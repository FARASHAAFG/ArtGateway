<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Log\LoggerInterface;

class RequestIdMiddleware implements MiddlewareInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function process(Request $request, RequestHandler $handler): ResponseInterface
    {
        $startTime = microtime(true);

        // Reuse an ID sent by the client only if it looks safe (stops log injection).
        $requestId = $request->getHeaderLine('X-Request-Id');
        if (!preg_match('/^[A-Za-z0-9_-]{8,64}$/', $requestId)) {
            $requestId = bin2hex(random_bytes(8)); // e.g. "9f2c41ab07d3e5c1"
        }

        // Store it on the request so the error handler can read it later.
        $request = $request->withAttribute('requestId', $requestId);

        // Run the rest of the app (routing, controllers, error handler...).
        $response = $handler->handle($request);

        $durationMs = round((microtime(true) - $startTime) * 1000, 1);
        $status = $response->getStatusCode();

        // 5xx = error level, everything else = info level.
        $level = $status >= 500 ? 'error' : 'info';
        $this->logger->log($level, 'request completed', [
            'requestId' => $requestId,
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
            'query' => $request->getUri()->getQuery(),
            'status' => $status,
            'durationMs' => $durationMs,
            'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? null,
            'userAgent' => $request->getHeaderLine('User-Agent'),
        ]);

        return $response->withHeader('X-Request-Id', $requestId);
    }
}