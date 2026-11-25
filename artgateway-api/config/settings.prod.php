<?php

declare(strict_types=1);
// Production environment

return function (array $settings): array {
    $settings['db']['database'] = 'artgateway_db';
    $settings['db']['host'] = 'localhost';
    $settings['db']['port'] = '3306';

    return $settings;
};
