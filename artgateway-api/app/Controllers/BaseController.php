<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\ApiException;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

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

    /**
     * Returns the JSON body of the request as an array.
     * Throws 400 if there is no body or it is not a JSON object like {"name": "x"}.
     */
    protected function getJsonBody(Request $request): array
    {
        $body = $request->getParsedBody();

        if (!is_array($body) || empty($body)) {
            throw new ApiException(400, 'A JSON object is required in the request body');
        }
        return $body;
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
