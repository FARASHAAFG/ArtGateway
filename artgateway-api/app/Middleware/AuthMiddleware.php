<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Domain\Models\UserModel;
use App\Exceptions\ApiException;
use App\Services\JwtService;
use Closure;
use DomainException;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use UnexpectedValueException;

class AuthMiddleware
{
    private UserModel|Closure $userModel;

    public function __construct(
        private JwtService $jwtService,
        UserModel|Closure $userModel,
        private ?string $requiredRole = null
    ) {
        $this->userModel = $userModel;
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $header = $request->getHeaderLine('Authorization');
        if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
            throw new ApiException(401, 'A valid bearer token is required');
        }

        try {
            $claims = $this->jwtService->decode($matches[1]);
        } catch (DomainException | InvalidArgumentException | UnexpectedValueException $exception) {
            throw new ApiException(401, 'The bearer token is invalid or expired');
        }

        $subject = $claims->sub ?? null;
        if (!\is_string($subject) || !\ctype_digit($subject) || (int) $subject < 1) {
            throw new ApiException(401, 'The bearer token is invalid or expired');
        }
        $tokenRole = $claims->role ?? null;
        if (!\is_string($tokenRole) || !\in_array($tokenRole, ['user', 'admin'], true)) {
            throw new ApiException(401, 'The bearer token is invalid or expired');
        }

        $userModel = $this->userModel instanceof Closure
            ? ($this->userModel)()
            : $this->userModel;
        $user = $userModel->findById((int) $subject);
        if ($user === null) {
            throw new ApiException(401, 'The bearer token is invalid or expired');
        }

        if (!\in_array($user['role'], ['user', 'admin'], true) || $user['role'] !== $tokenRole) {
            throw new ApiException(401, 'The bearer token is invalid or expired');
        }
        if ($this->requiredRole !== null && $user['role'] !== $this->requiredRole) {
            throw new ApiException(403, 'Administrator access is required');
        }

        return $handler->handle($request->withAttribute('auth_user', [
            'id' => (int) $user['id'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ]));
    }
}
