<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Models\ArtistModel;
use App\Domain\Models\ArtworkModel;
use App\Exceptions\ApiException;
use App\Services\ArtInstituteClient;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * The "composite" resource: one of OUR artworks combined with information
 * from an external service (Art Institute of Chicago).
 */
class ArtworkExternalController extends BaseController
{
    private ArtworkModel $artworkModel;
    private ArtistModel $artistModel;
    private ArtInstituteClient $articClient;

    public function __construct(ArtworkModel $artworkModel, ArtistModel $artistModel, ArtInstituteClient $articClient)
    {
        $this->artworkModel = $artworkModel;
        $this->artistModel = $artistModel;
        $this->articClient = $articClient;
    }

    // GET /artworks/{id}/external
    //
    // Always answers 200 when the artwork exists in OUR database, even if the
    // museum is down. "externalStatus" tells the client what happened:
    //   ok           -> "external" holds the museum's info
    //   not_found    -> the museum has no matching artwork, "external" is null
    //   unavailable  -> the museum did not answer properly, "external" is null
    public function handleGetArtworkExternal(Request $request, Response $response, array $args): Response
    {
        // 1. Validate the id from the URL (must be a positive whole number)
        if (!isset($args['id']) || !ctype_digit((string) $args['id']) || (int) $args['id'] < 1) {
            throw new ApiException(400, 'Validation failed', ['id' => 'id must be a positive whole number']);
        }
        $id = (int) $args['id'];

        // 2. Get OUR artwork (404 if it does not exist)
        $artwork = $this->artworkModel->getById($id);
        if (!$artwork) {
            throw new ApiException(404, 'Artwork not found');
        }

        // 3. We search the museum with the title and the artist's name
        $artist = $this->artistModel->getById((int) $artwork['artistId']);
        $artistName = $artist ? (string) $artist['name'] : '';

        $result = $this->articClient->findArtwork(
            (string) $artwork['title'],
            $artistName,
            (string) $request->getAttribute('requestId', '')
        );

        // 4. Combine both and answer
        $body = [
            'artwork' => $artwork,
            'external' => $result['data'] ?? null,
            'externalStatus' => $result['status'],
        ];

        return $this->renderResponse($request, $response, $body);
    }
}