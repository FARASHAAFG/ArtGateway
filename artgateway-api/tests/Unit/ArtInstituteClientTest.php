<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ArtInstituteClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ArtInstituteClientTest extends TestCase
{
    private array $history = [];

    public function testArtworkLookupUsesMockedHttpResponse(): void
    {
        $body = json_encode([
            'config' => ['iiif_url' => 'https://www.artic.edu/iiif/2'],
            'data' => [[
                'id' => 123,
                'title' => 'A Test Painting',
                'artist_title' => 'Test Artist',
                'date_display' => '1900',
                'medium_display' => 'Oil on canvas',
                'place_of_origin' => 'France',
                'department_title' => 'Painting',
                'short_description' => 'A fixture response',
                'is_public_domain' => true,
                'image_id' => '12345678-1234-1234-1234-123456789abc',
            ]],
        ], JSON_THROW_ON_ERROR);
        $http = new Client([
            'base_uri' => 'https://example.invalid/',
            'handler' => HandlerStack::create(new MockHandler([
                new Response(200, ['Content-Type' => 'application/json'], $body),
            ])),
        ]);
        $client = new ArtInstituteClient([
            'base_uri' => 'https://example.invalid/',
            'timeout' => 1,
            'connect_timeout' => 1,
            'user_agent' => 'ArtGateway test',
        ], new NullLogger(), $http);

        $result = $client->findArtwork('A Test Painting', 'Test Artist');

        self::assertSame('ok', $result['status']);
        self::assertSame('A Test Painting', $result['data']['title']);
        self::assertSame(
            'https://www.artic.edu/iiif/2/12345678-1234-1234-1234-123456789abc/full/843,/0/default.jpg',
            $result['data']['imageUrl']
        );
    }

    public function testArtworkLookupSendsExpectedRequestAndCleansExternalData(): void
    {
        $client = $this->client([
            new Response(
                200,
                ['Content-Type' => 'application/json; charset=utf-8'],
                json_encode([
                    'config' => ['iiif_url' => 'https://www.artic.edu/iiif/2'],
                    'data' => [[
                        'id' => 123,
                        'title' => 'A Test Painting',
                        'artist_title' => 'Test Artist',
                        'date_display' => '1900',
                        'medium_display' => '<b>Oil</b>   on canvas',
                        'place_of_origin' => 'France',
                        'department_title' => 'Painting',
                        'short_description' => '<p>A fixture response.</p>',
                        'is_public_domain' => true,
                        'image_id' => '12345678-1234-1234-1234-123456789abc',
                    ]],
                ], JSON_THROW_ON_ERROR)
            ),
        ]);

        $result = $client->findArtwork(' A Test Painting ', 'Test Artist', 'request-1');

        self::assertSame('ok', $result['status']);
        self::assertSame('A Test Painting', $result['data']['title']);
        self::assertSame('Oil on canvas', $result['data']['medium']);
        self::assertSame('A fixture response.', $result['data']['description']);
        self::assertSame('https://www.artic.edu/artworks/123', $result['data']['sourceUrl']);

        $request = $this->history[0]['request'];
        self::assertSame('GET', $request->getMethod());
        self::assertSame('/api/v1/artworks/search', $request->getUri()->getPath());
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertSame('ArtGateway automated tests', $request->getHeaderLine('AIC-User-Agent'));

        parse_str($request->getUri()->getQuery(), $query);
        self::assertSame('A Test Painting Test Artist', $query['q']);
        self::assertSame('5', $query['limit']);
        self::assertStringContainsString('artist_title', $query['fields']);
        self::assertSame(3.0, $this->history[0]['options']['timeout']);
        self::assertSame(2.0, $this->history[0]['options']['connect_timeout']);
        self::assertFalse($this->history[0]['options']['http_errors']);
        self::assertFalse($this->history[0]['options']['allow_redirects']);
    }

    public function testArtistLookupSkipsNonArtistsAndTransformsTheMatch(): void
    {
        $client = $this->client([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [
                    ['id' => 70, 'title' => 'Claude Monet Foundation', 'is_artist' => false],
                    [
                        'id' => 71,
                        'title' => 'Claude Monet',
                        'alt_titles' => ['Oscar-Claude Monet', ['unexpected']],
                        'birth_date' => '1840',
                        'death_date' => 'unknown',
                        'description' => '<p>French Impressionist painter.</p>',
                        'is_artist' => true,
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $client->findArtist('Claude Monet');

        self::assertSame('ok', $result['status']);
        self::assertSame('Claude Monet', $result['data']['name']);
        self::assertSame(1840, $result['data']['birthYear']);
        self::assertNull($result['data']['deathYear']);
        self::assertSame('French Impressionist painter.', $result['data']['description']);

        $request = $this->history[0]['request'];
        self::assertSame('/api/v1/agents/search', $request->getUri()->getPath());
        parse_str($request->getUri()->getQuery(), $query);
        self::assertSame('Claude Monet', $query['q']);
        self::assertSame('5', $query['limit']);
        self::assertStringContainsString('birth_date', $query['fields']);
    }

    public function testEmptySearchResultsAreNotFound(): void
    {
        $client = $this->client([
            new Response(200, ['Content-Type' => 'application/json'], '{"data":[]}'),
        ]);

        self::assertSame(['status' => 'not_found'], $client->findArtwork('No Match', ''));
    }

    public function testHttpErrorsIncludingQuotaResponsesBecomeUnavailableWithoutRetry(): void
    {
        foreach ([302, 404, 429, 500] as $status) {
            $client = $this->client([
                new Response($status, ['Content-Type' => 'application/json', 'Retry-After' => '60'], '{}'),
            ]);

            $result = $client->findArtwork('A Test Painting', '');

            self::assertSame(['status' => 'unavailable'], $result, 'HTTP ' . $status);
            self::assertCount(1, $this->history, 'HTTP ' . $status . ' should not be retried');
        }
    }

    public function testTimeoutAndConnectionFailureBecomeUnavailable(): void
    {
        $client = $this->client([
            new ConnectException(
                'Simulated upstream timeout',
                new Request('GET', 'https://api.artic.edu/api/v1/artworks/search')
            ),
        ]);

        self::assertSame(['status' => 'unavailable'], $client->findArtwork('A Test Painting', ''));
    }

    public function testMalformedAndUnexpectedUpstreamResponsesBecomeUnavailable(): void
    {
        $responses = [
            new Response(200, ['Content-Type' => 'application/json'], '{invalid JSON'),
            new Response(200, ['Content-Type' => 'text/html'], '{"data":[]}'),
            new Response(200, ['Content-Type' => 'application/json'], '{"results":[]}'),
            new Response(200, ['Content-Type' => 'application/json'], '{"data":{"one":{"id":1,"title":"A Test Painting"}}}'),
            new Response(200, ['Content-Type' => 'application/json'], '{"data":[{"id":"1","title":"A Test Painting"}]}'),
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                '{"config":"unexpected","data":[{"id":1,"title":"A Test Painting"}]}'
            ),
        ];

        foreach ($responses as $response) {
            $client = $this->client([$response]);

            self::assertSame(
                ['status' => 'unavailable'],
                $client->findArtwork('A Test Painting', '')
            );
        }
    }

    public function testMalformedOptionalFieldsAreDiscardedWithoutWarnings(): void
    {
        $client = $this->client([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'config' => ['iiif_url' => 'https://www.artic.edu/iiif/2'],
                'data' => [[
                    'id' => 123,
                    'title' => 'A Test Painting',
                    'artist_title' => ['unexpected'],
                    'medium_display' => ['unexpected'],
                    'image_id' => '------------------------------------',
                    'is_public_domain' => true,
                ]],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $client->findArtwork('A Test Painting', '');

        self::assertSame('ok', $result['status']);
        self::assertNull($result['data']['artist']);
        self::assertNull($result['data']['medium']);
        self::assertNull($result['data']['imageUrl']);
    }

    private function client(array $responses): ArtInstituteClient
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new ArtInstituteClient([
            'base_uri' => 'https://api.artic.edu/api/v1/',
            'timeout' => 3.0,
            'connect_timeout' => 2.0,
            'user_agent' => 'ArtGateway automated tests',
        ], new NullLogger(), new Client([
            'base_uri' => 'https://api.artic.edu/api/v1/',
            'handler' => $stack,
        ]));
    }
}
