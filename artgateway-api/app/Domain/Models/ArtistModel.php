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

    /**
     * Retrieve artists with optional filtering, sorting and pagination.
     */
    public function getAll(
        array $filters = [],
        string $sort = 'id',
        string $order = 'asc',
        int $limit = 20,
        int $offset = 0
    ): array {

        $params = [];

        // Build filtering conditions
        $where = $this->buildFilters($filters, $params);

        // Allowed sorting columns
        $allowedSorts = [
            'id' => 'id',
            'name' => 'name',
            'birthYear' => 'birthYear',
            'deathYear' => 'deathYear',
            'nationality' => 'nationality'
        ];

        // Prevent unauthorized column sorting
        $sortColumn = $allowedSorts[$sort] ?? 'id';

        // Validate sorting direction
        $order = strtolower($order);
        $direction = in_array($order, ['asc', 'desc'], true)
            ? strtoupper($order)
            : 'ASC';

        // Prevent invalid pagination values
        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);

        // Build SQL query
        $sql = "SELECT id, name, birthYear, deathYear, nationality
                FROM artists
                $where
                ORDER BY $sortColumn $direction";

        // Unique tie-breaker for consistent pagination
        if ($sortColumn !== 'id') {
            $sql .= ", id ASC";
        }

        $sql .= " LIMIT :limit OFFSET :offset";

        $params['limit'] = $limit;
        $params['offset'] = $offset;

        // Retrieve artists
        $rows = $this->fetchAll($sql, $params);

        return $rows;
    }

    /**
     * Count artists matching the supplied filters.
     */
    public function countAll(array $filters = []): int
    {
        $params = [];

        $where = $this->buildFilters($filters, $params);

        $sql = "SELECT COUNT(*) as total_count
                FROM artists
                $where";

        $row = $this->fetchSingle($sql, $params);

        // Return the total number of matching artists
        return (int) ($row['total_count'] ?? 0);
    }

    /**
     * Build reusable SQL filtering conditions.
     */
    private function buildFilters(
        array $filters,
        array &$params
    ): string {

        $conditions = [];

        // Search artist name
        if (
            isset($filters['name']) &&
            trim((string) $filters['name']) !== ''
        ) {
            $conditions[] = "LOWER(name) LIKE LOWER(:name) ESCAPE '!'";

            // Escape wildcard characters
            $search = strtr($filters['name'], [
                '!' => '!!',
                '%' => '!%',
                '_' => '!_'
            ]);

            $params['name'] = '%' . trim($search) . '%';
        }

        // Filter by nationality
        if (
            isset($filters['nationality']) &&
            trim((string) $filters['nationality']) !== ''
        ) {
            $conditions[] = "LOWER(nationality) LIKE LOWER(:nationality) ESCAPE '!'";

            // Escape wildcard characters
            $search = strtr($filters['nationality'], [
                '!' => '!!',
                '%' => '!%',
                '_' => '!_'
            ]);

            $params['nationality'] = '%' . trim($search) . '%';
        }

        // Filter by minimum birth year
        if (
            isset($filters['birthYear_min']) &&
            $filters['birthYear_min'] !== ''
        ) {
            $conditions[] = "birthYear >= :birthYear_min";
            $params['birthYear_min'] = (int) $filters['birthYear_min'];
        }

        // Filter by maximum birth year
        if (
            isset($filters['birthYear_max']) &&
            $filters['birthYear_max'] !== ''
        ) {
            $conditions[] = "birthYear <= :birthYear_max";
            $params['birthYear_max'] = (int) $filters['birthYear_max'];
        }

        // Return empty string if no filters were provided
        if (empty($conditions)) {
            return "";
        }

        return "WHERE " . implode(" AND ", $conditions);
    }

    // Getting one artist by ID
    public function getById(int $id)
    {
        $sql = "SELECT id, name, birthYear, deathYear, nationality
                FROM artists
                WHERE id = :id";

        return $this->fetchSingle($sql, ['id' => $id]);
    }

    /**
     * Retrieve artworks belonging to an artist.
     */
    public function getArtworksByArtistId(int $id): array
    {
        $sql = "SELECT id, title, creationYear, medium, period,
                       dimensions, artistId, museumId
                FROM artworks
                WHERE artistId = :id";

        return $this->fetchAll($sql, ['id' => $id]);
    }

    // Inserts a new artist and returns the new id.
    public function create(array $data): int
    {
        $newId = $this->insert('artists', array(
            'name' => $data['name'],
            'birthYear' => $data['birthYear'],
            'deathYear' => $data['deathYear'],
            'nationality' => $data['nationality'],
        ));

        return (int) $newId;
    }

    // Replaces all the fields of an existing artist.
    public function updateById(int $id, array $data): void
    {
        $this->update('artists', array(
            'name' => $data['name'],
            'birthYear' => $data['birthYear'],
            'deathYear' => $data['deathYear'],
            'nationality' => $data['nationality'],
        ), array('id' => $id));
    }

    // Deletes one artist.
    public function deleteById(int $id): int
    {
        return $this->delete('artists', array('id' => $id));
    }
}
