<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Models\ArtistModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Exceptions\ApiException;
use App\Helpers\RequestValidator;

class ArtistController extends BaseController
{
    private ArtistModel $artistModel;

    public function __construct(ArtistModel $artistModel)
    {
        $this->artistModel = $artistModel;
    }

    /*public function handleGetArtists(Request $request, Response $response): Response
    {
        $artists = $this->artistModel->getAll();

        return $this->renderJson($response, $artists);
    }*/
    public function handleGetArtists(Request $request, Response $response): Response
    {
        // Get query parameters from the URL
        $params = $request->getQueryParams();

        // 1. VALIDATE REQUEST PARAMETERS
        $validator = new RequestValidator($params);

        // Validate pagination
        $validator->integer('page', false, 1, 1000);
        $validator->integer('limit', false, 1, 100);

        // Validate sorting
        $validator->oneOf(
            'sort',
            ['id', 'name', 'birthYear', 'deathYear', 'nationality'],
            'id'
        );

        $validator->oneOf(
            'order',
            ['asc', 'desc'],
            'asc'
        );

        // Validate filtering
        $validator->string('name', false, 150);
        $validator->string('nationality', false, 100);

        $validator->integer('birthYear_min');
        $validator->integer('birthYear_max');

        // Return validation errors if any exist
        if ($validator->hasErrors()) {
            throw new ApiException(400, 'Validation failed', $validator->getErrors());
        }

        // 2. RETRIEVE VALIDATED PARAMETERS
        $validated = $validator->getValidated();

        // Pagination defaults
        $page = $validated['page'] ?? 1;
        $limit = $validated['limit'] ?? 20;

        // Sorting defaults
        $sort = $validated['sort'];
        $order = $validated['order'];

        // Extract filters
        $filters = [];

        foreach (
            ['name', 'nationality', 'birthYear_min', 'birthYear_max'] as $field
        ) {
            if (isset($validated[$field])) {
                $filters[$field] = $validated[$field];
            }
        }

        // Validate birth year range
        if (
            isset($filters['birthYear_min'], $filters['birthYear_max']) &&
            $filters['birthYear_min'] > $filters['birthYear_max']
        ) {
            throw new ApiException(400, 'Validation failed', ['range' => 'Minimum birth year cannot exceed maximum birth year.']);
        }

        // 3. CALCULATE PAGINATION OFFSET
        $offset = ($page - 1) * $limit;

        // 4. RETRIEVE ARTISTS

        // Count all matching artists
        $total = $this->artistModel->countAll($filters);

        // Calculate total number of pages
        $totalPages = (int) ceil($total / $limit);

        // Retrieve artists for the current page
        $artists = $this->artistModel->getAll(
            $filters,
            $sort,
            $order,
            $limit,
            $offset
        );

        // 5. GENERATE PAGINATION LINKS

        $queryParams = array_merge(
            $filters,
            [
                'sort' => $sort,
                'order' => $order,
                'limit' => $limit
            ]
        );

        // Generate URL for a specific page
        $makePageUrl = function (int $pageNumber) use ($request, $queryParams): string {

            $query = http_build_query(
                array_merge(
                    $queryParams,
                    ['page' => $pageNumber]
                )
            );

            return (string) $request->getUri()->withQuery($query);
        };

        // Generate navigation links
        $links = [
            'first' => $totalPages > 0
                ? $makePageUrl(1)
                : null,

            'prev' => $page > 1 && $totalPages > 0
                ? $makePageUrl(min($page - 1, $totalPages))
                : null,

            'next' => $page < $totalPages
                ? $makePageUrl($page + 1)
                : null,

            'last' => $totalPages > 0
                ? $makePageUrl($totalPages)
                : null
        ];

        // 6. RETURN RESPONSE
        $result = [
            'data' => $artists,

            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total_items' => $total,
                'total_pages' => $totalPages
            ],

            'links' => $links
        ];

        // Render according to the client's Accept header
        return $this->renderResponse($request, $response, $result);
    }

    public function handleGetArtistById(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $artist = $this->artistModel->getById($id);

        if (!$artist) {
            throw new ApiException(404, 'Artist not found');
        }

        return $this->renderJson($response, $artist);
    }

    public function handleGetArtworksByArtist(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $artworks = $this->artistModel->getArtworksByArtistId($id);

        return $this->renderJson($response, $artworks);
    }

    // POST /artists  ->  201 Created
    public function handleCreateArtist(Request $request, Response $response): Response
    {
        $body = $this->getJsonBody($request);

        // Check the input (throws 422 if something is wrong)
        $data = $this->validateArtist($body);

        $newId = $this->artistModel->create($data);
        $artist = $this->artistModel->getById($newId);

        // Location header tells the client where the new artist lives: /artists/5
        $location = rtrim($request->getUri()->getPath(), '/') . '/' . $newId;

        return $this->renderJson($response, $artist, 201)->withHeader('Location', $location);
    }

    // PUT /artists/{id}  ->  200 OK (replaces ALL the fields)
    public function handleUpdateArtist(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];

        if (!$this->artistModel->getById($id)) {
            throw new ApiException(404, 'Artist not found');
        }

        $body = $this->getJsonBody($request);
        $data = $this->validateArtist($body);

        $this->artistModel->updateById($id, $data);

        return $this->renderJson($response, $this->artistModel->getById($id));
    }

    // DELETE /artists/{id}  ->  204 No Content
    public function handleDeleteArtist(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];

        if (!$this->artistModel->getById($id)) {
            throw new ApiException(404, 'Artist not found');
        }

        // An artist who still has artworks cannot be deleted (the artworks need an artist).
        $artworks = $this->artistModel->getArtworksByArtistId($id);
        if (count($artworks) > 0) {
            throw new ApiException(409, 'This artist still has artworks. Delete or reassign them first.');
        }

        $this->artistModel->deleteById($id);

        // 204 = success, nothing to send back
        return $response->withStatus(204);
    }

    /**
     * Checks the body of a POST/PUT for an artist.
     * Returns a clean array with exactly the 4 columns we store.
     * Anything else the client sends (like "id") is ignored on purpose.
     */
    private function validateArtist(array $body): array
    {
        $errors = array();
        $thisYear = (int) date('Y');

        $name = $this->checkString($body, 'name', true, 150, $errors);
        $birthYear = $this->checkInt($body, 'birthYear', false, -3000, $thisYear, $errors);
        $deathYear = $this->checkInt($body, 'deathYear', false, -3000, $thisYear, $errors);
        $nationality = $this->checkString($body, 'nationality', false, 100, $errors);

        // Cross-field rule: an artist cannot die before being born.
        if ($birthYear !== null && $deathYear !== null && $deathYear < $birthYear) {
            $errors['deathYear'] = 'deathYear cannot be before birthYear';
        }

        $this->throwIfInvalid($errors);

        return array(
            'name' => $name,
            'birthYear' => $birthYear,
            'deathYear' => $deathYear,
            'nationality' => $nationality,
        );
    }
}