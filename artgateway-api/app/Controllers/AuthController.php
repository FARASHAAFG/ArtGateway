<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Models\UserModel;
use App\Exceptions\ApiException;
use App\Services\JwtService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController extends BaseController
{
    private const MAX_PASSWORD_BYTES = 72;

    public function __construct(
        private UserModel $userModel,
        private JwtService $jwtService
    ) {}

    public function handleRegister(Request $request, Response $response): Response
    {
        $this->jwtService->assertConfigured();
        $data = $this->getRequestBody($request);
        $errors = [];

        $fullName = $this->checkString($data, 'full_name', true, 100, $errors);
        $email = $this->checkString($data, 'email', true, 255, $errors);
        $password = $this->passwordValue($data, $errors);

        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'email must be a valid email address';
        }
        if ($password !== null && \strlen($password) < 8) {
            $errors['password'] = 'password must be at least 8 characters';
        }

        $this->throwIfInvalid($errors);

        $email = strtolower($email);
        if ($this->userModel->findByEmail($email) !== null) {
            throw new ApiException(409, 'An account with this email already exists');
        }

        $userId = $this->userModel->createUser([
            'full_name' => $fullName,
            'email' => $email,
            'password' => $password,
        ]);
        $user = $this->userModel->findById($userId);

        if ($user === null) {
            throw new \RuntimeException('The newly created user could not be loaded');
        }

        return $this->renderJson($response, $this->tokenResponse($user), 201);
    }

    public function handleGenerateToken(Request $request, Response $response): Response
    {
        $this->jwtService->assertConfigured();
        $data = $this->getRequestBody($request);
        $errors = [];

        $email = $this->checkString($data, 'email', true, 255, $errors);
        $password = $this->passwordValue($data, $errors);
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'email must be a valid email address';
        }
        $this->throwIfInvalid($errors);

        $user = $this->userModel->verifyCredentials(strtolower($email), $password);
        if ($user === null) {
            throw new ApiException(401, 'Invalid email or password');
        }

        return $this->renderJson($response, $this->tokenResponse($user));
    }

    public function handleGetCurrentUser(Request $request, Response $response): Response
    {
        return $this->renderJson($response, ['user' => $request->getAttribute('auth_user')]);
    }

    private function tokenResponse(array $user): array
    {
        return [
            'access_token' => $this->jwtService->issue((int) $user['id'], (string) $user['role']),
            'token_type' => 'Bearer',
            'expires_in' => $this->jwtService->getExpiry(),
            'user' => [
                'id' => (int) $user['id'],
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'role' => $user['role'],
            ],
        ];
    }

    private function passwordValue(array $data, array &$errors): ?string
    {
        if (!isset($data['password'])) {
            $errors['password'] = 'password is required';
            return null;
        }
        if (!\is_string($data['password'])) {
            $errors['password'] = 'password must be text';
            return null;
        }
        if ($data['password'] === '') {
            $errors['password'] = 'password cannot be empty';
            return null;
        }
        if (\strlen($data['password']) > self::MAX_PASSWORD_BYTES) {
            $errors['password'] = sprintf(
                'password must be at most %d bytes',
                self::MAX_PASSWORD_BYTES
            );
            return null;
        }

        return $data['password'];
    }
}
