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

    /**
     * Retrieve artworks with optional filtering, sorting and pagination.
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
            'title' => 'title',
            'year' => 'creationYear',
            'period' => 'period'
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
        $sql = "SELECT id, title, creationYear, medium, period,
                       dimensions, artistId, museumId
                FROM artworks
                $where
                ORDER BY $sortColumn $direction";

        // Unique tie-breaker for consistent pagination
        if ($sortColumn !== 'id') {
            $sql .= ", id ASC";
        }

        $sql .= " LIMIT :limit OFFSET :offset";

        $params['limit'] = $limit;
        $params['offset'] = $offset;

        // Retrieve artworks
        $rows = $this->fetchAll($sql, $params);

        // Attach tags to each artwork
        $result = [];

        foreach ($rows as $row) {
            $row['tags'] = $this->getTagsForArtwork(
                (int) $row['id']
            );

            $result[] = $row;
        }

        return $result;
    }

    /**
     * Count artworks matching the supplied filters.
     */
    public function countAll(array $filters = []): int
    {
        $params = [];

        $where = $this->buildFilters($filters, $params);

        $sql = "SELECT COUNT(*) as total_count
                FROM artworks
                $where";

        $row = $this->fetchSingle($sql, $params);

        // Return the total number of matching artworks
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

        // Filter by artistic period
        if (
            isset($filters['period']) &&
            trim((string) $filters['period']) !== ''
        ) {
            $conditions[] = "period = :period";
            $params['period'] = trim($filters['period']);
        }

        // Search artwork title
        if (
            isset($filters['title']) &&
            trim((string) $filters['title']) !== ''
        ) {
            $conditions[] = "LOWER(title) LIKE LOWER(:title) ESCAPE '!'";
            
            // Escape wildcard characters
            $search = strtr($filters['title'], [
                '!' => '!!',
                '%' => '!%',
                '_' => '!_'
            ]);

            $params['title'] = '%' . trim($search) . '%';
        }

        // Filter by minimum creation year
        if (
            isset($filters['year_min']) &&
            $filters['year_min'] !== ''
        ) {
            $conditions[] = "creationYear >= :year_min";
            $params['year_min'] = (int) $filters['year_min'];
        }

        // Filter by maximum creation year
        if (
            isset($filters['year_max']) &&
            $filters['year_max'] !== ''
        ) {
            $conditions[] = "creationYear <= :year_max";
            $params['year_max'] = (int) $filters['year_max'];
        }

        // Return empty string if no filters were provided
        if (empty($conditions)) {
            return "";
        }

        return "WHERE " . implode(" AND ", $conditions);
    }

    // Getting one artwork by ID
    public function getById(int $id)
    {
        $sql = "SELECT id, title, creationYear, medium, period,
                       dimensions, artistId, museumId
                FROM artworks
                WHERE id = :id";

        $row = $this->fetchSingle($sql, ['id' => $id]);

        if ($row) {
            $row['tags'] = $this->getTagsForArtwork(
                (int) $row['id']
            );
        }

        return $row;
    }

    /**
     * Retrieve tags associated with an artwork.
     */
    private function getTagsForArtwork(int $artworkId): array
    {
        $sql = "SELECT tags.name
                FROM tags
                JOIN artwork_tags
                    ON artwork_tags.tagId = tags.id
                WHERE artwork_tags.artworkId = :artworkId";

        $rows = $this->fetchAll(
            $sql,
            ['artworkId' => $artworkId]
        );

        $tagNames = [];

        foreach ($rows as $row) {
            $tagNames[] = $row['name'];
        }

        return $tagNames;
    }

    // Inserts a new artwork and returns the new id.
    public function create(array $data): int
    {
        $newId = $this->insert('artworks', array(
            'title' => $data['title'],
            'creationYear' => $data['creationYear'],
            'medium' => $data['medium'],
            'period' => $data['period'],
            'dimensions' => $data['dimensions'],
            'artistId' => $data['artistId'],
            'museumId' => $data['museumId'],
        ));

        return (int)$newId;
    }

    // Replaces all the fields of an existing artwork.
    public function updateById(int $id, array $data): void
    {
        $this->update('artworks', array(
            'title' => $data['title'],
            'creationYear' => $data['creationYear'],
            'medium' => $data['medium'],
            'period' => $data['period'],
            'dimensions' => $data['dimensions'],
            'artistId' => $data['artistId'],
            'museumId' => $data['museumId'],
        ), array('id' => $id));
    }

    // Deletes one artwork. Its tag links and collection links are removed
    // automatically by the database (ON DELETE CASCADE).
    public function deleteById(int $id): int
    {
        return $this->delete('artworks', array('id' => $id));
    }
}
