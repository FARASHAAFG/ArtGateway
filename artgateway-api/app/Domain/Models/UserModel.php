<?php

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
        // TODO: Hash the password before storing it
        $hash = password_hash($data['password'], PASSWORD_DEFAULT);
        // TODO: Insert the user into the database
        $this->execute(
            'INSERT INTO `users` (full_name, email, password_hash, created_at) VALUES (:full_name, :email, :password_hash, :created_at)',
            [
                ':full_name' => $data['full_name'],
                ':email' => $data['email'],
                ':password_hash' => $hash,
                ':created_at' => date('Y-m-d H:i:s'),
            ]
        );
        // TODO: Return the new user's ID
        return intval($this->lastInsertId());
    }
    public function findByEmail(string $email): ?array
    {
        // TODO: Look up a single user by their email address
        //       Return the user data or null if not found
        $sql = 'SELECT * FROM users WHERE email = :email';
        $result = $this->selectOne($sql, [':email' => $email]);

        if (is_bool($result)) {
            return null;
        } else {
            return $result;
        }
    }

    public function findByUsername(string $username): ?array
    {
        // TODO: Look up a single user by their username
        //       Return the user data or null if not found
        $sql = 'SELECT * FROM users WHERE username = :username';
        $result = $this->selectOne($sql, [':username' => $username]);

        if (is_bool($result)) {
            return null;
        } else {
            return $result;
        }
    }

    public function emailExists(string $email): bool
    {
        // TODO: Check whether a user with this email already exists in the database
        return !is_bool($this->selectOne("SELECT 'id' FROM users WHERE email = ?", [$email]));
    }

    public function usernameExists(string $username): bool
    {
        // TODO: Check whether a user with this username already exists in the database
        return !is_bool($this->selectOne("SELECT 'id' FROM users WHERE username = ?", [$username]));
    }

    //* Authentication-related methods
    /**
     * Verify user credentials by email/username and password.
     *
     * @param string $identifier Email or username
     * @param string $password Plain-text password to verify
     * @return array|null User data if credentials are valid, null otherwise
     */
    public function verifyCredentials(string $identifier, string $password): ?array
    {
        // TODO: Look up the user by their identifier (try email first, then username).
        //       If found, verify the password against the stored hash.
        //       Return the user data on success or null on failure.
        $user_data = $this->findByEmail($identifier);

        if (is_null($user_data)) {
            $user_data = $this->findByUsername($identifier);
        }
        if (is_null($user_data)) {
            return null;
        }
        if (password_verify($password, $user_data['password_hash'])) {
            return $user_data;
        }
        return null;
    }
}