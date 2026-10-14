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

    public function handleGetArtworks(Request $request, Response $response): Response
    {
        $filters = $request->getQueryParams();

        $artworks = $this->artworkModel->getAll($filters);
        return $this->renderJson($response, $artworks);
    }

    public function handleGetArtworkById(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $artwork = $this->artworkModel->getById($id);

        if (!$artwork) {
            return $this->renderJson($response, array('error' => 'Artwork not found'), 404);
        }

        return $this->renderJson($response, $artwork);
    }
}