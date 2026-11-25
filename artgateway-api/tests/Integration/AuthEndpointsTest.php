<?php

declare(strict_types=1);

namespace Tests\Integration;

use Firebase\JWT\JWT;
use Nyholm\Psr7\Factory\Psr17Factory;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestApplication;
use Tests\Support\TestDatabase;
use Tests\Support\TestDatabaseUnavailable;

final class AuthEndpointsTest extends TestCase
{
    private ?PDO $pdo = null;
    private array $emails = [];

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->pdo = TestDatabase::connection();
        } catch (TestDatabaseUnavailable $exception) {
            self::markTestSkipped($exception->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if ($this->pdo !== null) {
            foreach ($this->emails as $email) {
                $statement = $this->pdo->prepare('DELETE FROM users WHERE email = ?');
                $statement->execute([$email]);
            }
        }

        parent::tearDown();
    }

    public function testRegistrationPersistsHashedPasswordAndIssuesToken(): void
    {
        $email = $this->uniqueEmail();
        $response = $this->post('/register', [
            'full_name' => 'Integration User',
            'email' => strtoupper($email),
            'password' => 'integration-password-1',
        ]);
        $body = $this->json($response);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('user', $body['user']['role']);
        self::assertArrayNotHasKey('password', $body);
        self::assertArrayNotHasKey('password_hash', $body);

        $statement = $this->pdo->prepare('SELECT password_hash FROM users WHERE email = ?');
        $statement->execute([$email]);
        $passwordHash = $statement->fetchColumn();

        self::assertIsString($passwordHash);
        self::assertNotSame('integration-password-1', $passwordHash);
        self::assertTrue(password_verify('integration-password-1', $passwordHash));

        $me = $this->get('/me', $body['access_token']);
        self::assertSame(200, $me->getStatusCode());
        self::assertSame($email, $this->json($me)['user']['email']);
    }

    public function testDuplicateRegistrationReturnsConflictWithoutAddingAnotherUser(): void
    {
        $email = $this->uniqueEmail();
        $payload = [
            'full_name' => 'Integration User',
            'email' => $email,
            'password' => 'integration-password-1',
        ];

        self::assertSame(201, $this->post('/register', $payload)->getStatusCode());

        $response = $this->post('/register', $payload);
        $body = $this->json($response);

        self::assertSame(409, $response->getStatusCode());
        self::assertSame(409, $body['status']);
        self::assertSame('Conflict', $body['error']);

        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $statement->execute([$email]);
        self::assertSame(1, (int) $statement->fetchColumn());
    }

    public function testLoginAcceptsValidCredentialsAndRejectsInvalidCredentials(): void
    {
        $email = $this->uniqueEmail();
        $this->insertUser($email, 'correct-integration-password');

        $validResponse = $this->post('/login', [
            'email' => $email,
            'password' => 'correct-integration-password',
        ]);
        $validBody = $this->json($validResponse);

        self::assertSame(200, $validResponse->getStatusCode());
        self::assertArrayHasKey('access_token', $validBody);
        self::assertArrayNotHasKey('password_hash', $validBody);

        $invalidResponse = $this->post('/login', [
            'email' => $email,
            'password' => 'incorrect-integration-password',
        ]);
        $invalidBody = $this->json($invalidResponse);

        self::assertSame(401, $invalidResponse->getStatusCode());
        self::assertSame(401, $invalidBody['status']);
        self::assertSame('Invalid email or password', $invalidBody['message']);
        self::assertStringNotContainsString('incorrect-integration-password', (string) $invalidResponse->getBody());
    }

    public function testMissingCredentialsReturnStructuredValidationError(): void
    {
        $response = $this->post('/login', ['unexpected' => 'ignored']);
        $body = $this->json($response);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('Validation failed', $body['message']);
        self::assertArrayHasKey('email', $body['details']);
        self::assertArrayHasKey('password', $body['details']);
    }

    public function testProtectedMeRejectsMissingInvalidExpiredAndUnknownUserTokens(): void
    {
        $expiredToken = JWT::encode([
            'iss' => 'artgateway-api',
            'sub' => '1',
            'role' => 'user',
            'exp' => time() - 10,
        ], $this->testSecret(), 'HS256');
        $unknownUserToken = JWT::encode([
            'iss' => 'artgateway-api',
            'sub' => '4294967295',
            'role' => 'user',
            'exp' => time() + 60,
        ], $this->testSecret(), 'HS256');

        foreach ([null, 'not-a-jwt', $expiredToken, $unknownUserToken] as $token) {
            $response = $this->get('/me', $token);
            $body = $this->json($response);

            self::assertSame(401, $response->getStatusCode());
            self::assertSame(401, $body['status']);
            self::assertSame('Unauthorized', $body['error']);
            self::assertArrayHasKey('requestId', $body);
            self::assertStringNotContainsString($this->testSecret(), (string) $response->getBody());
        }
    }

    public function testPublicGetRouteRemainsAvailableWithoutAuthentication(): void
    {
        $response = $this->get('/ping');

        self::assertSame(200, $response->getStatusCode());
        self::assertArrayHasKey('greetings', $this->json($response));
    }

    private function insertUser(string $email, string $password): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)'
        );
        $statement->execute([
            'Integration User',
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            'user',
        ]);
    }

    private function uniqueEmail(): string
    {
        $email = 'auth-' . bin2hex(random_bytes(12)) . '@example.test';
        $this->emails[] = $email;
        return $email;
    }

    private function post(string $path, array $body): \Psr\Http\Message\ResponseInterface
    {
        $request = (new Psr17Factory())
            ->createServerRequest('POST', 'http://localhost' . $path)
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody($body);

        return TestApplication::get()->handle($request);
    }

    private function get(
        string $path,
        ?string $token = null
    ): \Psr\Http\Message\ResponseInterface {
        $request = (new Psr17Factory())
            ->createServerRequest('GET', 'http://localhost' . $path);

        if ($token !== null) {
            $request = $request->withHeader('Authorization', 'Bearer ' . $token);
        }

        return TestApplication::get()->handle($request);
    }

    private function json(\Psr\Http\Message\ResponseInterface $response): array
    {
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function testSecret(): string
    {
        return str_repeat('test-only-secret-', 3);
    }
}
