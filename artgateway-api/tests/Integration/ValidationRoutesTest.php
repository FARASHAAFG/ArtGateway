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

final class ValidationRoutesTest extends TestCase
{
    private App $app;
    private string $adminToken;
    private array $queriedArtistIds = [];
    private array $queriedMuseumIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = TestApplication::get();
        $container = $this->app->getContainer();
        self::assertInstanceOf(Container::class, $container);

        $users = $this->createMock(UserModel::class);
        $users->method('findById')->willReturn([
            'id' => 12,
            'full_name' => 'Administrator',
            'email' => 'admin@example.test',
            'role' => 'admin',
        ]);
        $users->method('findByEmail')->willReturnCallback(
            static fn (string $email): ?array => $email === 'duplicate@example.test'
                ? [
                    'id' => 13,
                    'full_name' => 'Existing User',
                    'email' => $email,
                    'role' => 'user',
                ]
                : null
        );
        $users->expects(self::never())->method('createUser');
        $container->set(UserModel::class, $users);

        $artists = $this->createMock(ArtistModel::class);
        $artists->method('getById')->willReturnCallback(
            static fn (int $id): ?array => $id === 1
                ? [
                    'id' => 1,
                    'name' => 'Existing Artist',
                    'birthYear' => 1900,
                    'deathYear' => null,
                    'nationality' => null,
                ]
                : null
        );
        $artists->method('getArtworksByArtistId')->willReturnCallback(
            function (int $id, ?int $limit = null, ?int $offset = null): array {
                $this->queriedArtistIds[] = $id;
                return [];
            }
        );
        $artists->method('countArtworksByArtistId')->willReturn(0);
        $artists->expects(self::never())->method('create');
        $artists->expects(self::never())->method('updateById');
        $artists->expects(self::never())->method('deleteById');
        $container->set(ArtistModel::class, $artists);

        $artworks = $this->createMock(ArtworkModel::class);
        $artworks->method('getById')->willReturnCallback(
            static fn (int $id): ?array => $id === 1
                ? [
                    'id' => 1,
                    'title' => 'Existing Artwork',
                    'creationYear' => 1900,
                    'medium' => null,
                    'period' => null,
                    'dimensions' => null,
                    'artistId' => 1,
                    'museumId' => null,
                    'tags' => [],
                ]
                : null
        );
        $artworks->expects(self::never())->method('create');
        $artworks->expects(self::never())->method('updateById');
        $artworks->expects(self::never())->method('deleteById');
        $container->set(ArtworkModel::class, $artworks);

        $museums = $this->createMock(MuseumModel::class);
        $museums->method('getById')->willReturnCallback(
            static fn (int $id): ?array => $id === 1
                ? [
                    'id' => 1,
                    'name' => 'Existing Museum',
                    'city' => 'Paris',
                    'country' => 'France',
                    'website' => null,
                ]
                : null
        );
        $museums->method('getArtworksByMuseumId')->willReturnCallback(
            function (int $id, ?int $limit = null, ?int $offset = null): array {
                $this->queriedMuseumIds[] = $id;
                return [];
            }
        );
        $museums->method('countArtworksByMuseumId')->willReturn(0);
        $museums->expects(self::never())->method('create');
        $museums->expects(self::never())->method('updateById');
        $museums->expects(self::never())->method('deleteById');
        $container->set(MuseumModel::class, $museums);

