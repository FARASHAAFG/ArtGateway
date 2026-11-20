<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AboutController extends BaseController
{
    private const API_NAME = 'artgateway-api';

    private const API_VERSION = '1.0.0';

    public function handleAboutWebService(Request $request, Response $response): Response
    {

        $data = [
            'api' => self::API_NAME,
            'version' => self::API_VERSION,
            'about' => 'REST API for artists, artworks and museums, with data enriched from the Art Institute of Chicago.',
            'resources' => ['/artworks', '/artists', '/museums', '/artworks/{id}/external', '/artists/{id}/external'],
            'formats' => ['application/json', 'application/xml', 'application/yaml'],
        ];

        return $this->renderResponse($request, $response, $data);
    }
}
