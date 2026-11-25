<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Models\UserModel;
use App\Exceptions\ApiException;
use App\Helpers\Core\AppSettings;
use App\Middleware\AuthMiddleware;
use App\Services\JwtService;
use Firebase\JWT\JWT;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class AuthenticationTest extends TestCase
{
    public function testJwtRoundTripsIssuedClaims(): void
    {
        $jwt = $this->jwtService();
        $claims = $jwt->decode($jwt->issue(73, 'user'));

        self::assertSame('artgateway-api', $claims->iss);
        self::assertSame('73', $claims->sub);
        self::assertSame('user', $claims->role);
    }

    public function testBearerTokenAddsCurrentUserToRequest(): void
    {
        $jwt = $this->jwtService();
        $userModel = $this->createMock(UserModel::class);
        $userModel->expects(self::once())
            ->method('findById')
            ->with(73)
            ->willReturn([
                'id' => 73,
                'full_name' => 'Test User',
                'email' => 'user@example.test',
                'role' => 'user',
            ]);
        $handler = new CapturingRequestHandler();
        $request = (new Psr17Factory())
            ->createServerRequest('GET', 'http://localhost/me')
            ->withHeader('Authorization', 'Bearer ' . $jwt->issue(73, 'user'))
            ->withHeader('X-User-Id', '999')
            ->withHeader('X-User-Role', 'admin');

        (new AuthMiddleware($jwt, $userModel))($request, $handler);

        self::assertSame(73, $handler->request?->getAttribute('auth_user')['id']);
        self::assertSame('user', $handler->request?->getAttribute('auth_user')['role']);
    }

    public function testUserTokenCannotPassAdminAuthorization(): void
    {
        $jwt = $this->jwtService();
        $userModel = $this->createMock(UserModel::class);
        $userModel->method('findById')->willReturn([
            'id' => 73,
            'full_name' => 'Test User',
            'email' => 'user@example.test',
            'role' => 'user',
        ]);
        $request = (new Psr17Factory())
            ->createServerRequest('GET', 'http://localhost/admin')
            ->withHeader('Authorization', 'Bearer ' . $jwt->issue(73, 'user'));

        try {
            (new AuthMiddleware($jwt, $userModel, 'admin'))(
                $request,
                new CapturingRequestHandler()
            );
            self::fail('Expected admin authorization to reject a user token.');
        } catch (ApiException $exception) {
            self::assertSame(403, $exception->getStatusCode());
        }
    }

    public function testMissingBearerTokenIsRejectedWithoutDatabaseLookup(): void
    {
        $userModel = $this->createMock(UserModel::class);
        $userModel->expects(self::never())->method('findById');
        $request = (new Psr17Factory())->createServerRequest('GET', 'http://localhost/me');

        $this->assertAuthenticationFailure(
            fn () => (new AuthMiddleware($this->jwtService(), $userModel))(
                $request,
                new CapturingRequestHandler()
            ),
            401
        );
    }

    public function testMalformedAndExpiredTokensAreRejected(): void
    {
        $jwt = $this->jwtService();
        $userModel = $this->createMock(UserModel::class);
        $userModel->expects(self::never())->method('findById');
        $middleware = new AuthMiddleware($jwt, $userModel);
        $handler = new CapturingRequestHandler();
        $factory = new Psr17Factory();

        foreach ([
            'not-a-jwt',
            JWT::encode([
                'iss' => 'artgateway-api',
                'sub' => '73',
                'role' => 'user',
                'exp' => time() + 60,
            ], str_repeat('different-test-secret-', 3), 'HS256'),
            JWT::encode([
                'iss' => 'artgateway-api',
                'sub' => '73',
                'role' => 'user',
                'exp' => time() - 10,
            ], str_repeat('unit-test-secret-', 3), 'HS256'),
        ] as $token) {
            $request = $factory
                ->createServerRequest('GET', 'http://localhost/me')
                ->withHeader('Authorization', 'Bearer ' . $token);

            $this->assertAuthenticationFailure(
                fn () => $middleware($request, $handler),
                401
            );
        }
    }

    public function testValidTokenForMissingAccountIsRejected(): void
    {
        $jwt = $this->jwtService();
        $userModel = $this->createMock(UserModel::class);
        $userModel->expects(self::once())
            ->method('findById')
            ->with(73)
            ->willReturn(null);
        $request = (new Psr17Factory())
            ->createServerRequest('GET', 'http://localhost/me')
            ->withHeader('Authorization', 'Bearer ' . $jwt->issue(73, 'user'));

        $this->assertAuthenticationFailure(
            fn () => (new AuthMiddleware($jwt, $userModel))(
                $request,
                new CapturingRequestHandler()
            ),
            401
        );
    }

    public function testAdminTokenPassesAdminAuthorization(): void
    {
        $jwt = $this->jwtService();
        $userModel = $this->createMock(UserModel::class);
        $userModel->expects(self::once())
            ->method('findById')
            ->with(73)
            ->willReturn([
                'id' => 73,
                'full_name' => 'Test Admin',
                'email' => 'admin@example.test',
                'role' => 'admin',
            ]);
        $handler = new CapturingRequestHandler();
        $request = (new Psr17Factory())
            ->createServerRequest('POST', 'http://localhost/artworks')
            ->withHeader('Authorization', 'Bearer ' . $jwt->issue(73, 'admin'));

        (new AuthMiddleware($jwt, $userModel, 'admin'))($request, $handler);

        self::assertSame('admin', $handler->request?->getAttribute('auth_user')['role']);
    }

    public function testAdminClaimCannotOverrideStoredUserRole(): void
    {
        $jwt = $this->jwtService();
        $userModel = $this->createMock(UserModel::class);
        $userModel->method('findById')->willReturn([
            'id' => 73,
            'full_name' => 'Test User',
            'email' => 'user@example.test',
            'role' => 'user',
        ]);
        $request = (new Psr17Factory())
            ->createServerRequest('POST', 'http://localhost/artworks')
            ->withHeader('Authorization', 'Bearer ' . $jwt->issue(73, 'admin'));

        $this->assertAuthenticationFailure(
            fn () => (new AuthMiddleware($jwt, $userModel, 'admin'))(
                $request,
                new CapturingRequestHandler()
            ),
            401
        );
    }

    public function testShortJwtSecretCannotBeUsed(): void
    {
        $jwt = new JwtService(new AppSettings([
            'jwt' => [
                'secret' => 'too-short',
                'algorithm' => 'HS256',
                'expiry' => 3600,
            ],
        ]));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('at least 32 characters');
        $jwt->assertConfigured();
    }

    private function assertAuthenticationFailure(callable $operation, int $status): void
    {
        try {
            $operation();
            self::fail('Expected authentication to reject the request.');
        } catch (ApiException $exception) {
            self::assertSame($status, $exception->getStatusCode());
        }
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
}

final class CapturingRequestHandler implements RequestHandlerInterface
{
    public ?ServerRequestInterface $request = null;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->request = $request;
        return (new Psr17Factory())->createResponse();
    }
}
