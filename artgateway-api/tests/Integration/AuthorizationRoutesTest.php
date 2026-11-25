<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Models\ArtistModel;
use App\Domain\Models\ArtworkModel;
use App\Domain\Models\MuseumModel;
use App\Domain\Models\UserModel;
use App\Services\JwtService;
use DI\Container;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Slim\App;
use Tests\Support\TestApplication;

final class AuthorizationRoutesTest extends TestCase
{
    protected function tearDown(): void
    {
        TestApplication::reset();
        parent::tearDown();
    }

    public function testPublicReadsAndAdminOnlyMutationsAcrossSlimRoutes(): void
    {
        $app = TestApplication::get();
        $container = $app->getContainer();
        self::assertInstanceOf(Container::class, $container);

        $users = $this->createMock(UserModel::class);
        $users->method('findById')->willReturnMap([
            [11, ['id' => 11, 'full_name' => 'Regular User', 'email' => 'user@example.test', 'role' => 'user']],
            [12, ['id' => 12, 'full_name' => 'Administrator', 'email' => 'admin@example.test', 'role' => 'admin']],
        ]);
        $container->set(UserModel::class, $users);

        $artists = $this->createMock(ArtistModel::class);
        $artists->method('countAll')->willReturn(0);
        $artists->method('getAll')->willReturn([]);
        $artists->method('getById')->willReturnCallback(static fn (int $id): ?array => $id === 101 ? [
            'id' => 101,
            'name' => 'Authorization Test Artist',
            'birthYear' => 1900,
            'deathYear' => null,
            'nationality' => null,
        ] : null);
        $artists->method('getArtworksByArtistId')->willReturn([]);
        $artists->method('countArtworksByArtistId')->willReturn(0);
        $artists->expects(self::once())->method('create')->willReturn(101);
        $artists->expects(self::once())->method('updateById')->with(101, self::anything());
        $artists->expects(self::once())->method('deleteById')->with(101)->willReturn(1);
        $container->set(ArtistModel::class, $artists);

        $artworks = $this->createMock(ArtworkModel::class);
        $artworks->method('countAll')->willReturn(0);
        $artworks->method('getAll')->willReturn([]);
        $artworks->method('getById')->willReturn([
            'id' => 201,
            'title' => 'Authorization Test Artwork',
            'creationYear' => 1900,
            'medium' => null,
            'period' => null,
            'dimensions' => null,
            'artistId' => 101,
            'museumId' => null,
            'tags' => [],
        ]);
        $artworks->expects(self::once())->method('create')->willReturn(201);
        $artworks->expects(self::once())->method('updateById')->with(201, self::anything());
        $artworks->expects(self::once())->method('deleteById')->with(201)->willReturn(1);
        $container->set(ArtworkModel::class, $artworks);

        $museums = $this->createMock(MuseumModel::class);
        $museums->method('getAll')->willReturn([]);
        $museums->method('countAll')->willReturn(0);
        $museums->method('getById')->willReturnCallback(static fn (int $id): ?array => $id === 1 ? [
            'id' => 1,
            'name' => 'Public Test Museum',
            'city' => 'Test City',
            'country' => 'Test Country',
            'website' => null,
        ] : null);
        $museums->method('getArtworksByMuseumId')->willReturn([]);
        $museums->method('countArtworksByMuseumId')->willReturn(0);
        $museums->expects(self::once())->method('create')->willReturn(1);
        $museums->expects(self::once())->method('updateById')->with(1, self::anything());
        $museums->expects(self::once())->method('deleteById')->with(1)->willReturn(1);
        $container->set(MuseumModel::class, $museums);

        try {
            $jwt = $container->get(JwtService::class);
            self::assertInstanceOf(JwtService::class, $jwt);
            $userToken = $jwt->issue(11, 'user');
            $adminToken = $jwt->issue(12, 'admin');

            foreach ([
                '/',
                '/ping',
                '/artists',
                '/artists/101',
                '/artists/101/artworks',
                '/artworks',
                '/artworks/201',
                '/museums',
                '/museums/1',
                '/museums/1/artworks',
            ] as $path) {
                self::assertSame(200, $this->request($app, 'GET', $path)->getStatusCode(), $path);
            }

            $museumPage = $this->request($app, 'GET', '/museums?limit=1');
            $museumPageBody = json_decode((string) $museumPage->getBody(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame(200, $museumPage->getStatusCode());
            self::assertSame(1, $museumPageBody['pagination']['per_page']);
            self::assertSame([], $museumPageBody['data']);

            $xmlMuseumPageRequest = (new Psr17Factory())
                ->createServerRequest('GET', 'http://localhost/museums?limit=1')
                ->withHeader('Accept', 'application/xml');
            $xmlMuseumPage = $app->handle($xmlMuseumPageRequest);
            self::assertSame(200, $xmlMuseumPage->getStatusCode());
            self::assertSame('application/xml', $xmlMuseumPage->getHeaderLine('Content-Type'));
            self::assertStringContainsString('<response>', (string) $xmlMuseumPage->getBody());

            $artistArtworksPage = $this->request($app, 'GET', '/artists/101/artworks?limit=1');
            $artistArtworksBody = json_decode((string) $artistArtworksPage->getBody(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame(200, $artistArtworksPage->getStatusCode());
            self::assertSame(0, $artistArtworksBody['pagination']['total_items']);
            self::assertArrayHasKey('links', $artistArtworksBody);

            $museumArtworksPage = $this->request($app, 'GET', '/museums/1/artworks?limit=1');
            $museumArtworksBody = json_decode((string) $museumArtworksPage->getBody(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame(200, $museumArtworksPage->getStatusCode());
            self::assertSame(0, $museumArtworksBody['pagination']['total_items']);
            self::assertArrayHasKey('links', $museumArtworksBody);

            $invalidPage = $this->request($app, 'GET', '/museums?limit=101');
            self::assertSame(400, $invalidPage->getStatusCode());
            self::assertSame(
                'limit cannot exceed 100.',
                json_decode((string) $invalidPage->getBody(), true, 512, JSON_THROW_ON_ERROR)['details']['limit']
            );
            self::assertSame(404, $this->request($app, 'GET', '/museums/999/artworks')->getStatusCode());
            self::assertSame(404, $this->request($app, 'GET', '/artists/999/artworks')->getStatusCode());

            $mutations = [
                ['POST', '/artists', ['name' => 'Authorization Test Artist']],
                ['PUT', '/artists/101', ['name' => 'Updated Authorization Artist']],
                ['DELETE', '/artists/101', []],
                ['POST', '/artworks', ['title' => 'Authorization Test Artwork', 'creationYear' => 1900, 'artistId' => 101]],
                ['PUT', '/artworks/201', ['title' => 'Updated Authorization Artwork', 'creationYear' => 1900, 'artistId' => 101]],
                ['DELETE', '/artworks/201', []],
                ['POST', '/museums', ['name' => 'Public Test Museum', 'city' => 'Test City', 'country' => 'Test Country']],
                ['PUT', '/museums/1', ['name' => 'Updated Test Museum', 'city' => 'Test City', 'country' => 'Test Country']],
                ['DELETE', '/museums/1', []],
            ];

            foreach ($mutations as [$method, $path, $body]) {
                $unauthenticated = $this->request($app, $method, $path, $body);
                self::assertSame(401, $unauthenticated->getStatusCode(), 'Anonymous request: ' . $method . ' ' . $path);
                $error = json_decode((string) $unauthenticated->getBody(), true, 512, JSON_THROW_ON_ERROR);
                self::assertSame(401, $error['status']);
                self::assertSame('Unauthorized', $error['error']);

                $regularUser = $this->request($app, $method, $path, $body, $userToken);
                self::assertSame(403, $regularUser->getStatusCode(), 'Regular user: ' . $method . ' ' . $path);
                $error = json_decode((string) $regularUser->getBody(), true, 512, JSON_THROW_ON_ERROR);
                self::assertSame(403, $error['status']);
                self::assertSame('Forbidden', $error['error']);

                $administrator = $this->request($app, $method, $path, $body, $adminToken);
                $expectedStatus = $method === 'POST' ? 201 : ($method === 'DELETE' ? 204 : 200);
                self::assertSame($expectedStatus, $administrator->getStatusCode(), 'Administrator: ' . $method . ' ' . $path);
            }

            $invalidMuseum = $this->request($app, 'POST', '/museums', [
                'name' => 'Invalid Website Museum',
                'city' => 'Test City',
                'country' => 'Test Country',
                'website' => 42,
            ], $adminToken);
            self::assertSame(422, $invalidMuseum->getStatusCode());
            self::assertArrayHasKey(
                'website',
                json_decode((string) $invalidMuseum->getBody(), true, 512, JSON_THROW_ON_ERROR)['details']
            );
        } finally {
            TestApplication::reset();
        }
    }

    private function request(
        App $app,
        string $method,
        string $path,
        array $body = [],
        ?string $token = null
    ): \Psr\Http\Message\ResponseInterface {
        $request = (new Psr17Factory())
            ->createServerRequest($method, 'http://localhost' . $path);

        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withParsedBody($body);
        }
        if ($token !== null) {
            $request = $request->withHeader('Authorization', 'Bearer ' . $token);
        }

        return $app->handle($request);
    }
}
