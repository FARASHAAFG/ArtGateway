<?php

declare(strict_types=1);

namespace App\Helpers;

use Psr\Http\Message\ServerRequestInterface as Request;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

class RequestProcessor
{
    /**
     * Retrieve and validate a JSON request body.
     *
     * @return array{
     *     success: bool,
     *     data?: array,
     *     error?: string,
     *     status?: int
     * }
     */
    public function processJsonBody(Request $request): array
    {
        // 1. Check the Content-Type header
        $contentType = strtolower(
            trim(
                explode(
                    ';',
                    $request->getHeaderLine('Content-Type')
                )[0]
            )
        );

        // Accept application/json and application/*+json
        $isJson = $contentType === 'application/json'
            || preg_match(
                '/^application\/[a-z0-9.!#$&^_-]+\+json$/',
                $contentType
            ) === 1;

        if (!$isJson) {
            return [
                'success' => false,
                'error' => 'Content-Type must be application/json.',
                'status' => 415
            ];
        }

        // 2. Retrieve the raw request body
        $rawBody = (string) $request->getBody();

        if (trim($rawBody) === '') {
            return [
                'success' => false,
                'error' => 'Request body cannot be empty.',
                'status' => 400
            ];
        }

        // 3. Decode JSON into an object first
        try {
            $decoded = json_decode(
                $rawBody,
                false,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $exception) {
            return [
                'success' => false,
                'error' => 'Request body contains invalid JSON.',
                'status' => 400
            ];
        }

        // 4. Ensure the JSON root is an object
        if (!is_object($decoded)) {
            return [
                'success' => false,
                'error' => 'Request body must be a JSON object.',
                'status' => 400
            ];
        }

        // 5. Convert the JSON object into an associative array
        $data = json_decode(
            $rawBody,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        // 6. Return the processed data
        return [
            'success' => true,
            'data' => $data
        ];
    }

    /**
     * Retrieve and validate an XML request body.
     *
     * @return array{
     *     success: bool,
     *     data?: array,
     *     error?: string,
     *     status?: int
     * }
     */
    public function processXmlBody(Request $request): array
    {
        // 1. Check the Content-Type header
        $contentType = strtolower(
            trim(
                explode(';', $request->getHeaderLine('Content-Type'))[0]
            )
        );

        $isXml = in_array(
            $contentType,
            ['application/xml', 'text/xml'],
            true
        ) || preg_match(
                '/^application\/[a-z0-9.!#$&^_-]+\+xml$/',
                $contentType
            ) === 1;

        if (!$isXml) {
            return [
                'success' => false,
                'error' => 'Content-Type must be an XML media type.',
                'status' => 415
            ];
        }

        // 2. Retrieve the raw request body
        $rawBody = (string) $request->getBody();

        if (trim($rawBody) === '') {
            return [
                'success' => false,
                'error' => 'Request body cannot be empty.',
                'status' => 400
            ];
        }

        // 3. Parse the XML document safely
        $document = new DOMDocument();

        $previousErrorSetting = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadXML(
                $rawBody,
                LIBXML_NONET | LIBXML_NOBLANKS
            );

            // Reject malformed XML and documents with a DOCTYPE
            if (!$loaded || $document->doctype !== null) {
                return [
                    'success' => false,
                    'error' => 'Request body contains invalid or unsafe XML.',
                    'status' => 400
                ];
            }

            // 4. Ensure that an XML root element exists
            $root = $document->documentElement;

            if (!$root instanceof DOMElement) {
                return [
                    'success' => false,
                    'error' => 'XML document must contain a root element.',
                    'status' => 400
                ];
            }

            // 5. Convert XML elements into an associative array
            $data = $this->xmlElementToArray($root);

            return [
                'success' => true,
                'data' => $data
            ];

        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorSetting);
        }
    }
    /**
     * Convert an XML element and its children into an array.
     */
    private function xmlElementToArray(DOMElement $element): array
    {
        $data = [];

        // Process child elements
        foreach ($element->childNodes as $child) {

            if (!$child instanceof DOMElement) {
                continue;
            }

            $name = $child->tagName;

            $hasChildren = false;

            foreach ($child->childNodes as $node) {
                if ($node instanceof DOMElement) {
                    $hasChildren = true;
                    break;
                }
            }

            // Convert nested elements or retrieve text content
            $value = $hasChildren
                ? $this->xmlElementToArray($child)
                : trim($child->textContent);

            // Support repeated XML elements
            if (array_key_exists($name, $data)) {

                if (!is_array($data[$name]) || !array_is_list($data[$name])) {
                    $data[$name] = [$data[$name]];
                }

                $data[$name][] = $value;

            } else {
                $data[$name] = $value;
            }
        }

        return $data;
    }
    /**
     * Retrieve and validate a YAML request body.
     *
     * @return array{
     *     success: bool,
     *     data?: array,
     *     error?: string,
     *     status?: int
     * }
     */
    public function processYamlBody(Request $request): array
    {
        // 1. Check the Content-Type header
        $contentType = strtolower(
            trim(
                explode(';', $request->getHeaderLine('Content-Type'))[0]
            )
        );

        $isYaml = in_array(
            $contentType,
            [
                'application/yaml',
                'text/yaml',
                'application/x-yaml',
                'text/x-yaml'
            ],
            true
        );

        if (!$isYaml) {
            return [
                'success' => false,
                'error' => 'Content-Type must be a YAML media type.',
                'status' => 415
            ];
        }

        // 2. Retrieve the raw request body
        $rawBody = (string) $request->getBody();

        if (trim($rawBody) === '') {
            return [
                'success' => false,
                'error' => 'Request body cannot be empty.',
                'status' => 400
            ];
        }

        // 3. Parse the YAML document
        try {
            $data = Yaml::parse($rawBody);

        } catch (ParseException $exception) {
            return [
                'success' => false,
                'error' => 'Request body contains invalid YAML.',
                'status' => 400
            ];
        }

        // 4. Ensure the YAML root is a mapping
        if (!is_array($data) || array_is_list($data)) {

            // An empty array is also accepted as an empty mapping
            if ($data !== []) {
                return [
                    'success' => false,
                    'error' => 'Request body must contain a YAML mapping.',
                    'status' => 400
                ];
            }
        }

        // 5. Return the processed data
        return [
            'success' => true,
            'data' => $data
        ];
    }
}
