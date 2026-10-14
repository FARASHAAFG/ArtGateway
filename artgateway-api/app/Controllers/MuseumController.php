<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Models\MuseumModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class MuseumController extends BaseController
{
    private MuseumModel $museumModel;

    public function __construct(MuseumModel $museumModel)
    {
        $this->museumModel = $museumModel;
    }

    public function handleGetMuseums(Request $request, Response $response): Response
    {
        $museums = $this->museumModel->getAll();
        return $this->renderJson($response, $museums);
    }

    public function handleGetMuseumById(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $museum = $this->museumModel->getById($id);

        if (!$museum) {
            return $this->renderJson($response, array('error' => 'Museum not found'), 404);
        }

        return $this->renderJson($response, $museum);
    }

    public function handleGetArtworksByMuseum(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $artworks = $this->museumModel->getArtworksByMuseumId($id);

        return $this->renderJson($response, $artworks);
    }
}