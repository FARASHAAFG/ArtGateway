<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\ContentNegotiator;
use App\Helpers\ResponseSerializer;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * Sends EVERY response (data and errors) in the format the client asked for.
 *
 * Controllers and the error handler build JSON. On the way OUT, this middleware
 * converts that JSON to XML or YAML with ResponseSerializer when the Accept header
 * asks for it. Responses that are already converted (for example by
 * BaseController::renderResponse) or that have no body (204) are left alone.
 *
 * It must be placed OUTSIDE the error middleware so error responses are converted too.
 */
class ResponseFormatMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        // Tell caches that the body depends on the Accept header.
        $response = $response->withAddedHeader('Vary', 'Accept');

        $result = (new ContentNegotiator())->negotiate($request);
        if (!$result['success'] || $result['format'] === 'json') {
            return $response; // JSON is already what we have (a 406 is built as JSON)
        }

        // Only convert JSON bodies.
        if (!str_starts_with($response->getHeaderLine('Content-Type'), 'application/json')) {
            return $response;
        }

        $data = json_decode((string) $response->getBody(), true);
        if (!is_array($data)) {
            return $response; // empty body or not an object/list: leave it alone
        }

        try {
            $serialized = (new ResponseSerializer())->serialize($data, $result['format']);
        } catch (\Throwable $e) {
            // If conversion fails, still answer (in JSON) instead of crashing.
            return $response;
        }

        return $response
            ->withBody((new Psr17Factory())->createStream($serialized['body']))
            ->withHeader('Content-Type', $serialized['contentType']);
    }
}