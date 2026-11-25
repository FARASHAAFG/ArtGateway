<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;
use Tests\Support\TestDatabaseUnavailable;

final class DatabaseIntegrityTest extends TestCase
{
    private ?PDO $pdo = null;
    private array $userEmails = [];
    private array $artistIds = [];
    private array $artworkIds = [];
    private array $tagIds = [];

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
            foreach ($this->artworkIds as $id) {
                $statement = $this->pdo->prepare('DELETE FROM artworks WHERE id = ?');
                $statement->execute([$id]);
            }

            foreach ($this->tagIds as $id) {
                $statement = $this->pdo->prepare('DELETE FROM tags WHERE id = ?');
                $statement->execute([$id]);
            }

            foreach ($this->artistIds as $id) {
                $statement = $this->pdo->prepare('DELETE FROM artists WHERE id = ?');
                $statement->execute([$id]);
            }

            foreach ($this->userEmails as $email) {
                $statement = $this->pdo->prepare('DELETE FROM users WHERE email = ?');
                $statement->execute([$email]);
            }
        }

        parent::tearDown();
    }

    public function testUniqueEmailConstraintRejectsDuplicateWithoutAddingAnotherUser(): void
    {
        $email = 'integrity-' . bin2hex(random_bytes(12)) . '@example.test';
        $this->userEmails[] = $email;
        $this->insertUser($email);

        try {
            $this->insertUser($email);
            self::fail('The database accepted a duplicate user email.');
        } catch (PDOException $exception) {
            self::assertSame('23000', (string) $exception->getCode());
        }

        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $statement->execute([$email]);
        self::assertSame(1, (int) $statement->fetchColumn());
    }

    public function testArtworkForeignKeyRejectsMissingArtistWithoutPersistingArtwork(): void
    {
        $missingArtistId = $this->unusedArtistId();
        $title = 'Integrity test artwork ' . bin2hex(random_bytes(12));

        try {
            $this->insertArtwork($title, $missingArtistId);
            self::fail('The database accepted an artwork with a missing artist.');
        } catch (PDOException $exception) {
            self::assertSame('23000', (string) $exception->getCode());
        }

        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM artworks WHERE title = ?');
        $statement->execute([$title]);
        self::assertSame(0, (int) $statement->fetchColumn());
    }

    public function testArtistWithArtworkCannotBeDeletedAndBothRowsRemain(): void
    {
        $artistId = $this->createArtist();
        $artworkId = $this->createArtwork(
            'Integrity test artwork ' . bin2hex(random_bytes(12)),
            $artistId
        );

        try {
            $statement = $this->pdo->prepare('DELETE FROM artists WHERE id = ?');
            $statement->execute([$artistId]);
            self::fail('The database deleted an artist referenced by an artwork.');
        } catch (PDOException $exception) {
            self::assertSame('23000', (string) $exception->getCode());
        }

        $artistExists = $this->pdo->prepare('SELECT COUNT(*) FROM artists WHERE id = ?');
        $artistExists->execute([$artistId]);
        self::assertSame(1, (int) $artistExists->fetchColumn());

        $artworkExists = $this->pdo->prepare('SELECT COUNT(*) FROM artworks WHERE id = ?');
        $artworkExists->execute([$artworkId]);
        self::assertSame(1, (int) $artworkExists->fetchColumn());
    }

    public function testArtworkTagCompositeKeyRejectsDuplicateAssociation(): void
    {
        $artistId = $this->createArtist();
        $artworkId = $this->createArtwork(
            'Integrity test artwork ' . bin2hex(random_bytes(12)),
            $artistId
        );
        $tagId = $this->createTag('integrity-' . bin2hex(random_bytes(12)));

        $insert = $this->pdo->prepare(
            'INSERT INTO artwork_tags (artworkId, tagId) VALUES (?, ?)'
        );
        $insert->execute([$artworkId, $tagId]);

        try {
            $insert->execute([$artworkId, $tagId]);
            self::fail('The database accepted a duplicate artwork-tag association.');
        } catch (PDOException $exception) {
            self::assertSame('23000', (string) $exception->getCode());
        }

        $count = $this->pdo->prepare(
            'SELECT COUNT(*) FROM artwork_tags WHERE artworkId = ? AND tagId = ?'
        );
        $count->execute([$artworkId, $tagId]);
        self::assertSame(1, (int) $count->fetchColumn());
    }

    private function insertUser(string $email): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)'
        );
        $statement->execute([
            'Integrity test user',
            $email,
            password_hash('integrity-test-password', PASSWORD_DEFAULT),
        ]);
    }

    private function createArtist(): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO artists (name) VALUES (?)'
        );
        $statement->execute(['Integrity test artist ' . bin2hex(random_bytes(12))]);
        $id = (int) $this->pdo->lastInsertId();
        $this->artistIds[] = $id;

        return $id;
    }

    private function createArtwork(string $title, int $artistId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO artworks (title, creationYear, artistId) VALUES (?, 1900, ?)'
        );
        $statement->execute([$title, $artistId]);
        $id = (int) $this->pdo->lastInsertId();
        $this->artworkIds[] = $id;

        return $id;
    }

    private function createTag(string $name): int
    {
        $statement = $this->pdo->prepare('INSERT INTO tags (name) VALUES (?)');
        $statement->execute([$name]);
        $id = (int) $this->pdo->lastInsertId();
        $this->tagIds[] = $id;

        return $id;
    }

    private function unusedArtistId(): int
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM artists WHERE id = ?');

        do {
            $id = random_int(1_000_000_000, 4_000_000_000);
            $statement->execute([$id]);
        } while ($statement->fetchColumn() !== false);

        return $id;
    }
}