        $jwt = $container->get(JwtService::class);
        self::assertInstanceOf(JwtService::class, $jwt);
        $this->adminToken = $jwt->issue(12, 'admin');
    }

    protected function tearDown(): void
    {
        TestApplication::reset();
        parent::tearDown();
    }

    public function testPathIdsArePositiveUnsignedDatabaseIntegers(): void
    {
        foreach ([
            '/artworks/not-an-id',
            '/artists/0',
            '/museums/4294967296',
            '/artists/not-an-id/artworks',
            '/museums/-1/artworks',
            '/artworks/not-an-id/external',
            '/artists/not-an-id/external',
        ] as $path) {
            $response = $this->request('GET', $path);
            self::assertSame(400, $response->getStatusCode(), $path);
            $error = $this->json($response);
            self::assertSame(400, $error['status']);
            self::assertArrayHasKey('id', $error['details']);
        }
    }

    public function testInvalidQueryValuesReturn400BeforeDatabaseReads(): void
    {
        foreach ([
            '/artworks?page=0',
            '/artworks?limit=101',
            '/artworks?sort=unknown',
            '/artworks?year_min=' . ((int) date('Y') + 1),
            '/artworks?year_min=1900&year_max=1800',
            '/artists?birthYear_max=' . ((int) date('Y') + 1),
            '/museums?limit=not-an-integer',
            '/artists/1/artworks?page=0',
            '/museums/1/artworks?limit=101',
        ] as $path) {
            $response = $this->request('GET', $path);
            self::assertSame(400, $response->getStatusCode(), $path);
            self::assertSame(400, $this->json($response)['status']);
        }
    }

    public function testMissingResourcesReturnStructured404Responses(): void
    {
        foreach ([
            '/artworks/999',
            '/artists/999',
            '/museums/999',
            '/artists/999/artworks',
            '/museums/999/artworks',
        ] as $path) {
            $response = $this->request('GET', $path);
            self::assertSame(404, $response->getStatusCode(), $path);
            $error = $this->json($response);
            self::assertSame(404, $error['status']);
            self::assertSame('Not Found', $error['error']);
            self::assertArrayHasKey('requestId', $error);
        }
    }

    public function testRelatedRouteIdsCannotBeOverriddenByQueryParameters(): void
    {
        $artistResponse = $this->request('GET', '/artists/1/artworks?id=99');
        $museumResponse = $this->request('GET', '/museums/1/artworks?id=99');

        self::assertSame(200, $artistResponse->getStatusCode());
        self::assertSame(200, $museumResponse->getStatusCode());
        self::assertSame([1], $this->queriedArtistIds);
        self::assertSame([1], $this->queriedMuseumIds);
    }

    public function testInvalidBodyAndForeignKeysNeverReachWriteMethods(): void
    {
        $invalidArtworkBodies = [
            [
                'creationYear' => 1900,
                'artistId' => 1,
            ],
            [
                'title' => 42,
                'creationYear' => 1900,
                'artistId' => 1,
            ],
            [
                'title' => str_repeat('x', 256),
                'creationYear' => 1900,
                'artistId' => 1,
            ],
            [
                'title' => 'Future work',
                'creationYear' => (int) date('Y') + 1,
                'artistId' => 1,
            ],
            [
                'title' => 'Text year',
                'creationYear' => '1900',
                'artistId' => 1,
            ],
            [
                'title' => 'Unknown artist',
                'creationYear' => 1900,
                'artistId' => 999,
            ],
            [
                'title' => 'Oversized artist id',
                'creationYear' => 1900,
                'artistId' => 4294967296,
            ],
            [
                'title' => 'Unknown museum',
                'creationYear' => 1900,
                'artistId' => 1,
                'museumId' => 999,
            ],
        ];

        foreach ($invalidArtworkBodies as $body) {
            $response = $this->request('POST', '/artworks', $body, $this->adminToken);
            self::assertSame(422, $response->getStatusCode());
            self::assertSame(422, $this->json($response)['status']);
        }

        $missingArtist = $this->request('POST', '/artists', [], $this->adminToken);
        $missingMuseum = $this->request(
            'POST',
            '/museums',
            ['name' => 'Missing fields'],
            $this->adminToken
        );
        self::assertSame(422, $missingArtist->getStatusCode());
        self::assertSame(422, $missingMuseum->getStatusCode());
    }

    public function testDuplicateRegistrationReturns409WithoutCreatingAnAccount(): void
    {
        $response = $this->request('POST', '/register', [
            'full_name' => 'Duplicate User',
            'email' => 'DUPLICATE@example.test',
            'password' => 'correct-horse',
        ]);

        self::assertSame(409, $response->getStatusCode());
        self::assertSame(409, $this->json($response)['status']);
    }

    private function request(
        string $method,
        string $path,
        ?array $body = null,
        ?string $token = null
    ): \Psr\Http\Message\ResponseInterface {
        $request = (new Psr17Factory())
            ->createServerRequest($method, 'http://localhost' . $path);

        if ($body !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withParsedBody($body);
        }
        if ($token !== null) {
            $request = $request->withHeader('Authorization', 'Bearer ' . $token);
        }

        return $this->app->handle($request);
    }

    private function json(\Psr\Http\Message\ResponseInterface $response): array
    {
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
