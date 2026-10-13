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
        $sql = "SELECT
                    id,
                    title,
                    creation_year AS creationYear,
                    medium,
                    period,
                    dimensions,
                    artist_id AS artistId,
                    museum_id AS museumId,
                    tags
                FROM artworks";

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
            $sql = $sql . " AND creation_year >= :year_min";
            $params['year_min'] = (int)$filters['year_min'];
        }

        if (!empty($filters['year_max'])) {
            $sql = $sql . " AND creation_year <= :year_max";
            $params['year_max'] = (int)$filters['year_max'];
        }

        $sql = $sql . " ORDER BY id";

        $rows = $this->fetchAll($sql, $params);

        // convert in to PHP array
        $result = array();
        foreach ($rows as $row) {
            if ($row['tags']) {
                $row['tags'] = json_decode($row['tags'], true);
            } else {
                $row['tags'] = array();
            }
            $result[] = $row;
        }
        return $result;
    }

    //Getting one artwork by ID
    public function getById(int $id)
    {
        $sql = "SELECT
                    id,
                    title,
                    creation_year AS creationYear,
                    medium,
                    period,
                    dimensions,
                    artist_id AS artistId,
                    museum_id AS museumId,
                    tags
                FROM artworks
                WHERE id = :id";

        $row = $this->fetchSingle($sql, array('id' => $id));

        if ($row) {
            if ($row['tags']) {
                $row['tags'] = json_decode($row['tags'], true);
            } else {
                $row['tags'] = array();
            }
        }

        return $row;
    }
}