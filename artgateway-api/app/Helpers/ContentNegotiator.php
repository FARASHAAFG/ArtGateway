<?php

declare(strict_types=1);

namespace App\Helpers;

use Psr\Http\Message\ServerRequestInterface as Request;

class ContentNegotiator
{
    /**
     * Supported response formats and their media types.
     */
    private const SUPPORTED_FORMATS = [
        'json' => [
            'mediaType' => 'application/json',
            'aliases' => ['application/json']
        ],
        'xml' => [
            'mediaType' => 'application/xml',
            'aliases' => ['application/xml', 'text/xml']
        ],
        'yaml' => [
            'mediaType' => 'application/yaml',
            'aliases' => [
                'application/yaml',
                'text/yaml',
                'application/x-yaml',
                'text/x-yaml'
            ]
        ]
    ];

    /**
     * Negotiate the response format using the Accept header.
     *
     * @return array{
     *     success: bool,
     *     format?: string,
     *     contentType?: string,
     *     error?: string,
     *     status?: int
     * }
     */
    public function negotiate(Request $request): array
    {
        // 1. Retrieve the Accept header
        $accept = trim($request->getHeaderLine('Accept'));

        // 2. Default to JSON if Accept is missing
        if ($accept === '') {
            return $this->successResult('json');
        }

        // 3. Parse the accepted media types
        $acceptedTypes = $this->parseAcceptHeader($accept);

        if (empty($acceptedTypes)) {
            return $this->notAcceptable();
        }

        // 4. Evaluate each supported representation
        $bestFormat = null;
        $bestQuality = -1.0;

        foreach (self::SUPPORTED_FORMATS as $format => $details) {

            $quality = $this->getFormatQuality(
                $details['aliases'],
                $acceptedTypes
            );

            // q=0 means the client explicitly rejects the format
            if ($quality > $bestQuality && $quality > 0) {
                $bestQuality = $quality;
                $bestFormat = $format;
            }
        }

        // 5. Return the selected representation
        if ($bestFormat === null) {
            return $this->notAcceptable();
        }

        return $this->successResult($bestFormat);
    }

    /**
     * Parse the Accept header into media types and quality values.
     */
    private function parseAcceptHeader(string $accept): array
    {
        $acceptedTypes = [];

        foreach (explode(',', $accept) as $index => $part) {

            $segments = array_map('trim', explode(';', $part));

            $mediaType = strtolower(array_shift($segments));

            if ($mediaType === '') {
                continue;
            }

            $quality = 1.0;

            foreach ($segments as $parameter) {

                if (preg_match(
                    '/^q\s*=\s*(.+)$/i',
                    $parameter,
                    $matches
                )) {
                    $value = trim($matches[1]);

                    if (
                        !is_numeric($value) ||
                        (float) $value < 0 ||
                        (float) $value > 1
                    ) {
                        $quality = 0.0;
                    } else {
                        $quality = (float) $value;
                    }

                    break;
                }
            }

            $acceptedTypes[] = [
                'mediaType' => $mediaType,
                'quality' => $quality,
                'order' => $index
            ];
        }

        return $acceptedTypes;
    }

    /**
     * Determine the effective quality for a supported format.
     *
     * More specific media ranges take precedence over wildcards.
     */
    private function getFormatQuality(
        array $aliases,
        array $acceptedTypes
    ): float {
        $bestSpecificity = -1;
        $bestQuality = 0.0;

        foreach ($acceptedTypes as $accepted) {

            foreach ($aliases as $alias) {

                $specificity = $this->getSpecificity(
                    $accepted['mediaType'],
                    $alias
                );

                if ($specificity === -1) {
                    continue;
                }

                if ($specificity > $bestSpecificity) {
                    $bestSpecificity = $specificity;
                    $bestQuality = $accepted['quality'];
                }
            }
        }

        return $bestQuality;
    }

    /**
     * Determine whether an accepted media range matches a format.
     *
     * Returns:
     * 2 = exact match
     * 1 = subtype wildcard
     * 0 = all media types
     * -1 = no match
     */
    private function getSpecificity(
        string $acceptedType,
        string $supportedType
    ): int {
        if ($acceptedType === $supportedType) {
            return 2;
        }

        if ($acceptedType === '*/*') {
            return 0;
        }

        if (
            str_ends_with($acceptedType, '/*') &&
            explode('/', $acceptedType)[0] === explode('/', $supportedType)[0]
        ) {
            return 1;
        }

        return -1;
    }

    /**
     * Build a successful negotiation result.
     */
    private function successResult(string $format): array
    {
        return [
            'success' => true,
            'format' => $format,
            'contentType' => self::SUPPORTED_FORMATS[$format]['mediaType']
        ];
    }

    /**
     * Build an HTTP 406 result.
     */
    private function notAcceptable(): array
    {
        return [
            'success' => false,
            'error' => 'No acceptable response representation is available.',
            'status' => 406
        ];
    }
}
