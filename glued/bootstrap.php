<?php
declare(strict_types=1);

use DI\Container;
use Nyholm\Psr7\Factory\Psr17Factory;
use Slim\Factory\AppFactory;
use Slim\Http\Factory\DecoratedResponseFactory;

define("__ROOT__", realpath(__DIR__ . '/..'));
require_once(__ROOT__ . '/vendor/autoload.php');

// Load .env file, don't override existing $_ENV values
// If GLUED_PROD = 1, rely purely on $_ENV and don't load
// the .env file (which is intended only for development)
// to improve performance.
if (!isset($_ENV['GLUED_PROD'])) {
    $dotenv = Dotenv\Dotenv::createImmutable(__ROOT__);
    $dotenv->safeLoad();
}

// Slim 4 comes decoupled from a container solution.
// We must set up the container and pass it to our app.
$container = new Container();
AppFactory::setContainer($container);
$app = AppFactory::create();

// DecoratedResponseFactory provides response decorators such as $response->withJson(). It takes 2 parameters:
// @param \Psr\Http\Message\ResponseFactoryInterface a ResponseFactory originating from the PSR-7 Implementation of choice (Nyhlolm)
// @param \Psr\Http\Message\StreamFactoryInterface a StreamFactory originating from the PSR-7 Implementation of choice (Nyhlolm)
// Nyholm/Psr17 has one factory which implements Both ResponseFactoryInterface and StreamFactoryInterface
// See https://github.com/Nyholm/psr7/blob/master/src/Factory/Psr17Factory.php
$nyholmFactory = new Psr17Factory();
$decoratedResponseFactory = new DecoratedResponseFactory($nyholmFactory, $nyholmFactory);
    
require_once (__ROOT__ . '/glued/container.php');
require_once (__ROOT__ . '/glued/environment.php');
require_once (__ROOT__ . '/glued/events.php');
require_once (__ROOT__ . '/glued/middleware.php');
require_once (__ROOT__ . '/glued/routes.php');

$app->run();

