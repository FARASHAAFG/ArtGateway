<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\ContentNegotiator;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class ContentNegotiatorTest extends TestCase
{
    public function testMissingAcceptHeaderDefaultsToJson(): void
    {
        $request = (new Psr17Factory())->createServerRequest('GET', 'http://localhost/');

        self::assertSame(
            [
                'success' => true,
                'format' => 'json',
                'contentType' => 'application/json',
            ],
            (new ContentNegotiator())->negotiate($request)
        );
    }

    public function testUnsupportedAcceptHeaderReturnsNotAcceptable(): void
    {
        $request = (new Psr17Factory())
            ->createServerRequest('GET', 'http://localhost/')
            ->withHeader('Accept', 'text/plain');

        $result = (new ContentNegotiator())->negotiate($request);

        self::assertFalse($result['success']);
        self::assertSame(406, $result['status']);
    }
}
