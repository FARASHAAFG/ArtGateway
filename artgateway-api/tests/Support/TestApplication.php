<?php

declare(strict_types=1);

namespace Tests\Support;

use Slim\App;

final class TestApplication
{
    private static ?App $app = null;

    public static function reset(): void
    {
        self::$app = null;
    }

    public static function get(): App
    {
        if (self::$app === null) {
            try {
                $app = require APP_BASE_PATH . '/config/bootstrap.php';
            } finally {
                // Defaults and middleware each install a process-wide handler during setup.
                restore_error_handler();
                restore_error_handler();
            }

            if (!$app instanceof App) {
                throw new \RuntimeException('The Slim application could not be initialized.');
            }

            self::$app = $app;
        }

        return self::$app;
    }
}
