<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\AuthController;
use App\Domain\Models\UserModel;
use App\Exceptions\ApiException;
use App\Helpers\Core\AppSettings;
use App\Services\JwtService;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class AuthControllerTest extends TestCase
{
    public function testLoginIssuesTokenWithoutReturningCredentialMaterial(): void
    {
        $userModel = $this->createMock(UserModel::class);
        $userModel->expects(self::once())
            ->method('verifyCredentials')
            ->with('user@example.test', 'correct-password')
            ->willReturn([
                'id' => 8,
                'full_name' => 'Test User',
                'email' => 'user@example.test',
                'role' => 'user',
            ]);

        $response = $this->controller($userModel)->handleGenerateToken(
            $this->request(['email' => 'USER@example.test', 'password' => 'correct-password']),
            (new Psr17Factory())->createResponse()
        );
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Bearer', $body['token_type']);
        self::assertSame('user@example.test', $body['user']['email']);
        self::assertArrayNotHasKey('password', $body);
        self::assertArrayNotHasKey('password_hash', $body);
        self::assertStringNotContainsString(str_repeat('unit-test-secret-', 3), (string) $response->getBody());
        self::assertSame('user', $this->jwtService()->decode($body['access_token'])->role);
    }

    public function testInvalidLoginReturnsGenericUnauthorizedError(): void
    {
        $userModel = $this->createMock(UserModel::class);
        $userModel->method('verifyCredentials')->willReturn(null);

        try {
            $this->controller($userModel)->handleGenerateToken(
                $this->request(['email' => 'user@example.test', 'password' => 'wrong-password']),
                (new Psr17Factory())->createResponse()
            );
            self::fail('Expected invalid credentials to be rejected.');
        } catch (ApiException $exception) {
            self::assertSame(401, $exception->getStatusCode());
            self::assertSame('Invalid email or password', $exception->getMessage());
        }
    }

    public function testMissingLoginCredentialsReturnStructuredValidationError(): void
    {
        $userModel = $this->createMock(UserModel::class);
        $userModel->expects(self::never())->method('verifyCredentials');

        try {
            $this->controller($userModel)->handleGenerateToken(
                $this->request(['unexpected' => 'ignored']),
                (new Psr17Factory())->createResponse()
            );
            self::fail('Expected missing credentials to be rejected.');
        } catch (ApiException $exception) {
            self::assertSame(422, $exception->getStatusCode());
            self::assertArrayHasKey('email', $exception->getDetails());
            self::assertArrayHasKey('password', $exception->getDetails());
        }
    }

    public function testPasswordLongerThanBcryptInputLimitIsRejected(): void
    {
        $userModel = $this->createMock(UserModel::class);
        $userModel->expects(self::never())->method('verifyCredentials');

        try {
            $this->controller($userModel)->handleGenerateToken(
                $this->request([
                    'email' => 'user@example.test',
                    'password' => str_repeat('x', 73),
                ]),
                (new Psr17Factory())->createResponse()
            );
            self::fail('Expected passwords over bcrypt’s byte limit to be rejected.');
        } catch (ApiException $exception) {
            self::assertSame(422, $exception->getStatusCode());
            self::assertSame('password must be at most 72 bytes', $exception->getDetails()['password']);
        }
    }

    public function testRegistrationCreatesAccountAndReturnsBearerToken(): void
    {
        $userModel = $this->createMock(UserModel::class);
        $userModel->expects(self::once())
            ->method('findByEmail')
            ->with('new@example.test')
            ->willReturn(null);
        $userModel->expects(self::once())
            ->method('createUser')
            ->with([
                'full_name' => 'New User',
                'email' => 'new@example.test',
                'password' => 'long-enough-password',
            ])
            ->willReturn(42);
        $userModel->expects(self::once())
            ->method('findById')
            ->with(42)
            ->willReturn([
                'id' => 42,
                'full_name' => 'New User',
                'email' => 'new@example.test',
                'role' => 'user',
            ]);

        $response = $this->controller($userModel)->handleRegister(
            $this->request([
                'full_name' => 'New User',
                'email' => 'NEW@example.test',
                'password' => 'long-enough-password',
                'role' => 'admin',
            ]),
            (new Psr17Factory())->createResponse()
        );
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('Bearer', $body['token_type']);
        self::assertSame(42, $body['user']['id']);
        self::assertArrayNotHasKey('password', $body);
        self::assertArrayNotHasKey('password_hash', $body);
    }

    private function controller(UserModel $userModel): AuthController
    {
        return new AuthController($userModel, $this->jwtService());
    }

    private function jwtService(): JwtService
    {
        return new JwtService(new AppSettings([
            'jwt' => [
                'secret' => str_repeat('unit-test-secret-', 3),
                'algorithm' => 'HS256',
                'expiry' => 3600,
            ],
        ]));
    }

    private function request(array $data): \Psr\Http\Message\ServerRequestInterface
    {
        return (new Psr17Factory())
            ->createServerRequest('POST', 'http://localhost/login')
            ->withParsedBody($data);
    }
}
