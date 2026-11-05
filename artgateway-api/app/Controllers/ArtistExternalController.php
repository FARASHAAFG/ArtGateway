<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Models\ArtistModel;
use App\Exceptions\ApiException;
use App\Services\ArtInstituteClient;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Composite resource for an artist: OUR artist + OUR artworks by that artist
 * + information about the artist from the Art Institute of Chicago.
 */
class ArtistExternalController extends BaseController
{
    private ArtistModel $artistModel;
    private ArtInstituteClient $articClient;

    public function __construct(ArtistModel $artistModel, ArtInstituteClient $articClient)
    {
        $this->artistModel = $artistModel;
        $this->articClient = $articClient;
    }

    // GET /artists/{id}/external
    //
    // Always answers 200 when the artist exists in OUR database, even if the
    // museum is down. "externalStatus" tells the client what happened:
    //   ok           -> "external" holds the museum's info about the artist
    //   not_found    -> the museum has no matching artist, "external" is null
    //   unavailable  -> the museum did not answer properly, "external" is null
    public function handleGetArtistExternal(Request $request, Response $response, array $args): Response
    {
        // 1. Validate the id from the URL (must be a positive whole number)
        if (!isset($args['id']) || !ctype_digit((string) $args['id']) || (int) $args['id'] < 1) {
            throw new ApiException(400, 'Validation failed', ['id' => 'id must be a positive whole number']);
        }
        $id = (int) $args['id'];

        // 2. Get OUR artist (404 if it does not exist) and OUR artworks by this artist
        $artist = $this->artistModel->getById($id);
        if (!$artist) {
            throw new ApiException(404, 'Artist not found');
        }
        $artworks = $this->artistModel->getArtworksByArtistId($id);

        // 3. Ask the museum about the artist
        $result = $this->articClient->findArtist(
            (string) $artist['name'],
            (string) $request->getAttribute('requestId', '')
        );

        // 4. Combine everything and answer
        $body = [
            'artist' => $artist,
            'artworks' => $artworks,
            'external' => $result['data'] ?? null,
            'externalStatus' => $result['status'],
        ];

        return $this->renderResponse($request, $response, $body);
    }
}