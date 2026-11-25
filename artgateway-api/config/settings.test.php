<?php

declare(strict_types=1);

return static function (array $settings): array {
    $settings['db'] = array_replace($settings['db'], [
        'host' => '127.0.0.1',
        'port' => '3307',
        'database' => 'artgateway_db',
        'username' => 'artgateway_test',
        'password' => 'artgateway_test_password',
    ]);
    $settings['jwt']['secret'] = str_repeat('test-only-secret-', 3);
    $settings['logger']['path'] = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'artgateway-test-logs';
    $settings['error']['display_error_details'] = true;

    return $settings;
};
