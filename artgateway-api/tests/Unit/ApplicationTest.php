<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\Core\AppSettings;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestApplication;

final class ApplicationTest extends TestCase
{
    public function testApplicationInitializesAndRegistersArtworkRoutes(): void
    {
        $app = TestApplication::get();
        $patterns = array_map(
            static fn ($route): string => $route->getPattern(),
            $app->getRouteCollector()->getRoutes()
        );

        self::assertContains('/artworks', $patterns);
        self::assertContains('/artworks/{id}', $patterns);
        self::assertContains('/ping', $patterns);
        self::assertContains('/register', $patterns);
        self::assertContains('/login', $patterns);
        self::assertContains('/token', $patterns);
        self::assertContains('/me', $patterns);
    }

    public function testTestEnvironmentUsesDedicatedDatabaseSettings(): void
    {
        $settings = TestApplication::get()
            ->getContainer()
            ->get(AppSettings::class)
            ->get('db');

        self::assertSame('127.0.0.1', $settings['host']);
        self::assertSame('3307', (string) $settings['port']);
        self::assertSame('artgateway_db', $settings['database']);
        self::assertSame('artgateway_test', $settings['username']);
    }

    public function testUnknownRouteReturnsStructuredJsonError(): void
    {
        $request = (new Psr17Factory())->createServerRequest(
            'GET',
            'http://localhost/route-that-does-not-exist'
        );

        $response = TestApplication::get()->handle($request);
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame(404, $body['status']);
        self::assertSame('Not Found', $body['error']);
        self::assertArrayHasKey('requestId', $body);
    }

    public function testProtectedMeRejectsMissingCredentialsThroughSlim(): void
    {
        $request = (new Psr17Factory())->createServerRequest('GET', 'http://localhost/me');

        $response = TestApplication::get()->handle($request);
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('Unauthorized', $body['error']);
        self::assertSame('A valid bearer token is required', $body['message']);
    }

    public function testProtectedMeRejectsInvalidTokenThroughSlim(): void
    {
        $request = (new Psr17Factory())
            ->createServerRequest('GET', 'http://localhost/me')
            ->withHeader('Authorization', 'Bearer invalid-token');

        $response = TestApplication::get()->handle($request);
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('The bearer token is invalid or expired', $body['message']);
        self::assertArrayHasKey('requestId', $body);
    }

    public function testAboutRouteNegotiatesXmlThroughTheSlimApplication(): void
    {
        $request = (new Psr17Factory())
            ->createServerRequest('GET', 'http://localhost/')
            ->withHeader('Accept', 'application/xml');

        $response = TestApplication::get()->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/xml', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('<response>', (string) $response->getBody());
    }
}
