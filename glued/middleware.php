<?php
declare(strict_types=1);


use DI\Container;
use Glued\Classes\Error\HtmlErrorRenderer;
use Glued\Classes\Error\JsonErrorRenderer;
use Glued\Lib\Middleware\AntiXSSMiddleware;
use Glued\Lib\Middleware\TimerMiddleware;
use Middlewares\Csp;
use Middlewares\TrailingSlash;
use Nyholm\Psr7\Response as Psr7Response;
use ParagonIE\CSPBuilder\CSPBuilder;
use Slim\App;
use Slim\Exception\HttpNotFoundException;
use Slim\Middleware\ErrorMiddleware;
use Slim\Middleware\MethodOverrideMiddleware;
use Slim\addRoutingMiddleware;
use Tuupola\Middleware\CorsMiddleware;
use Zeuxisoo\Whoops\Slim\WhoopsMiddleware;
use Whoops\Handler\JsonResponseHandler;

/**
 * WARNING
 * 
 * In Slim 4 middlewares are executed in the reverse order as they appear in middleware.php.
 * Do not change the order of the middleware below without a good thought. The first middleware
 * to kick must always be the error middleware, so it has to be at the end of this file.
 * 
 */


// TimerMiddleware injects the time needed to generate the response.
$app->add(TimerMiddleware::class);


// BodyParsingMiddleware detects the content-type and automatically decodes
// json, x-www-form-urlencoded and xml decodes the $request->getBody() 
// properti into a php array and places it into $request->getParsedBody(). 
// See https://www.slimframework.com/docs/v4/middleware/body-parsing.html
$app->addBodyParsingMiddleware();


// TrailingSlash(false) removes the trailing from requests, for example
// `https://example.com/user/` will change into https://example.com/user.
// Optionally, setting redirect(true) enforces a 301 redirect.
$trailingSlash = new TrailingSlash(false);
$trailingSlash->redirect();
$app->add($trailingSlash);


// The Csp middleware injects csp headers as defined in
// $settings['headers']['csp']. It also provides the $nonce array
// which is consumed by the TwigCspMiddleware.
$csp = new CSPBuilder($settings['headers']['csp']);
$nonce['script_src'] = $csp->nonce('script-src');
$nonce['style_src'] = $csp->nonce('style-src');
$app->add(new Middlewares\Csp($csp));


// The CorsMiddleware injects `Access-Control-*` response headers and acts
// accordingly. TODO configure CorsMiddleware
/*
$app->add(new Tuupola\Middleware\CorsMiddleware([
    "origin" => ["*"],
    "methods" => ["GET", "POST", "PUT", "PATCH", "DELETE"],
    "headers.allow" => ["Authorization", "If-Match", "If-Unmodified-Since", "DNT", "Keep-Alive", "User-Agent", "X-CustomHeader", "X-Requested-With", "If-Modified-Since", "Cache-Control", "Content-Type", "Content-Range", "Content-Length" ],
    "headers.expose" => ["Etag"],
    "credentials" => true,
    "cache" => 600
]));
*/

// RoutingMiddleware provides the FastRoute router. See
// https://www.slimframework.com/docs/v4/middleware/routing.html
$app->addRoutingMiddleware();


// Per the HTML standard, desktop browsers will only submit GET and POST requests, PUT
// and DELETE requests will be handled as GET. MethodOverrideMiddleware allows browsers
// to submit pseudo PUT and DELETE requests by relying on pre-determined request 
// parameters, either a `X-Http-Method-Override` header, or a `_METHOD` form value
// and behave as a propper API client. This middleware must be added before 
// $app->addRoutingMiddleware().
$app->add(new MethodOverrideMiddleware);


/**
 * *******************************
 * ERROR HANDLING MIDDLEWARE
 * *******************************
 * 
 * This middleware must be added last. It will not handle any exceptions/errors
 * for middleware added after it.
 */

if ($settings['slim']['debugEngine'] == "Whoops") {
    //$_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
    $app->add(new Zeuxisoo\Whoops\Slim\WhoopsMiddleware([
        'enable' => true,
        'editor' => 'sublime',
        'title'  => 'Custom whoops page title',
    ]));

} else {

    /**
     * @param bool $displayErrorDetails -> Should be set to false in production
     * @param bool $logErrors -> Parameter is passed to the default ErrorHandler
     * @param bool $logErrorDetails -> Display error details in error log
     * which can be replaced by a callable of your choice.
     */
    $errorMiddleware = new ErrorMiddleware(
        $app->getCallableResolver(),
        $app->getResponseFactory(),
        $settings['slim']['displayErrorDetails'],
        $settings['slim']['logErrors'],
        $settings['slim']['logErrorDetails']
    );

    //if ($settings['displayErrorDetails'] === false) {
        $errorHandler = $errorMiddleware->getDefaultErrorHandler();
        $errorHandler->registerErrorRenderer('application/json', JsonErrorRenderer::class);
        //$errorHandler->registerErrorRenderer('text/html', HtmlErrorRenderer::class);
        $errorHandler->forceContentType('application/json');
        // TODO beautify html renderer
        // TODO review json renderer & modify if needed
        // HELP https://akrabat.com/custom-error-rendering-in-slim-4/
    //}

    $app->add($errorMiddleware);

    /*
    // Example 404 override. Usage: `throw new HttpNotFoundException($request, 'optional message');`
    // Must be placed above the $app->add();
    $errorMiddleware->setErrorHandler(HttpNotFoundException::class, function ($request, $exception, $displayErrorDetails, $logErrors, $logErrorDetails) use ($container) {
        $response = new Psr7Response();
        return $container->get('view')->render(
            $response->withStatus(404), 
            'Core/Views/errors/404.twig'
        );
    });
    */
   
}







