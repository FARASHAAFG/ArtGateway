<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Models\ArtistModel;
use App\Domain\Models\MuseumModel;
use App\Domain\Models\ArtworkModel;
use App\Helpers\RequestValidator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Exceptions\ApiException;

class ArtworkController extends BaseController
{
    private ArtworkModel $artworkModel;
    private ArtistModel $artistModel;
    private MuseumModel $museumModel;

    public function __construct(ArtworkModel $artworkModel, ArtistModel $artistModel, MuseumModel $museumModel)
    {
        $this->artworkModel = $artworkModel;
        $this->artistModel = $artistModel;
        $this->museumModel = $museumModel;
    }

    /**
     * Retrieve artworks with filtering, sorting and pagination.
     */
    public function handleGetArtworks(
        Request $request,
        Response $response
    ): Response {

        // Get query parameters from the URL
        $params = $request->getQueryParams();

        // 1. VALIDATE REQUEST PARAMETERS
        $validator = new RequestValidator($params);

        // Validate pagination
        $validator->integer('page', false, 1, 1000);
        $validator->integer('limit', false, 1, 100);

        // Validate sorting
        // - user can only sort if the sort paramter is part of one of the four
        //   available in the function second parameter
        // - if none is specified, it goes with the id as the default
        $validator->oneOf(
            'sort',
            ['id', 'title', 'year', 'period'],
            'id'
        );

        // same here, only one of the 2 can b chosen
        // with asc as the default
        $validator->oneOf(
            'order',
            ['asc', 'desc'],
            'asc'
        );

        // Validate filtering
        $validator->string('period');
        $validator->string('title', false, 255);

        $validator->integer('year_min');
        $validator->integer('year_max');

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
            ['period', 'title', 'year_min', 'year_max'] as $field
        ) {
            if (isset($validated[$field])) {
                $filters[$field] = $validated[$field];
            }
        }

        // Validate year range
        if (
            isset($filters['year_min'], $filters['year_max']) &&
            $filters['year_min'] > $filters['year_max']
        ) {
            throw new ApiException(400, 'Validation failed', ['range' => 'Minimum year cannot exceed maximum year.']);
        }

        // Calculate pagination offset
        $offset = ($page - 1) * $limit;

        // 3. RETRIEVE ARTWORKS
        // Count all matching artworks
        $total = $this->artworkModel->countAll($filters);

        // Calculate total number of pages
        $totalPages = (int) ceil($total / $limit);

        // Retrieve artworks for the current page
        $artworks = $this->artworkModel->getAll(
            $filters,
            $sort,
            $order,
            $limit,
            $offset
        );

        // 4. GENERATE PAGINATION LINKS

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

        // 5. RETURN JSON RESPONSE
        $result = [
            'data' => $artworks,

            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total_items' => $total,
                'total_pages' => $totalPages
            ],

            'links' => $links
        ];

        //return $this->renderJson($response, $result);

        // this current line will render the response depedning on what the user wants
        // e.g. if the Accept header is application/xml, itll display it with xml
        return $this->renderResponse($request, $response, $result);
    }

    /**
     * Retrieve a single artwork by ID.
     */
    /*public function handleGetArtworkById(
        Request $request,
        Response $response,
        array $args
    ): Response {

        $id = (int) $args['id'];

        $artwork = $this->artworkModel->getById($id);

        if (!$artwork) {
            throw new ApiException(404, 'Artwork not found');
        }

        return $this->renderJson($response, $artwork);
    }*/
    /**
     * Retrieve a single artwork by ID.
     */
    public function handleGetArtworkById(
        Request $request,
        Response $response,
        array $args
    ): Response {

        // 1. VALIDATE THE ARTWORK ID
        $validator = new RequestValidator($args);

        $validator->integer('id', true, 1);

        // Return validation errors if any exist
        if ($validator->hasErrors()) {
            throw new ApiException(400, 'Validation failed', $validator->getErrors());
        }

        // 2. RETRIEVE THE VALIDATED ID
        $validated = $validator->getValidated();

        $id = $validated['id'];

        // 3. RETRIEVE THE ARTWORK
        $artwork = $this->artworkModel->getById($id);

        // Return 404 if the artwork does not exist
        if (!$artwork) {
            throw new ApiException(404, 'Artwork not found');
        }

        // 4. RETURN THE ARTWORK

        //return $this->renderJson($response, $artwork);
        return $this->renderResponse($request, $response, $artwork);
    }
 /* Retrieve a single artwork by ID.
 */
