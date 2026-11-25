<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Models\ArtistModel;
use App\Domain\Models\ArtworkModel;
use App\Services\ArtInstituteClient;
use DI\Container;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Yaml\Yaml;
use Tests\Support\TestApplication;

final class CompositeRoutesTest extends TestCase
{
    private \Slim\App $app;
    private Container $container;
    private array $artwork;
    private array $artist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = TestApplication::get();
        $container = $this->app->getContainer();
        self::assertInstanceOf(Container::class, $container);
        $this->container = $container;

        $this->artist = [
            'id' => 8,
            'name' => 'Test Artist',
            'birthYear' => 1900,
            'deathYear' => null,
            'nationality' => 'Test',
        ];
        $this->artwork = [
            'id' => 7,
            'title' => 'A Test Painting',
            'creationYear' => 1900,
            'medium' => 'Oil on canvas',
            'period' => 'Test period',
            'dimensions' => null,
            'artistId' => 8,
            'museumId' => null,
            'tags' => [],
        ];

        $artworks = $this->createMock(ArtworkModel::class);
        $artworks->method('getById')->willReturnCallback(
            fn (int $id): ?array => $id === 7 ? $this->artwork : null
        );
        $this->container->set(ArtworkModel::class, $artworks);

        $artists = $this->createMock(ArtistModel::class);
        $artists->method('getById')->willReturnCallback(
            fn (int $id): ?array => $id === 8 ? $this->artist : null
        );
        $artists->method('getArtworksByArtistId')->willReturn([$this->artwork]);
        $this->container->set(ArtistModel::class, $artists);
    }

    protected function tearDown(): void
    {
        TestApplication::reset();
        parent::tearDown();
    }

    public function testArtworkCompositeRouteUsesUpstreamDataAndXmlResponseFormatting(): void
    {
        $this->useUpstreamResponses([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'config' => ['iiif_url' => 'https://www.artic.edu/iiif/2'],
                'data' => [[
                    'id' => 123,
                    'title' => 'A Test Painting',
                    'artist_title' => 'Test Artist',
                    'is_public_domain' => false,
                ]],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $response = $this->get('/artworks/7/external', 'application/xml');
        $body = (string) $response->getBody();
        $document = new \DOMDocument();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/xml', $response->getHeaderLine('Content-Type'));
        self::assertTrue($document->loadXML($body));
        self::assertSame(
            'ok',
            $document->getElementsByTagName('externalStatus')->item(0)?->textContent
        );
        self::assertSame(
            'A Test Painting',
            $document->getElementsByTagName('title')->item(0)?->textContent
        );
    }

    public function testArtistCompositeRouteUsesUpstreamDataAndYamlResponseFormatting(): void
    {
        $this->useUpstreamResponses([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [[
                    'id' => 71,
                    'title' => 'Test Artist',
                    'birth_date' => '1900',
                    'death_date' => null,
                    'description' => 'Artist fixture.',
                    'is_artist' => true,
                ]],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $response = $this->get('/artists/8/external', 'application/yaml');
        $body = Yaml::parse((string) $response->getBody());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/yaml', $response->getHeaderLine('Content-Type'));
        self::assertSame('ok', $body['externalStatus']);
        self::assertSame('Test Artist', $body['artist']['name']);
        self::assertCount(1, $body['artworks']);
        self::assertSame(1900, $body['external']['birthYear']);
    }

    public function testNoUpstreamMatchKeepsLocalCompositeResponseSuccessful(): void
    {
        $this->useUpstreamResponses([
            new Response(200, ['Content-Type' => 'application/json'], '{"data":[]}'),
        ]);

        $response = $this->get('/artworks/7/external', 'application/json');
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('not_found', $body['externalStatus']);
        self::assertNull($body['external']);
        self::assertSame($this->artwork['title'], $body['artwork']['title']);
    }

    public function testQuotaFailureDoesNotCrashOrExposeUpstreamDetails(): void
    {
        $this->useUpstreamResponses([
            new Response(
                429,
                ['Content-Type' => 'application/json', 'Retry-After' => '60'],
                '{"error":"private quota detail"}'
            ),
        ]);

        $response = $this->get('/artworks/7/external', 'application/json');
        $bodyText = (string) $response->getBody();
        $body = json_decode($bodyText, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('unavailable', $body['externalStatus']);
        self::assertNull($body['external']);
        self::assertSame($this->artwork['title'], $body['artwork']['title']);
        self::assertStringNotContainsString('private quota detail', $bodyText);
    }

    public function testUnknownLocalResourceReturnsStructured404BeforeUpstreamCall(): void
    {
        $this->useUpstreamResponses([]);

        $response = $this->get('/artworks/999/external', 'application/json');
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('Artwork not found', $body['message']);
    }

    private function useUpstreamResponses(array $responses): void
    {
        $http = new Client([
            'base_uri' => 'https://api.artic.edu/api/v1/',
            'handler' => HandlerStack::create(new MockHandler($responses)),
        ]);

        $this->container->set(ArtInstituteClient::class, new ArtInstituteClient([
            'base_uri' => 'https://api.artic.edu/api/v1/',
            'timeout' => 3.0,
            'connect_timeout' => 2.0,
            'user_agent' => 'ArtGateway controlled integration fixture',
        ], new NullLogger(), $http));
    }

    private function get(string $path, string $accept): ResponseInterface
    {
        $request = (new Psr17Factory())
            ->createServerRequest('GET', 'http://localhost' . $path)
            ->withHeader('Accept', $accept);

        return $this->app->handle($request);
    }
}
