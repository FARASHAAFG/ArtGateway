<?php

declare(strict_types=1);

namespace App\Domain\Models;

use App\Helpers\Core\PDOService;

class ArtworkModel extends BaseModel
{

    public function __construct(PDOService $pdo)
    {
        parent::__construct($pdo);
    }

    public function getAll(array $filters = []): array
    {
        $sql = "SELECT id, title, creationYear, medium, period, dimensions, artistId, museumId
                FROM artworks
                WHERE 1=1";

        $params = array();

        if (!empty($filters['period'])) {
            $sql = $sql . " AND period = :period";
            $params['period'] = $filters['period'];
        }

        if (!empty($filters['title'])) {
            $sql = $sql . " AND title LIKE :title";
            $params['title'] = '%' . $filters['title'] . '%';
        }

        if (!empty($filters['year_min'])) {
            $sql = $sql . " AND creationYear >= :year_min";
            $params['year_min'] = (int)$filters['year_min'];
        }

        if (!empty($filters['year_max'])) {
            $sql = $sql . " AND creationYear <= :year_max";
            $params['year_max'] = (int)$filters['year_max'];
        }

        $sql = $sql . " ORDER BY id";

        $rows = $this->fetchAll($sql, $params);

        //attach tags to each artwork
        $result = array();
        foreach ($rows as $row) {
            $row['tags'] = $this->getTagsForArtwork($row['id']);
            $result[] = $row;
        }
        return $result;
    }

    //Getting one artwork by ID
    public function getById(int $id)
    {
        $sql = "SELECT id, title, creationYear, medium, period, dimensions, artistId, museumId
                FROM artworks
                WHERE id = :id";

        $row = $this->fetchSingle($sql, array('id' => $id));

        if ($row) {
            $row['tags'] = $this->getTagsForArtwork($row['id']);
        }

        return $row;
    }

    private function getTagsForArtwork(int $artworkId): array
    {
        $sql = "SELECT tags.name
                FROM tags
                JOIN artwork_tags ON artwork_tags.tagId = tags.id
                WHERE artwork_tags.artworkId = :artworkId";

        $rows = $this->fetchAll($sql, array('artworkId' => $artworkId));

        $tagNames = array();
        foreach ($rows as $row) {
            $tagNames[] = $row['name'];
        }

        return $tagNames;
    }
}