/*public function handleGetArtworkById(
    Request $request,
    Response $response,
    array $args
): Response {

    // 1. VALIDATE THE ARTWORK ID
    $validator = new RequestValidator($args);

    $validator->integer('id', true, 1);

    // Return validation errors if any exist
    if ($validator->hasErrors()) {
        return $this->renderJson(
            $response,
            [
                'error' => 'Validation failed.',
                'details' => $validator->getErrors()
            ],
            400
        );
    }

    // 2. RETRIEVE THE VALIDATED ID
    $validated = $validator->getValidated();

    $id = $validated['id'];

    // 3. RETRIEVE THE ARTWORK
    $artwork = $this->artworkModel->getById($id);

    // Return 404 if the artwork does not exist
    if (!$artwork) {
        return $this->renderJson(
            $response,
            ['error' => 'Artwork not found'],
            404
        );
    }

    // 4. RETURN THE ARTWORK
    return $this->renderJson($response, $artwork);
}*/

    // POST /artworks  ->  201 Created
    public function handleCreateArtwork(Request $request, Response $response): Response
    {
        $body = $this->getJsonBody($request);
        $data = $this->validateArtwork($body);

        $newId = $this->artworkModel->create($data);
        $artwork = $this->artworkModel->getById($newId);

        $location = rtrim($request->getUri()->getPath(), '/') . '/' . $newId;

        return $this->renderJson($response, $artwork, 201)->withHeader('Location', $location);
    }

    // PUT /artworks/{id}  ->  200 OK (replaces ALL the fields)
    public function handleUpdateArtwork(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];

        if (!$this->artworkModel->getById($id)) {
            throw new ApiException(404, 'Artwork not found');
        }

        $body = $this->getJsonBody($request);
        $data = $this->validateArtwork($body);

        $this->artworkModel->updateById($id, $data);

        return $this->renderJson($response, $this->artworkModel->getById($id));
    }

    // DELETE /artworks/{id}  ->  204 No Content
    public function handleDeleteArtwork(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];

        if (!$this->artworkModel->getById($id)) {
            throw new ApiException(404, 'Artwork not found');
        }

        $this->artworkModel->deleteById($id);

        return $response->withStatus(204);
    }

    /**
     * Checks the body of a POST/PUT for an artwork.
     * Returns a clean array with exactly the columns we store.
     */
    private function validateArtwork(array $body): array
    {
        $errors = array();
        $thisYear = (int)date('Y');

        $title = $this->checkString($body, 'title', true, 255, $errors);
        $creationYear = $this->checkInt($body, 'creationYear', true, -3000, $thisYear, $errors);
        $medium = $this->checkString($body, 'medium', false, 150, $errors);
        $period = $this->checkString($body, 'period', false, 100, $errors);
        $dimensions = $this->checkString($body, 'dimensions', false, 100, $errors);
        $artistId = $this->checkInt($body, 'artistId', true, 1, 2147483647, $errors);
        $museumId = $this->checkInt($body, 'museumId', false, 1, 2147483647, $errors);

        // The artist (and museum, if given) must really exist.
        // We only check when the id itself was valid, so we don't stack two errors on one field.
        if ($artistId !== null && !$this->artistModel->getById($artistId)) {
            $errors['artistId'] = 'No artist exists with this id';
        }
        if ($museumId !== null && !$this->museumModel->getById($museumId)) {
            $errors['museumId'] = 'No museum exists with this id';
        }

        $this->throwIfInvalid($errors);

        return array(
            'title' => $title,
            'creationYear' => $creationYear,
            'medium' => $medium,
            'period' => $period,
            'dimensions' => $dimensions,
            'artistId' => $artistId,
            'museumId' => $museumId,
        );
    }
}
