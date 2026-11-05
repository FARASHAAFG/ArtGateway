<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exceptions\ApiException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class ContentNegotiationMiddleware implements MiddlewareInterface
{
    // Formats the API can send back
    private array $producible = ['application/json'];

    // Formats the API accepts in a request body.
    private array $consumable = ['application/json'];

    public function process(Request $request, RequestHandler $handler): ResponseInterface
    {
        // ---- 1. Accept header (what the client wants back) ----
        $accept = strtolower($request->getHeaderLine('Accept'));

        // No Accept header, or "*/*" means "anything is fine".
        if ($accept !== '' && !$this->acceptsAnyOf($accept)) {
            throw new ApiException(406, 'This API can only return: ' . implode(', ', $this->producible));
        }

        // ---- 2. Content-Type header (what the client sent) ----
        $method = $request->getMethod();
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $body = (string) $request->getBody();
            $request->getBody()->rewind(); // put the "read cursor" back for the next middleware

            if ($body !== '') {
                // "application/json; charset=utf-8" -> "application/json"
                $contentType = strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'))[0]));

                if (!in_array($contentType, $this->consumable, true)) {
                    throw new ApiException(415, 'Request body must be sent as: ' . implode(', ', $this->consumable));
                }

                // ---- 3. Is the JSON actually valid? ----
                json_decode($body);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new ApiException(400, 'Request body is not valid JSON');
                }
            }
        }

        return $handler->handle($request);
    }

    // True if the Accept header lists something we can produce (or a wildcard).
    private function acceptsAnyOf(string $accept): bool
    {
        foreach (explode(',', $accept) as $part) {
            $type = trim(explode(';', $part)[0]); // remove ";q=0.8"
            if ($type === '*/*' || $type === 'application/*' || in_array($type, $this->producible, true)) {
                return true;
            }
        }
        return false;
    }
}