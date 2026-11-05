<?php

declare(strict_types=1);

namespace App\Helpers;

use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

class ResponseSerializer
{
    /**
     * Serialize PHP data into the requested representation.
     *
     * @return array{
     *     body: string,
     *     contentType: string
     * }
     */
    public function serialize(array $data, string $format): array
    {
        // Normalize the requested format
        $format = strtolower(trim($format));

        // Select the appropriate serializer
        return match ($format) {
            'json', 'application/json' => [
                'body' => $this->serializeJson($data),
                'contentType' => 'application/json'
            ],

            'xml', 'application/xml', 'text/xml' => [
                'body' => $this->serializeXml($data),
                'contentType' => 'application/xml'
            ],

            'yaml', 'yml', 'application/yaml',
            'text/yaml', 'application/x-yaml', 'text/x-yaml' => [
                'body' => $this->serializeYaml($data),
                'contentType' => 'application/yaml'
            ],

            default => throw new InvalidArgumentException(
                "Unsupported response format: $format"
            )
        };
    }

    /**
     * Serialize data into JSON.
     */
    private function serializeJson(array $data): string
    {
        return json_encode(
            $data,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_THROW_ON_ERROR
        );
    }

    /**
     * Serialize data into XML.
     */
    private function serializeXml(array $data): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');

        $document->formatOutput = true;

        // Create the root element
        $root = $document->createElement('response');

        $document->appendChild($root);

        // Handle associative arrays and collections
        if (array_is_list($data)) {

            foreach ($data as $item) {
                $this->appendXmlElement(
                    $document,
                    $root,
                    'item',
                    $item
                );
            }

        } else {

            foreach ($data as $key => $value) {
                $this->appendXmlElement(
                    $document,
                    $root,
                    (string) $key,
                    $value
                );
            }
        }

        return $document->saveXML();
    }

    /**
     * Recursively append values to an XML document.
     */
    private function appendXmlElement(
        DOMDocument $document,
        DOMElement $parent,
        string $name,
        mixed $value
    ): void {
        // Ensure the element name is valid
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9._-]*$/D', $name)) {
            throw new InvalidArgumentException(
                "Invalid XML element name: $name"
            );
        }

        // Create the element
        $element = $document->createElement($name);

        $parent->appendChild($element);

        // Handle nested arrays
        if (is_array($value)) {

            foreach ($value as $key => $item) {

                // Lists use <item> elements
                $childName = array_is_list($value)
                    ? 'item'
                    : (string) $key;

                $this->appendXmlElement(
                    $document,
                    $element,
                    $childName,
                    $item
                );
            }

            return;
        }

        // Handle null values as empty elements
        if ($value === null) {
            return;
        }

        // Handle boolean values consistently
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }

        // Only scalar values are supported
        if (!is_scalar($value)) {
            throw new InvalidArgumentException(
                "Unsupported XML value for element: $name"
            );
        }

        // DOMDocument handles XML escaping
        $element->appendChild(
            $document->createTextNode((string) $value)
        );
    }

    /**
     * Serialize data into YAML.
     */
    private function serializeYaml(array $data): string
    {
        return Yaml::dump(
            $data,
            4,
            2,
            Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK
        );
    }
}
