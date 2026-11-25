<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\ApiException;
use App\Helpers\RequestValidator;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
//use Psr\Http\Message\ServerRequestInterface as Request;
use App\Helpers\ResponseSerializer;

abstract class BaseController
{
    protected const MAX_DATABASE_ID = 4294967295;

    public function __construct() {}
    protected function renderJson(Response $response, array $data, int $status_code = 200): Response
    {
        $payload = json_encode(
            $data,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
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
    /**
     * Returns a parsed JSON, XML, or YAML object body as an array.
     */
    protected function getRequestBody(Request $request): array
    {
        $body = $request->getParsedBody();

        if (!is_array($body) || ($body !== [] && array_is_list($body))) {
            throw new ApiException(400, 'An object or mapping is required in the request body');
        }
        return $body;
    }

    protected function positivePathId(array $args): int
    {
        $validator = new RequestValidator($args);
        $validator->integer('id', true, 1, self::MAX_DATABASE_ID);
        if ($validator->hasErrors()) {
            throw new ApiException(400, 'Validation failed', $validator->getErrors());
        }

        return $validator->getValidated()['id'];
    }

    /**
     * Throws a 422 error if the $errors array has anything in it.
     * $errors looks like: ['name' => 'name is required', 'birthYear' => 'must be a whole number']
     */
    protected function throwIfInvalid(array $errors): void
    {
        if (!empty($errors)) {
            throw new ApiException(422, 'Validation failed', $errors);
        }
    }

    protected function buildPaginatedResult(
        Request $request,
        array $data,
        int $page,
        int $limit,
        int $total
    ): array {
        $totalPages = (int) ceil($total / $limit);
        $queryParams = ['limit' => $limit];
        $makePageUrl = static function (int $pageNumber) use ($request, $queryParams): string {
            $query = http_build_query(array_merge($queryParams, ['page' => $pageNumber]));
            return (string) $request->getUri()->withQuery($query);
        };

        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total_items' => $total,
                'total_pages' => $totalPages,
            ],
            'links' => [
                'first' => $totalPages > 0 ? $makePageUrl(1) : null,
                'prev' => $page > 1 && $totalPages > 0
                    ? $makePageUrl(min($page - 1, $totalPages))
                    : null,
                'next' => $page < $totalPages ? $makePageUrl($page + 1) : null,
                'last' => $totalPages > 0 ? $makePageUrl($totalPages) : null,
            ],
        ];
    }

    /**
     * Checks one text field. Adds a message to $errors if it is wrong.
     * Returns the cleaned value (trimmed), or null if the field is empty/missing.
     */
    protected function checkString(array $data, string $field, bool $required, int $maxLength, array &$errors): ?string
    {
        // Field missing or null
        if (!isset($data[$field])) {
            if ($required) {
                $errors[$field] = "$field is required";
            }
            return null;
        }

        // Must really be text (not a number, array, true/false...)
        if (!is_string($data[$field])) {
            $errors[$field] = "$field must be text";
            return null;
        }

        $value = trim($data[$field]);

        if ($value === '') {
            if ($required) {
                $errors[$field] = "$field cannot be empty";
            }
            return null;
        }

        if (mb_strlen($value) > $maxLength) {
            $errors[$field] = "$field must be at most $maxLength characters";
            return null;
        }

        return $value;
    }

    /**
     * Checks one whole-number field. Adds a message to $errors if it is wrong.
     * Returns the number, or null if the field is empty/missing.
     */
    protected function checkInt(array $data, string $field, bool $required, int $min, int $max, array &$errors): ?int
    {
        if (!isset($data[$field])) {
            if ($required) {
                $errors[$field] = "$field is required";
            }
            return null;
        }

        // Must be a real JSON number like 1890 (not the text "1890" or 18.5)
        if (!is_int($data[$field])) {
            $errors[$field] = "$field must be a whole number";
            return null;
        }

        if ($data[$field] < $min || $data[$field] > $max) {
            $errors[$field] = "$field must be between $min and $max";
            return null;
        }

        return $data[$field];
    }
}
