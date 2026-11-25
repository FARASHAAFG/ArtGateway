<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exceptions\ApiException;
use App\Helpers\ContentNegotiator;
use App\Helpers\RequestProcessor;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class ContentNegotiationMiddleware implements MiddlewareInterface
{
    private ContentNegotiator $negotiator;
    private RequestProcessor $requestProcessor;

    public function __construct(?RequestProcessor $requestProcessor = null)
    {
        $this->negotiator = new ContentNegotiator();
        $this->requestProcessor = $requestProcessor ?? new RequestProcessor();
    }

    public function process(Request $request, RequestHandler $handler): ResponseInterface
    {
        if (preg_match('~(?:^|/)phpinfo/?$~', $request->getUri()->getPath()) === 1) {
            return $handler->handle($request);
        }

        // ---- 1. Accept header (what the client wants back) ----
        $result = $this->negotiator->negotiate($request);

        if (!$result['success']) {
            throw new ApiException(406, 'This API can only return: application/json, application/xml, application/yaml');
        }

        // Remember the chosen format for the rest of the request.
        $request = $request->withAttribute('response_format', $result['format']);

        // ---- 2. Parse mutation bodies before controllers validate them ----
        if (in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'], true)) {
            $rawBody = (string) $request->getBody();
            $request->getBody()->rewind();

            if (trim($rawBody) === '' && $request->getParsedBody() !== null) {
                $format = $this->requestProcessor->formatForContentType(
                    $request->getHeaderLine('Content-Type')
                );
                if ($format === null) {
                    throw new ApiException(415, 'Request Content-Type must be JSON, XML, or YAML.');
                }

                $request = $request->withAttribute('request_body_format', $format);
            } else {
                if (trim($rawBody) === '' && trim($request->getHeaderLine('Content-Type')) === '') {
                    throw new ApiException(400, 'Request body cannot be empty.');
                }

                $processed = $this->requestProcessor->processBody($request);
                if (!$processed['success']) {
                    throw new ApiException($processed['status'], $processed['error']);
                }

                $request = $request
                    ->withParsedBody($processed['data'])
                    ->withAttribute('request_body_format', $processed['format']);
                $request->getBody()->rewind();
            }
        }

        return $handler->handle($request);
    }
}