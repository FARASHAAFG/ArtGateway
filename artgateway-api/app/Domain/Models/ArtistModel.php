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
}