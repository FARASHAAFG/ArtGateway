<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Helpers\ResponseSerializer;

abstract class BaseController
{

    public function __construct() {}
    protected function renderJson(Response $response, array $data, int $status_code = 200): Response
    {
        // var_dump($data);
        $payload = json_encode($data, JSON_UNESCAPED_SLASHES |    JSON_PARTIAL_OUTPUT_ON_ERROR);
        //-- Write JSON data into the response's body.
        $response->getBody()->write($payload);
        return $response->withStatus($status_code)->withAddedHeader(HEADERS_CONTENT_TYPE, APP_MEDIA_TYPE_JSON);
    }

    protected function renderResponse(
        Request $request,
        Response $response,
        array $data,
        int $status_code = 200
    ): Response {

        // Get the format selected by the middleware.
        // If no format was selected, default to JSON.
        $format = $request->getAttribute('response_format', 'json');

        // Create the serializer.
        $serializer = new ResponseSerializer();

        // Convert the data into the requested format.
        $serialized = $serializer->serialize($data, $format);

        // Write the serialized content into the response body.
        $response->getBody()->write($serialized['body']);

        // Set the status code and correct Content-Type header.
        return $response
            ->withStatus($status_code)
            ->withHeader(
                'Content-Type',
                $serialized['contentType']
            );
    }
}
