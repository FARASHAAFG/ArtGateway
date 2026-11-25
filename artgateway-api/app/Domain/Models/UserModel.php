<?php

declare(strict_types=1);

namespace App\Domain\Models;

use App\Helpers\Core\PDOService;

class UserModel extends BaseModel
{
    public function __construct(PDOService $db_service)
    {
        parent::__construct($db_service);
    }

    public function createUser(array $data): int
    {
        $hash = password_hash($data['password'], PASSWORD_DEFAULT);
        return (int) $this->insert('users', [
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'password_hash' => $hash,
        ]);
    }

    public function findByEmail(string $email): ?array
    {
        $result = $this->fetchSingle('SELECT * FROM users WHERE email = ?', [$email]);
        return \is_array($result) ? $result : null;
    }

    public function findById(int $id): ?array
    {
        $result = $this->fetchSingle(
            'SELECT id, full_name, email, role, created_at FROM users WHERE id = ?',
            [$id]
        );
        return \is_array($result) ? $result : null;
    }

    public function verifyCredentials(string $email, string $password): ?array
    {
        $user = $this->findByEmail($email);
        if ($user === null || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        unset($user['password_hash']);
        return $user;
    }
}