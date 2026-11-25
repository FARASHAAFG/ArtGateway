<?php

declare(strict_types=1);

namespace App\Domain\Models;

use App\Helpers\Core\PDOService;

class MuseumModel extends BaseModel
{
    public function __construct(PDOService $pdo)
    {
        parent::__construct($pdo);
    }

    public function getAll(int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT id, name, city, country, website
                FROM museums
                ORDER BY name, id
                LIMIT :limit OFFSET :offset";

        return $this->fetchAll($sql, [
            'limit' => max(1, min($limit, 100)),
            'offset' => max(0, $offset),
        ]);
    }

    public function countAll(): int
    {
        $row = $this->fetchSingle('SELECT COUNT(*) AS total_count FROM museums');
        return (int) ($row['total_count'] ?? 0);
    }

    public function getById(int $id)
    {
        $sql = "SELECT id, name, city, country, website
                FROM museums
                WHERE id = :id";

        return $this->fetchSingle($sql, array('id' => $id));
    }

    public function getArtworksByMuseumId(int $museumId, ?int $limit = null, ?int $offset = null): array
    {
        $sql = "SELECT id, title, creationYear, medium, period
                FROM artworks
                WHERE museumId = :museumId
                ORDER BY creationYear, id";

        $params = ['museumId' => $museumId];
        if ($limit !== null && $offset !== null) {
            $sql .= " LIMIT :limit OFFSET :offset";
            $params['limit'] = max(1, min($limit, 100));
            $params['offset'] = max(0, $offset);
        }

        return $this->fetchAll($sql, $params);
    }

    public function countArtworksByMuseumId(int $museumId): int
    {
        $row = $this->fetchSingle(
            'SELECT COUNT(*) AS total_count FROM artworks WHERE museumId = :museumId',
            ['museumId' => $museumId]
        );

        return (int) ($row['total_count'] ?? 0);
    }

    public function create(array $data): int
    {
        return (int) $this->insert('museums', [
            'name' => $data['name'],
            'city' => $data['city'],
            'country' => $data['country'],
            'website' => $data['website'],
        ]);
    }

    public function updateById(int $id, array $data): void
    {
        $this->update('museums', [
            'name' => $data['name'],
            'city' => $data['city'],
            'country' => $data['country'],
            'website' => $data['website'],
        ], ['id' => $id]);
    }

    public function deleteById(int $id): int
    {
        return $this->delete('museums', ['id' => $id]);
    }
}