<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Core\AppSettings;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use LogicException;
use stdClass;

class JwtService
{
    private array $settings;

    public function __construct(AppSettings $appSettings)
    {
        $this->settings = $appSettings->get('jwt');
    }

    public function issue(int $userId, string $role): string
    {
        $now = time();

        return JWT::encode([
            'iss' => 'artgateway-api',
            'sub' => (string) $userId,
            'role' => $role,
            'iat' => $now,
            'exp' => $now + $this->getExpiry(),
        ], $this->getSecret(), $this->getAlgorithm());
    }

    public function assertConfigured(): void
    {
        $this->getSecret();
        $this->getAlgorithm();
        $this->getExpiry();
    }

    public function decode(string $token): stdClass
    {
        $claims = JWT::decode(
            $token,
            new Key($this->getSecret(), $this->getAlgorithm())
        );

        if (($claims->iss ?? null) !== 'artgateway-api') {
            throw new \UnexpectedValueException('Invalid token issuer');
        }

        return $claims;
    }

    public function getExpiry(): int
    {
        $expiry = $this->settings['expiry'] ?? null;
        if (!\is_int($expiry) || $expiry < 1) {
            throw new LogicException('JWT expiry must be a positive integer');
        }

        return $expiry;
    }

    private function getSecret(): string
    {
        $secret = $this->settings['secret'] ?? null;
        if (!\is_string($secret) || \strlen($secret) < 32) {
            throw new LogicException('Configure JWT_SECRET with at least 32 characters');
        }

        return $secret;
    }

    private function getAlgorithm(): string
    {
        $algorithm = $this->settings['algorithm'] ?? null;
        if ($algorithm !== 'HS256') {
            throw new LogicException('JWT algorithm must be HS256');
        }

        return $algorithm;
    }
}
