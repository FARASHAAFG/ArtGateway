<?php

declare(strict_types=1);

namespace App\Middleware;

use Nyholm\Psr7\Response;
use App\Helpers\ContentNegotiator;
//use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class ContentNegotiationMiddleware implements MiddlewareInterface
{
    private ContentNegotiator $negotiator;

    public function __construct()
    {
        $this->negotiator = new ContentNegotiator();
    }

    public function process(
        Request $request,
        RequestHandler $handler
    ): Response {

        // Determine which response format the client accepts.
        $result = $this->negotiator->negotiate($request);

        // If no supported format is acceptable, return 406.
        if (!$result['success']) {

            $response = new Response();

            $error = [
                'status' => 406,
                'error' => 'Not Acceptable',
                'message' => 'The requested response format is not supported.'
            ];

            $response->getBody()->write(
                json_encode($error, JSON_PRETTY_PRINT)
            );

            return $response
                ->withStatus(406)
                ->withHeader('Content-Type', 'application/json');
        }

        // Store the selected format in the request.
        $request = $request->withAttribute(
            'response_format',
            $result['format']
        );

        // Continue to the next middleware/controller.
        return $handler->handle($request);
    }
}