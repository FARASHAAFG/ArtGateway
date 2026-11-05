<?php

declare(strict_types=1);

namespace App\Domain\Models;

use App\Helpers\Core\PDOService;

class ArtistModel extends BaseModel
{
    public function __construct(PDOService $pdo)
    {
        parent::__construct($pdo);
    }

    public function getAll(): array
    {
        $sql = "SELECT id, name, birthYear, deathYear, nationality
                FROM artists
                ORDER BY name";

        return $this->fetchAll($sql);
    }

    public function getById(int $id)
    {
        $sql = "SELECT id, name, birthYear, deathYear, nationality
                FROM artists
                WHERE id = :id";

        return $this->fetchSingle($sql, array('id' => $id));
    }

    public function getArtworksByArtistId(int $artistId): array
    {
        $sql = "SELECT id, title, creationYear, medium, period
                FROM artworks
                WHERE artistId = :artistId
                ORDER BY creationYear";

        return $this->fetchAll($sql, array('artistId' => $artistId));
    }

    // Inserts a new artist and returns the new id.
    // $data must contain ONLY the keys below (the controller builds it from a fixed list).
    public function create(array $data): int
    {
        $newId = $this->insert('artists', array(
            'name' => $data['name'],
            'birthYear' => $data['birthYear'],
            'deathYear' => $data['deathYear'],
            'nationality' => $data['nationality'],
        ));

        return (int)$newId;
    }

    // Replaces all the fields of an existing artist.
    public function updateById(int $id, array $data): void
    {
        // BaseModel's update(table, new values, where condition)
        $this->update('artists', array(
            'name' => $data['name'],
            'birthYear' => $data['birthYear'],
            'deathYear' => $data['deathYear'],
            'nationality' => $data['nationality'],
        ), array('id' => $id));
    }

    // Deletes one artist. Returns how many rows were deleted (1 or 0).
    public function deleteById(int $id): int
    {
        return $this->delete('artists', array('id' => $id));
    }
}