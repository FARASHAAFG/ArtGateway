<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\ApiException;
use App\Handlers\ApiErrorHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

final class ApiErrorHandlerTest extends TestCase
{
    public function testApiExceptionProducesStructuredJsonError(): void
    {
        $factory = new Psr17Factory();
        $request = $factory
            ->createServerRequest('GET', 'http://localhost/artworks/12')
            ->withAttribute('requestId', 'test-request-123');
        $handler = new ApiErrorHandler(new NullLogger(), $factory);

        $response = $handler(
            $request,
            new ApiException(422, 'Validation failed', ['title' => 'title is required']),
            false,
            false,
            false
        );
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('Unprocessable Entity', $body['error']);
        self::assertSame(['title' => 'title is required'], $body['details']);
        self::assertSame('test-request-123', $body['requestId']);
    }

    public function testDatabaseConstraintErrorsBecomeSanitizedConflicts(): void
    {
        $factory = new Psr17Factory();
        $request = $factory
            ->createServerRequest('POST', 'http://localhost/register')
            ->withAttribute('requestId', 'test-conflict-123');
        $databaseError = new \PDOException('Duplicate entry secret@example.test in SQL query', 23000);
        $exception = new RuntimeException('database operation failed: private SQL', 0, $databaseError);

        $response = (new ApiErrorHandler(new NullLogger(), $factory))(
            $request,
            $exception,
            false,
            false,
            false
        );
        $bodyText = (string) $response->getBody();
        $body = json_decode($bodyText, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('Conflict', $body['error']);
        self::assertStringNotContainsString('secret@example.test', $bodyText);
        self::assertStringNotContainsString('private SQL', $bodyText);
    }

    public function testUnexpectedErrorsDoNotExposeSqlOrStackDetails(): void
    {
        $factory = new Psr17Factory();
        $request = $factory->createServerRequest('GET', 'http://localhost/artworks');
        $exception = new RuntimeException('SQL connection failed: private credentials');

        $response = (new ApiErrorHandler(new NullLogger(), $factory))(
            $request,
            $exception,
            false,
            false,
            false
        );
        $bodyText = (string) $response->getBody();
        $body = json_decode($bodyText, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('Internal Server Error', $body['error']);
        self::assertStringNotContainsString('SQL connection failed', $bodyText);
        self::assertStringNotContainsString('private credentials', $bodyText);
        self::assertStringNotContainsString('RuntimeException', $bodyText);
    }
}
