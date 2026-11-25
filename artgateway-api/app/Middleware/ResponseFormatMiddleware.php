<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\ContentNegotiator;
use App\Helpers\ResponseSerializer;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Log\LoggerInterface;
use Throwable;
use UnexpectedValueException;

/**
 * Serializes JSON endpoint responses into the representation requested by the client.
 */
class ResponseFormatMiddleware implements MiddlewareInterface
{
    private ResponseSerializer $serializer;

    public function __construct(
        private LoggerInterface $logger,
        private ResponseFactoryInterface $responseFactory
    ) {
        $this->serializer = new ResponseSerializer();
    }

    public function process(Request $request, RequestHandler $handler): ResponseInterface
    {
        if (preg_match('~(?:^|/)phpinfo/?$~', $request->getUri()->getPath()) === 1) {
            return $handler->handle($request);
        }

        $response = $handler->handle($request)->withAddedHeader('Vary', 'Accept');

        if ($response->getStatusCode() === 204) {
            return $response;
        }

        $negotiated = (new ContentNegotiator())->negotiate($request);
        if (!$negotiated['success'] || $negotiated['format'] === 'json') {
            return $response;
        }

        $contentType = strtolower($response->getHeaderLine('Content-Type'));
        if ($contentType === strtolower($negotiated['contentType'])) {
            return $response;
        }

        if (!str_starts_with($contentType, 'application/json')) {
            return $this->serializationFailure(
                $request,
                $negotiated['format'],
                new UnexpectedValueException('The response is not JSON.')
            );
        }

        try {
            $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                throw new UnexpectedValueException('The JSON response root is not an object or array.');
            }

            $serialized = $this->serializer->serialize($data, $negotiated['format']);

            return $response
                ->withBody((new Psr17Factory())->createStream($serialized['body']))
                ->withHeader('Content-Type', $serialized['contentType']);
        } catch (Throwable $exception) {
            return $this->serializationFailure($request, $negotiated['format'], $exception);
        }
    }

    private function serializationFailure(
        Request $request,
        string $format,
        Throwable $exception
    ): ResponseInterface {
        $requestId = $request->getAttribute('requestId');
        $this->logger->error('response serialization failed', [
            'requestId' => $requestId,
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
            'exception' => get_class($exception),
        ]);

        $error = [
            'status' => 500,
            'error' => 'Internal Server Error',
            'message' => 'The response could not be serialized.',
        ];
        if (is_string($requestId)) {
            $error['requestId'] = $requestId;
        }

        try {
            $serialized = $this->serializer->serialize($error, $format);
        } catch (Throwable) {
            $serialized = $this->staticErrorRepresentation($format, $requestId);
        }

        $response = $this->responseFactory->createResponse(500);
        $response->getBody()->write($serialized['body']);

        return $response
            ->withHeader('Content-Type', $serialized['contentType'])
            ->withHeader('Vary', 'Accept');
    }

    /**
     * Keeps the failure response in the negotiated format if a serializer itself is broken.
     *
     * @return array{body: string, contentType: string}
     */
    private function staticErrorRepresentation(string $format, mixed $requestId): array
    {
        $safeId = is_string($requestId)
            ? preg_replace('/[^A-Za-z0-9_-]/', '', $requestId)
            : '';
        $idXml = $safeId === '' ? '' : '<requestId>' . $safeId . '</requestId>';
        $idYaml = $safeId === '' ? '' : 'requestId: ' . $safeId . "\n";

        return match ($format) {
            'xml' => [
                'body' => '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
                    . '<response><status>500</status><error>Internal Server Error</error>'
                    . '<message>The response could not be serialized.</message>' . $idXml . '</response>',
                'contentType' => 'application/xml',
            ],
            'yaml' => [
                'body' => "status: 500\nerror: \"Internal Server Error\"\n"
                    . "message: \"The response could not be serialized.\"\n" . $idYaml,
                'contentType' => 'application/yaml',
            ],
            default => [
                'body' => json_encode([
                    'status' => 500,
                    'error' => 'Internal Server Error',
                    'message' => 'The response could not be serialized.',
                    ...($safeId === '' ? [] : ['requestId' => $safeId]),
                ], JSON_THROW_ON_ERROR),
                'contentType' => 'application/json',
            ],
        };
    }
}
