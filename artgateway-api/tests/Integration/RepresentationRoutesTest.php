<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Models\MuseumModel;
use App\Domain\Models\ArtistModel;
use App\Domain\Models\ArtworkModel;
use App\Domain\Models\UserModel;
use App\Services\JwtService;
use DI\Container;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\NullLogger;
use App\Middleware\ResponseFormatMiddleware;
use Slim\App;
use Symfony\Component\Yaml\Yaml;
use Tests\Support\TestApplication;

final class RepresentationRoutesTest extends TestCase
{
    private App $app;
    private Container $container;
    private string $adminToken;

    protected function setUp(): void
    {
        $this->app = TestApplication::get();
        $container = $this->app->getContainer();
        self::assertInstanceOf(Container::class, $container);
        $this->container = $container;

        $users = $this->createMock(UserModel::class);
        $users->method('findById')->willReturnCallback(static fn (int $id): array => $id === 21
            ? [
                'id' => 21,
                'full_name' => 'Test User',
                'email' => 'user@example.test',
                'role' => 'user',
            ]
            : [
                'id' => 12,
                'full_name' => 'Administrator',
                'email' => 'admin@example.test',
                'role' => 'admin',
            ]);
        $users->method('findByEmail')->willReturn(null);
        $users->method('createUser')->willReturn(21);
        $users->method('verifyCredentials')->willReturn([
            'id' => 21,
            'full_name' => 'Test User',
            'email' => 'user@example.test',
            'role' => 'user',
        ]);
        $this->container->set(UserModel::class, $users);

        $artists = $this->createMock(ArtistModel::class);
        $artists->method('getAll')->willReturn([]);
        $artists->method('countAll')->willReturn(0);
        $this->container->set(ArtistModel::class, $artists);

        $artworks = $this->createMock(ArtworkModel::class);
        $artworks->method('getAll')->willReturn([]);
        $artworks->method('countAll')->willReturn(0);
        $this->container->set(ArtworkModel::class, $artworks);

        $museum = [
            'id' => 44,
            'name' => 'Test Museum',
            'city' => 'Paris',
            'country' => 'France',
            'website' => null,
        ];
        $museums = $this->createMock(MuseumModel::class);
        $museums->method('getAll')->willReturn([]);
        $museums->method('countAll')->willReturn(0);
        $museums->method('create')->willReturn(44);
        $museums->method('getById')->with(44)->willReturn($museum);
        $this->container->set(MuseumModel::class, $museums);

        $jwt = $this->container->get(JwtService::class);
        self::assertInstanceOf(JwtService::class, $jwt);
        $this->adminToken = $jwt->issue(12, 'admin');
    }

    protected function tearDown(): void
    {
        TestApplication::reset();
        parent::tearDown();
    }

    public function testActualRoutesReturnJsonXmlAndYaml(): void
    {
        foreach ([
            ['application/json', 'application/json'],
            ['application/xml', 'application/xml'],
            ['application/yaml', 'application/yaml'],
        ] as [$accept, $expectedType]) {
            $response = $this->request('GET', '/ping', null, null, $accept);
            self::assertSame(200, $response->getStatusCode());
            self::assertSame($expectedType, $response->getHeaderLine('Content-Type'));
            self::assertNotSame('', (string) $response->getBody());
        }

        $notAcceptable = $this->request('GET', '/ping', null, null, 'text/plain');
        self::assertSame(406, $notAcceptable->getStatusCode());
        self::assertSame('application/json', $notAcceptable->getHeaderLine('Content-Type'));
        self::assertSame(
            406,
            json_decode((string) $notAcceptable->getBody(), true, 512, JSON_THROW_ON_ERROR)['status']
        );
    }

    public function testResourceCollectionsUseAllSupportedResponseFormats(): void
    {
        foreach (['/artists', '/artworks', '/museums'] as $path) {
            foreach (['application/json', 'application/xml', 'application/yaml'] as $format) {
                $response = $this->request('GET', $path, null, null, $format);
                self::assertSame(200, $response->getStatusCode(), $path . ' ' . $format);
                self::assertSame($format, $response->getHeaderLine('Content-Type'));
                self::assertNotSame('', (string) $response->getBody());
            }
        }
    }

    public function testMutationRequestAndResponseFormatsWorkAcrossRepresentations(): void
    {
        $cases = [
            [
                'application/json',
                '{"name":"Test Museum","city":"Paris","country":"France"}',
            ],
            [
                'application/xml',
                '<museum><name>Test Museum</name><city>Paris</city><country>France</country></museum>',
            ],
            [
                'application/yaml',
                "name: Test Museum\ncity: Paris\ncountry: France\n",
            ],
        ];

        foreach ($cases as [$contentType, $body]) {
            $response = $this->request('POST', '/museums', $body, $contentType, $contentType);
            self::assertSame(201, $response->getStatusCode());
            self::assertSame($contentType, $response->getHeaderLine('Content-Type'));

            if ($contentType === 'application/json') {
                $decoded = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
                self::assertSame('Test Museum', $decoded['name']);
            } elseif ($contentType === 'application/xml') {
                $document = new \DOMDocument();
                self::assertTrue($document->loadXML((string) $response->getBody()));
                self::assertSame('Test Museum', $document->getElementsByTagName('name')->item(0)?->textContent);
            } else {
                self::assertSame('Test Museum', Yaml::parse((string) $response->getBody())['name']);
            }
        }
    }

