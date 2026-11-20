<?php

declare(strict_types=1);

use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use App\Helpers\Core\AppSettings;
use App\Helpers\Core\PDOService;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Slim\Factory\AppFactory;
use Slim\App;
use Slim\Interfaces\RouteParserInterface;
use App\Services\ArtInstituteClient;

$definitions = [
    AppSettings::class => function () {
        return new AppSettings(
            require_once __DIR__ . '/settings.php'
        );
    },
    App::class => function (ContainerInterface $container) {

        $app = AppFactory::createFromContainer($container);
        // Set base path from APP_ROOT_DIR (empty in Docker, subdirectory name in Wampoon)
        $app->setBasePath(APP_ROOT_DIR ? '/' . APP_ROOT_DIR : '');

        // Register routes
        (require_once __DIR__ . '/../app/Routes/routes.php')($app);

        // Register middleware
        (require_once __DIR__ . '/middleware.php')($app);

        return $app;
    },

    PDOService::class => function (ContainerInterface $container): PDOService {
        $db_config = $container->get(AppSettings::class)->get('db');
        return new PDOService($db_config);
    },

    // The logger: writes to var/logs/app-YYYY-MM-DD.log (one file per day, keeps the last 14).
    LoggerInterface::class => function (ContainerInterface $container): LoggerInterface {
        $logger_settings = $container->get(AppSettings::class)->get('logger');
        $logger = new Logger('artgateway');
        $logger->pushHandler(
            new RotatingFileHandler($logger_settings['path'] . '/app.log', 14, $logger_settings['level'])
        );
        return $logger;
    },
    
    ArtInstituteClient::class => function (ContainerInterface $container): ArtInstituteClient {
        $settings = $container->get(AppSettings::class)->get('external');
        return new ArtInstituteClient($settings['artic'], $container->get(LoggerInterface::class));
    },    

    // HTTP factories
    ResponseFactoryInterface::class => function (ContainerInterface $container) {
        return $container->get(Psr17Factory::class);
    },
    ServerRequestFactoryInterface::class => function (ContainerInterface $container) {
        return $container->get(Psr17Factory::class);
    },
    StreamFactoryInterface::class => function (ContainerInterface $container) {
        return $container->get(Psr17Factory::class);
    },
    UriFactoryInterface::class => function (ContainerInterface $container) {
        return $container->get(Psr17Factory::class);
    },
    // The Slim RouterParser
    RouteParserInterface::class => function (ContainerInterface $container) {
        return $container->get(App::class)->getRouteCollector()->getRouteParser();
    },
];
return $definitions;
