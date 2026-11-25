<?php

declare(strict_types=1);

namespace Tests\Integration;

use Nyholm\Psr7\Factory\Psr17Factory;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestApplication;
use Tests\Support\TestDatabase;
use Tests\Support\TestDatabaseUnavailable;

final class ArtworkEndpointsTest extends TestCase
{
    private ?PDO $pdo = null;
    private array $artworkIds = [];
    private array $artistIds = [];
    private string $period = '';

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->pdo = TestDatabase::connection();
        } catch (TestDatabaseUnavailable $exception) {
            self::markTestSkipped($exception->getMessage());
        }

        $this->period = 'PHPUNIT_' . bin2hex(random_bytes(12));
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
        }

        parent::tearDown();
    }

    public function testGetExistingArtwork(): void
    {
        $artwork = $this->createArtwork();
        $response = $this->get('/artworks/' . $artwork['id']);
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($artwork['title'], $body['title']);
    }

    public function testGetNonexistentArtworkReturnsStructured404(): void
    {
        $id = 4294967295;
        $statement = $this->pdo->prepare('SELECT 1 FROM artworks WHERE id = ?');
        while (true) {
            $statement->execute([$id]);
            if ($statement->fetchColumn() === false) {
                break;
            }
            --$id;
        }

        $response = $this->get('/artworks/' . $id);
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(404, $body['status']);
        self::assertSame('Artwork not found', $body['message']);
    }

    public function testGetPopulatedArtworkCollection(): void
    {
        $artwork = $this->createArtwork();
        $response = $this->get('/artworks?period=' . rawurlencode($this->period));
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(1, $body['data']);
        self::assertSame($artwork['title'], $body['data'][0]['title']);
        self::assertSame(1, $body['pagination']['total_items']);
    }

    public function testGetEmptyArtworkCollection(): void
    {
        $response = $this->get('/artworks?period=' . rawurlencode($this->period));
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([], $body['data']);
        self::assertSame(0, $body['pagination']['total_items']);
    }

    private function createArtwork(): array
    {
        $artist = $this->pdo->prepare(
            'INSERT INTO artists (name, birthYear, deathYear, nationality) VALUES (?, NULL, NULL, NULL)'
        );
        $artist->execute(['PHPUnit test artist ' . $this->period]);
        $artistId = (int) $this->pdo->lastInsertId();
        $this->artistIds[] = $artistId;

        $title = 'PHPUnit artwork ' . $this->period;
        $artwork = $this->pdo->prepare(
            'INSERT INTO artworks (title, creationYear, medium, period, dimensions, artistId, museumId)
             VALUES (?, 1900, NULL, ?, NULL, ?, NULL)'
        );
        $artwork->execute([$title, $this->period, $artistId]);
        $artworkId = (int) $this->pdo->lastInsertId();
        $this->artworkIds[] = $artworkId;

        return ['id' => $artworkId, 'title' => $title];
    }

    private function get(string $path): \Psr\Http\Message\ResponseInterface
    {
        $request = (new Psr17Factory())
            ->createServerRequest('GET', 'http://localhost' . $path)
            ->withHeader('Accept', 'application/json');

        return TestApplication::get()->handle($request);
    }
}
