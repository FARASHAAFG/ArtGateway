<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\JwtService;
use Nyholm\Psr7\Factory\Psr17Factory;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestApplication;
use Tests\Support\TestDatabase;
use Tests\Support\TestDatabaseUnavailable;

final class MuseumEndpointsTest extends TestCase
{
    private ?PDO $pdo = null;
    private array $artworkIds = [];
    private array $artistIds = [];
    private array $museumIds = [];
    private ?int $userId = null;
    private string $adminToken = '';

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->pdo = TestDatabase::connection();
        } catch (TestDatabaseUnavailable $exception) {
            self::markTestSkipped($exception->getMessage());
        }

        $suffix = bin2hex(random_bytes(12));
        $user = $this->pdo->prepare(
            'INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)'
        );
        $user->execute([
            'Museum endpoint test',
            'museum-test-' . $suffix . '@example.test',
            password_hash('not-used-password', PASSWORD_DEFAULT),
            'admin',
        ]);
        $this->userId = (int) $this->pdo->lastInsertId();

        $jwt = TestApplication::get()->getContainer()->get(JwtService::class);
        $this->adminToken = $jwt->issue($this->userId, 'admin');

        $artist = $this->pdo->prepare(
            'INSERT INTO artists (name, birthYear, deathYear, nationality) VALUES (?, NULL, NULL, NULL)'
        );
        $artist->execute(['Museum endpoint test artist ' . $suffix]);
        $artistId = (int) $this->pdo->lastInsertId();
        $this->artistIds[] = $artistId;

        $museum = $this->pdo->prepare(
            'INSERT INTO museums (name, city, country, website) VALUES (?, ?, ?, NULL)'
        );
        $museum->execute(['Museum endpoint test ' . $suffix, 'Test City', 'Test Country']);
        $museumId = (int) $this->pdo->lastInsertId();
        $this->museumIds[] = $museumId;

        $artwork = $this->pdo->prepare(
            'INSERT INTO artworks (title, creationYear, medium, period, dimensions, artistId, museumId)
             VALUES (?, 1900, NULL, NULL, NULL, ?, ?)'
        );
        foreach (['First', 'Second'] as $name) {
            $artwork->execute(['Museum endpoint ' . $name . ' ' . $suffix, $artistId, $museumId]);
            $this->artworkIds[] = (int) $this->pdo->lastInsertId();
        }
    }

    protected function tearDown(): void
    {
        if ($this->pdo !== null) {
            foreach ($this->artworkIds as $id) {
                $statement = $this->pdo->prepare('DELETE FROM artworks WHERE id = ?');
                $statement->execute([$id]);
            }
            foreach ($this->artistIds as $id) {
                $statement = $this->pdo->prepare('DELETE FROM artists WHERE id = ?');
                $statement->execute([$id]);
            }
            foreach ($this->museumIds as $id) {
                $statement = $this->pdo->prepare('DELETE FROM museums WHERE id = ?');
                $statement->execute([$id]);
            }
            if ($this->userId !== null) {
                $statement = $this->pdo->prepare('DELETE FROM users WHERE id = ?');
                $statement->execute([$this->userId]);
            }
        }

        TestApplication::reset();
        parent::tearDown();
    }

    public function testMuseumCrudAndPaginatedRelatedResources(): void
    {
        $museumId = $this->museumIds[0];
        $artistId = $this->artistIds[0];

        $museums = $this->get('/museums?limit=1');
        $museumPage = $this->decode($museums);
        self::assertSame(200, $museums->getStatusCode());
        self::assertLessThanOrEqual(1, count($museumPage['data']));
        self::assertGreaterThanOrEqual(1, $museumPage['pagination']['total_items']);
        self::assertSame(1, $museumPage['pagination']['per_page']);

        $artistArtworks = $this->get('/artists/' . $artistId . '/artworks?limit=1&page=2');
        $artistPage = $this->decode($artistArtworks);
        self::assertSame(200, $artistArtworks->getStatusCode());
        self::assertCount(1, $artistPage['data']);
        self::assertSame(2, $artistPage['pagination']['total_items']);
        self::assertSame(2, $artistPage['pagination']['current_page']);

        $museumArtworks = $this->get('/museums/' . $museumId . '/artworks?limit=1');
        $museumArtworkPage = $this->decode($museumArtworks);
        self::assertSame(200, $museumArtworks->getStatusCode());
        self::assertCount(1, $museumArtworkPage['data']);
        self::assertSame(2, $museumArtworkPage['pagination']['total_items']);

        $created = $this->send('POST', '/museums', [
            'name' => 'Created endpoint test museum',
            'city' => 'Paris',
            'country' => 'France',
            'website' => 'https://example.test/museum',
        ], $this->adminToken);
        $createdMuseum = $this->decode($created);
        self::assertSame(201, $created->getStatusCode());
        $createdId = (int) $createdMuseum['id'];
        $this->museumIds[] = $createdId;
        self::assertStringEndsWith('/museums/' . $createdId, $created->getHeaderLine('Location'));

        $persisted = $this->pdo->prepare('SELECT name FROM museums WHERE id = ?');
        $persisted->execute([$createdId]);
        self::assertSame('Created endpoint test museum', $persisted->fetchColumn());

        $updated = $this->send('PUT', '/museums/' . $createdId, [
            'name' => 'Updated endpoint test museum',
            'city' => 'Lyon',
            'country' => 'France',
            'website' => null,
        ], $this->adminToken);
        self::assertSame(200, $updated->getStatusCode());
        self::assertSame('Updated endpoint test museum', $this->decode($updated)['name']);

        $invalid = $this->send('POST', '/museums', ['name' => 'Missing fields'], $this->adminToken);
        self::assertSame(422, $invalid->getStatusCode());

        $deleted = $this->send('DELETE', '/museums/' . $createdId, [], $this->adminToken);
        self::assertSame(204, $deleted->getStatusCode());
        $persisted->execute([$createdId]);
        self::assertFalse($persisted->fetchColumn());

        $this->museumIds = array_values(array_diff($this->museumIds, [$createdId]));
        $deletedRelatedMuseum = $this->send('DELETE', '/museums/' . $museumId, [], $this->adminToken);
        self::assertSame(204, $deletedRelatedMuseum->getStatusCode());

        $link = $this->pdo->prepare('SELECT museumId FROM artworks WHERE id = ?');
        $link->execute([$this->artworkIds[0]]);
        self::assertNull($link->fetchColumn());

        $missingRelated = $this->get('/museums/' . $museumId . '/artworks');
        self::assertSame(404, $missingRelated->getStatusCode());
        self::assertSame('Museum not found', $this->decode($missingRelated)['message']);
    }

    private function get(string $path): \Psr\Http\Message\ResponseInterface
    {
        return $this->send('GET', $path);
    }

    private function send(
        string $method,
        string $path,
        array $body = [],
        ?string $token = null
    ): \Psr\Http\Message\ResponseInterface {
        $request = (new Psr17Factory())
            ->createServerRequest($method, 'http://localhost' . $path)
            ->withHeader('Accept', 'application/json');

        if (in_array($method, ['POST', 'PUT'], true)) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withParsedBody($body);
        }
        if ($token !== null) {
            $request = $request->withHeader('Authorization', 'Bearer ' . $token);
        }

        return TestApplication::get()->handle($request);
    }

    private function decode(\Psr\Http\Message\ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
