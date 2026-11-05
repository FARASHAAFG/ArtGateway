<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Models\ArtistModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Exceptions\ApiException;

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
            throw new ApiException(404, 'Artist not found');
        }

        return $this->renderJson($response, $artist);
    }

    public function handleGetArtworksByArtist(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
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
        $id = (int)$args['id'];

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
        $id = (int)$args['id'];

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
        $thisYear = (int)date('Y');

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