    public function testAuthenticationPostRoutesAcceptJsonXmlAndYaml(): void
    {
        $registrationBodies = [
            [
                'application/json',
                '{"full_name":"Test User","email":"user@example.test","password":"correct-horse"}',
            ],
            [
                'application/xml',
                '<account><full_name>Test User</full_name><email>user@example.test</email><password>correct-horse</password></account>',
            ],
            [
                'application/yaml',
                "full_name: Test User\nemail: user@example.test\npassword: correct-horse\n",
            ],
        ];

        foreach ($registrationBodies as [$contentType, $body]) {
            $response = $this->request('POST', '/register', $body, $contentType, $contentType);
            self::assertSame(201, $response->getStatusCode());
            self::assertSame($contentType, $response->getHeaderLine('Content-Type'));
            self::assertStringContainsString('access_token', (string) $response->getBody());
        }

        foreach ([
            ['application/json', '{"email":"user@example.test","password":"correct-horse"}'],
            ['application/xml', '<credentials><email>user@example.test</email><password>correct-horse</password></credentials>'],
            ['application/yaml', "email: user@example.test\npassword: correct-horse\n"],
        ] as [$contentType, $body]) {
            $response = $this->request('POST', '/login', $body, $contentType, $contentType);
            self::assertSame(200, $response->getStatusCode());
            self::assertSame($contentType, $response->getHeaderLine('Content-Type'));
            self::assertStringContainsString('access_token', (string) $response->getBody());
        }
    }

    public function testBodyErrorsAndValidationRemainStructured(): void
    {
        foreach ([
            ['{invalid', 'application/json', 400],
            ['<museum>', 'application/xml', 400],
            ["name: [unclosed\n", 'application/yaml', 400],
            ['', 'application/json', 400],
            ['name: Test', 'text/plain', 415],
            ['{}', 'application/json', 422],
            ['<museum/>', 'application/xml', 422],
            ['{}', 'application/yaml', 422],
        ] as [$body, $contentType, $expectedStatus]) {
            $response = $this->request(
                'POST',
                '/museums',
                $body,
                $contentType,
                'application/json'
            );
            self::assertSame($expectedStatus, $response->getStatusCode(), $contentType . ' request');
            $error = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame($expectedStatus, $error['status']);
            self::assertArrayHasKey('error', $error);
            self::assertArrayHasKey('requestId', $error);
        }
    }

    public function testStructuredErrorsUseTheNegotiatedRepresentation(): void
    {
        foreach ([
            ['application/xml', '<response>', 'application/xml'],
            ['application/yaml', "name: [unclosed\n", 'application/yaml'],
        ] as [$contentType, $body, $accept]) {
            $response = $this->request(
                'POST',
                '/museums',
                $body,
                $contentType,
                $accept
            );
            self::assertSame(400, $response->getStatusCode());
            self::assertSame($accept, $response->getHeaderLine('Content-Type'));
            self::assertStringContainsString('Bad Request', (string) $response->getBody());
        }
    }

    public function testValidationErrorsUseTheNegotiatedRepresentation(): void
    {
        foreach (['application/xml', 'application/yaml'] as $format) {
            $response = $this->request(
                'POST',
                '/museums',
                '{}',
                'application/json',
                $format
            );
            self::assertSame(422, $response->getStatusCode());
            self::assertSame($format, $response->getHeaderLine('Content-Type'));
            self::assertStringContainsString('Validation failed', (string) $response->getBody());
        }
    }

    public function testSerializationFailureReturnsGenericNegotiatedError(): void
    {
        $factory = new Psr17Factory();
        $request = $factory
            ->createServerRequest('GET', 'http://localhost/test')
            ->withHeader('Accept', 'application/xml');
        $response = $factory->createResponse()
            ->withHeader('Content-Type', 'application/json');
        $response->getBody()->write('{"bad:tag":"secret"}');

        $handler = new class($response) implements RequestHandlerInterface {
            public function __construct(private ResponseInterface $response) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };

        $middleware = new ResponseFormatMiddleware(new NullLogger(), $factory);
        $result = $middleware->process($request, $handler);

        self::assertSame(500, $result->getStatusCode());
        self::assertSame('application/xml', $result->getHeaderLine('Content-Type'));
        self::assertStringContainsString('The response could not be serialized.', (string) $result->getBody());
        self::assertStringNotContainsString('bad:tag', (string) $result->getBody());
        self::assertStringNotContainsString('secret', (string) $result->getBody());
    }

    private function request(
        string $method,
        string $path,
        ?string $body = null,
        ?string $contentType = null,
        ?string $accept = null
    ): ResponseInterface {
        $factory = new Psr17Factory();
        $request = $factory->createServerRequest($method, 'http://localhost' . $path);
        if ($contentType !== null) {
            $request = $request->withHeader('Content-Type', $contentType);
        }
        if ($accept !== null) {
            $request = $request->withHeader('Accept', $accept);
        }
        if ($body !== null) {
            $request = $request->withBody($factory->createStream($body));
        }
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $request = $request->withHeader('Authorization', 'Bearer ' . $this->adminToken);
        }

        return $this->app->handle($request);
    }
}
