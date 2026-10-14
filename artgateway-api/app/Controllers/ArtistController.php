<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Models\ArtistModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ArtistController extends BaseController
{
    private ArtistModel $artistModel;

    public function __construct(ArtistModel $artistModel)
    {
        $this->artistModel = $artistModel;
    }

    public function handleGetArtists(Request $request, Response $response): Response
    {
        $artists = $this->artistModel->getAll();

        return $this->renderJson($response, $artists);
    }

    public function handleGetArtistById(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $artist = $this->artistModel->getById($id);

        if (!$artist) {
            return $this->renderJson($response, array('error' => 'Artist not found'), 404);
        }

        return $this->renderJson($response, $artist);
    }

    public function handleGetArtworksByArtist(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $artworks = $this->artistModel->getArtworksByArtistId($id);

        return $this->renderJson($response, $artworks);
    }
}