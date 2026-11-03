<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Models\ArtworkModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ArtworkController extends BaseController
{
    private ArtworkModel $artworkModel;

    public function __construct(ArtworkModel $artworkModel)
    {
        $this->artworkModel = $artworkModel;
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

        // -----------------------------------
        // 1. PAGINATION PARAMETERS
        // -----------------------------------

        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 20;

        // Validate page
        if (
            (!is_string($page) && !is_int($page)) ||
            filter_var($page, FILTER_VALIDATE_INT) === false ||
            (int) $page < 1 ||
            (int) $page > 1000000
        ) {
            return $this->renderJson(
                $response,
                ['error' => 'Page must be a positive integer.'],
                400
            );
        }

        // Validate limit
        if (
            (!is_string($limit) && !is_int($limit)) ||
            filter_var($limit, FILTER_VALIDATE_INT) === false ||
            (int) $limit < 1 ||
            (int) $limit > 100
        ) {
            return $this->renderJson(
                $response,
                ['error' => 'Limit must be between 1 and 100.'],
                400
            );
        }

        $page = (int) $page;
        $limit = (int) $limit;

        // Calculate pagination offset
        $offset = ($page - 1) * $limit;


        // -----------------------------------
        // 2. SORTING PARAMETERS
        // -----------------------------------

        $sort = $params['sort'] ?? 'id';
        $order = $params['order'] ?? 'asc';

        $allowedSorts = [
            'id',
            'title',
            'year',
            'period'
        ];

        // Validate sorting column
        if (
            !is_string($sort) ||
            !in_array($sort, $allowedSorts, true)
        ) {
            return $this->renderJson(
                $response,
                ['error' => 'Invalid sorting field.'],
                400
            );
        }

        // Validate sorting direction
        if (
            !is_string($order) ||
            !in_array(strtolower($order), ['asc', 'desc'], true)
        ) {
            return $this->renderJson(
                $response,
                ['error' => 'Order must be asc or desc.'],
                400
            );
        }

        $order = strtolower($order);


        // -----------------------------------
        // 3. FILTERING PARAMETERS
        // -----------------------------------

        $filters = [];

        // Filter by artistic period
        if (isset($params['period'])) {

            if (
                !is_string($params['period']) ||
                trim($params['period']) === ''
            ) {
                return $this->renderJson(
                    $response,
                    ['error' => 'Invalid period parameter.'],
                    400
                );
            }

            $filters['period'] = trim($params['period']);
        }

        // Search artwork title
        if (isset($params['title'])) {

            if (
                !is_string($params['title']) ||
                trim($params['title']) === '' ||
                strlen($params['title']) > 255
            ) {
                return $this->renderJson(
                    $response,
                    ['error' => 'Invalid title parameter.'],
                    400
                );
            }

            $filters['title'] = trim($params['title']);
        }

        // Minimum creation year
        if (isset($params['year_min'])) {

            if (
               (!is_string($params['year_min']) && !is_int($params['year_min'])) ||
                filter_var($params['year_min'], FILTER_VALIDATE_INT) === false
            ) {
                return $this->renderJson(
                    $response,
                    ['error' => 'Invalid minimum year.'],
                    400
                );
            }

            $filters['year_min'] = (int) $params['year_min'];
        }

        // Maximum creation year
        if (isset($params['year_max'])) {

            if (
                (!is_string($params['year_max']) && !is_int($params['year_max'])) ||
                filter_var($params['year_max'], FILTER_VALIDATE_INT) === false
            ) {
                return $this->renderJson(
                    $response,
                    ['error' => 'Invalid maximum year.'],
                    400
                );
            }

            $filters['year_max'] = (int) $params['year_max'];
        }

        // Validate year range
        if (
            isset($filters['year_min'], $filters['year_max']) &&
            $filters['year_min'] > $filters['year_max']
        ) {
            return $this->renderJson(
                $response,
                ['error' => 'Minimum year cannot exceed maximum year.'],
                400
            );
        }


        // -----------------------------------
        // 4. RETRIEVE ARTWORKS
        // -----------------------------------

        // Count matching artworks
        $total = $this->artworkModel->countAll($filters);

        // Calculate total number of pages
        $totalPages = (int) ceil($total / $limit);

        // Retrieve matching artworks
        $artworks = $this->artworkModel->getAll(
            $filters,
            $sort,
            $order,
            $limit,
            $offset
        );


        // -----------------------------------
        // 5. GENERATE PAGINATION LINKS
        // -----------------------------------

        $queryParams = array_merge(
            $filters,
            [
                'sort' => $sort,
                'order' => $order,
                'limit' => $limit
            ]
        );

        // Generate URL for a specific page
        $makePageUrl = function (int $pageNumber) use (
            $request,
            $queryParams
        ): string {

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


        // -----------------------------------
        // 6. RETURN JSON RESPONSE
        // -----------------------------------

        $result = [
            'debug' => 'pagination-controller-v2',
            'data' => $artworks,

            

            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total_items' => $total,
                'total_pages' => $totalPages
            ],

            'links' => $links
        ];

        return $this->renderJson($response, $result);
    }


    /**
     * Retrieve a single artwork by ID.
     */
    public function handleGetArtworkById(
        Request $request,
        Response $response,
        array $args
    ): Response {

        $id = (int) $args['id'];

        $artwork = $this->artworkModel->getById($id);

        if (!$artwork) {
            return $this->renderJson(
                $response,
                ['error' => 'Artwork not found'],
                404
            );
        }

        return $this->renderJson($response, $artwork);
    }
}
