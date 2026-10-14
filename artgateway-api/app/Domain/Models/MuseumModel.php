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

    public function getAll(): array
    {
        $sql = "SELECT id, name, city, country, website
                FROM museums
                ORDER BY name";

        return $this->fetchAll($sql);
    }

    public function getById(int $id)
    {
        $sql = "SELECT id, name, city, country, website
                FROM museums
                WHERE id = :id";

        return $this->fetchSingle($sql, array('id' => $id));
    }

    public function getArtworksByMuseumId(int $museumId): array
    {
        $sql = "SELECT id, title, creationYear, medium, period
                FROM artworks
                WHERE museumId = :museumId
                ORDER BY creationYear";

        return $this->fetchAll($sql, array('museumId' => $museumId));
    }
}