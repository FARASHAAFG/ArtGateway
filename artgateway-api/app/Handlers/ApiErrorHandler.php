<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Exceptions\ApiException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Throwable;

/**
 * The place where every error becomes a response.
 *
 * The CLIENT gets a short safe message. The LOG gets the full technical story.
 */
class ApiErrorHandler
{
    private LoggerInterface $logger;
    private ResponseFactoryInterface $responseFactory;

    // Short titles for the "error" field.
    private array $titles = [
        400 => 'Bad Request',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        409 => 'Conflict',
        415 => 'Unsupported Media Type',
        422 => 'Unprocessable Entity',
        500 => 'Internal Server Error',
    ];

    public function __construct(LoggerInterface $logger, ResponseFactoryInterface $responseFactory)
    {
        $this->logger = $logger;
        $this->responseFactory = $responseFactory;
    }

    // Slim calls this with these 5 parameters whenever an exception is thrown.
    public function __invoke(
        Request $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ): ResponseInterface {
        // Defaults = the "something unexpected broke" case.
        $status = 500;
        $message = 'An unexpected error occurred. Please contact support and give them the requestId.';
        $details = [];
        $headers = [];

        if ($exception instanceof ApiException) {
            // We threw it ourselves, so the message is safe to show.
            $status = $exception->getStatusCode();
            $message = $exception->getMessage();
            $details = $exception->getDetails();

        } elseif ($exception instanceof HttpNotFoundException) {
            $status = 404;
            $message = 'The requested resource was not found.';

        } elseif ($exception instanceof HttpMethodNotAllowedException) {
            $status = 405;
            $message = 'This method is not allowed on this resource.';
            // The HTTP rules say a 405 must tell the client which methods ARE allowed.
            $headers['Allow'] = implode(', ', $exception->getAllowedMethods());

        } elseif ($exception instanceof HttpBadRequestException) {
            $status = 400;
            $message = 'The request could not be understood.';

        } elseif ($exception instanceof HttpException) {
            // Any other Slim HTTP error (401, 403...). Use its code, not its message.
            $status = $exception->getCode();
            $message = $this->titles[$status] ?? 'Request failed';

        } elseif ($this->isConstraintViolation($exception)) {
            // A database rule was broken (e.g. deleting an artist who still has artworks).
            $status = 409;
            $message = 'This action conflicts with existing data (for example, related records still exist).';
        }

        $requestId = $request->getAttribute('requestId');

        $this->writeLog($request, $exception, $status, $requestId);

        // Build the body. "details" and "requestId" are only added when we have them.
        $body = [
            'status' => $status,
            'error' => $this->titles[$status] ?? 'Error',
            'message' => $message,
        ];
        if (!empty($details)) {
            $body['details'] = $details;
        }
        if ($requestId !== null) {
            $body['requestId'] = $requestId;
        }

        $response = $this->responseFactory->createResponse($status);
        $response->getBody()->write(json_encode($body, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR));
        $response = $response->withHeader('Content-Type', APP_MEDIA_TYPE_JSON);

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }

    // BaseModel wraps PDOExceptions in a RuntimeException, so look at the original one.
    // SQLSTATE 23000 = "integrity constraint violation" (foreign key, duplicate key...).
    private function isConstraintViolation(Throwable $exception): bool
    {
        $original = $exception->getPrevious();
        if ($exception instanceof \PDOException) {
            $original = $exception;
        }
        return $original instanceof \PDOException && (string) $original->getCode() === '23000';
    }

    // Everything technical goes in the log file only.
    private function writeLog(Request $request, Throwable $exception, int $status, ?string $requestId): void
    {
        $context = [
            'requestId' => $requestId,
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
            'status' => $status,
            'exception' => get_class($exception),
            'exceptionMessage' => $exception->getMessage(),
        ];

        if ($status >= 500) {
            // Real bugs: log where it happened and the stack trace.
            $context['file'] = $exception->getFile() . ':' . $exception->getLine();
            $context['trace'] = $exception->getTraceAsString();
            $this->logger->error('unhandled error', $context);
        } else {
            // Client mistakes (404, 422...) are normal, so just a warning without a trace.
            $this->logger->warning('request rejected', $context);
        }
    }
}