<?php
/*
declare(strict_types=1);

use App\Middleware\ContentNegotiationMiddleware;
use App\Middleware\HelloMiddleware;
use Slim\App;

return function (App $app) {
    //TODO: Add your middleware here.
    
    $app->addBodyParsingMiddleware();
    $app->addRoutingMiddleware();

    $app->add(new ContentNegotiationMiddleware());

    //!NOTE: the error handling middleware MUST be added last.
    $errorMiddleware = $app->addErrorMiddleware(true, true, true);
    $errorMiddleware->getDefaultErrorHandler()->forceContentType(APP_MEDIA_TYPE_JSON);
    //!NOTE: You can add override the default error handler with your custom error handler.
    //* For more details, refer to Slim framework's documentation.
};
use App\Handlers\ApiErrorHandler;
use App\Middleware\ContentNegotiationMiddleware;
use App\Middleware\RequestIdMiddleware;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Slim\App;

return function (App $app) {
    $container = $app->getContainer();
    $logger = $container->get(LoggerInterface::class);

    // Never print PHP warnings/notices in the response. Turn them into exceptions instead.
    ini_set('display_errors', '0');
    set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) {
            return false; // this kind of error is switched off, ignore it
        }
        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    $app->addBodyParsingMiddleware();
    $app->addRoutingMiddleware();

    // Checks Accept / Content-Type -> throws 406 / 415 / 400
    $app->add(new ContentNegotiationMiddleware());

    // Catches EVERY exception thrown above and turns it into our standard JSON error.
    $errorMiddleware = $app->addErrorMiddleware(false, false, false);
    $errorMiddleware->setDefaultErrorHandler(
        new ApiErrorHandler($logger, $container->get(ResponseFactoryInterface::class))
    );

    // Outermost: gives each request an ID and logs it once the response is ready.
    $app->add(new RequestIdMiddleware($logger));
};*/
  
//merged version presumebly

declare(strict_types=1);

use App\Handlers\ApiErrorHandler;
use App\Middleware\ContentNegotiationMiddleware;
use App\Middleware\RequestIdMiddleware;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Slim\App;
use App\Middleware\ResponseFormatMiddleware;

return function (App $app) {
    $container = $app->getContainer();
    $logger = $container->get(LoggerInterface::class);

    // Never print PHP warnings/notices in the response.
    // Turn them into exceptions instead.
    ini_set('display_errors', '0');

    set_error_handler(function (
        int $severity,
        string $message,
        string $file,
        int $line
    ): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    // Middleware for routing and request processing.
    $app->addBodyParsingMiddleware();
    $app->addRoutingMiddleware();

    // Determine the response format requested by the client.
    $app->add(new ContentNegotiationMiddleware());

    // Centralized error handling.
    $errorMiddleware = $app->addErrorMiddleware(false, false, false);

    $errorMiddleware->setDefaultErrorHandler(
        new ApiErrorHandler(
            $logger,
            $container->get(ResponseFactoryInterface::class)
        )
    );

    // Outermost middleware: assigns a request ID.
    $app->add(new ResponseFormatMiddleware());
    $app->add(new RequestIdMiddleware($logger));
};
