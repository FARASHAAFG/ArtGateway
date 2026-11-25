<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class OpenApiContractTest extends TestCase
{
    public function testOpenApiDocumentsJsonXmlAndYamlRequestBodies(): void
    {
        $spec = Yaml::parseFile(
            dirname(__DIR__, 3)
            . DIRECTORY_SEPARATOR . 'docs'
            . DIRECTORY_SEPARATOR . 'openapi.yaml'
        );

        self::assertSame('3.0.3', $spec['openapi']);

        foreach ([
            ['/register', 'post'],
            ['/login', 'post'],
            ['/token', 'post'],
            ['/artworks', 'post'],
            ['/artworks/{id}', 'put'],
            ['/artists', 'post'],
            ['/artists/{id}', 'put'],
            ['/museums', 'post'],
            ['/museums/{id}', 'put'],
        ] as [$path, $method]) {
            self::assertSame(
                ['application/json', 'application/xml', 'application/yaml'],
                array_keys($spec['paths'][$path][$method]['requestBody']['content']),
                $method . ' ' . $path
            );
        }
    }
}
