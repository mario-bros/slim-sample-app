<?php
use Aura\Di\Container;
use Aura\Di\Factory;
use Slim\App;

define ('ROOT', dirname(__DIR__));
define ('ROOT_APP', ROOT . '/src');

require_once(ROOT . '/vendor/autoload.php');

// Set up dependencies

$di = new Container(new Factory());
$di->set('settings', $di->lazyRequire(ROOT_APP . '/config.php'));
$di->set(\View\RendererInterface::class, $di->lazyNew(\View\Twig::class));
$di->set(\Doctrine\ORM\EntityManagerInterface::class, $di->lazy(function () use ($di) {
    $settings = $di->get('settings');
    $createEntityManager = require_once(ROOT_APP . '/Infrastructure/Data/Doctrine/bootstrap.php');
    return $createEntityManager($settings['db']);
}));
$di->setter[\Controller\ControllerInterface::class]['setRenderer'] = $di->lazyGet(\View\RendererInterface::class);

// Create application

$app = new App($di->get('settings'));
$app->get('/login', function (\Slim\Http\Request $request, \Slim\Http\Response $response, array $args) use ($di) {
    $response->write($di->get(\View\RendererInterface::class)->render('auth/login.html', [
        'debug' => true
    ]));
    return $response;
});

// Configure view

$view = $di->get(\View\RendererInterface::class);
$view->parserOptions = [
    'debug' => true,
    'cache' => ROOT . '/cache',
];
$view->twigTemplateDirs = ROOT . '/templates';
$view->parserExtensions = [
    new \View\Twig\Extension($app['request']->getUri(), $app['router'])
];

// Set up routes

$app->get('/hello/{name}', 'Hello:hello')->setName('hello');

foreach ([
    'Hello' => \Controller\Hello::class
] as $key => $class) {
    $app[$key] = function (App $app) use($di, $class) {
        return $di->newInstance($class);
    };
}

// Run application

$app->run();