<?php

declare(strict_types=1);
// Production environment

return function (array $settings): array {
    $settings['db']['database'] = 'artgateway_db';
    $settings['db']['hostname'] = 'localhost';

    return $settings;
};
