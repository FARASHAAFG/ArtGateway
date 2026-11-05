<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

/**
 * Talks to the Art Institute of Chicago public API (https://api.artic.edu/docs).
 *
 * FAILURE POLICY: this class NEVER throws. Whatever goes wrong at the museum
 * (slow, down, strange answer) the caller just gets status "unavailable".
 * Our own API then still answers 200 with our database data and "external": null.
 * Reason: the museum is optional extra info; our data must not depend on it.
 *
 * Return value is always an array:
 *   ['status' => 'ok',          'data' => [ ...cleaned museum info... ]]
 *   ['status' => 'not_found']   (the museum answered but has no matching artwork)
 *   ['status' => 'unavailable'] (timeout, error, bad answer...)
 */
class ArtInstituteClient
{
    // We never accept more than this many bytes from the museum (protects our memory).
    private const MAX_RESPONSE_BYTES = 262144; // 256 KB

    // Only these fields are requested, and only these are ever used.
    private const FIELDS = 'id,title,artist_title,date_display,medium_display,place_of_origin,department_title,short_description,is_public_domain,image_id';

    private Client $http;
    private LoggerInterface $logger;

    public function __construct(array $settings, LoggerInterface $logger)
    {
        $this->logger = $logger;

        $options = [
            'base_uri' => $settings['base_uri'],
            // Time limits: without them a slow museum would freeze OUR api too.
            'timeout' => $settings['timeout'],
            'connect_timeout' => $settings['connect_timeout'],
            // We look at the status code ourselves instead of getting exceptions.
            'http_errors' => false,
            // Do not follow redirects to other servers.
            'allow_redirects' => false,
            'headers' => [
                'Accept' => 'application/json',
                'AIC-User-Agent' => $settings['user_agent'],
            ],
        ];

        // Ask cURL to stop downloading if the answer is bigger than our limit.
        // (Only added when the cURL extension is installed.)
        if (defined('CURLOPT_MAXFILESIZE')) {
            $options['curl'] = [CURLOPT_MAXFILESIZE => self::MAX_RESPONSE_BYTES];
        }

        $this->http = new Client($options);
    }

    /**
     * Looks for the artwork in the museum's collection.
     *
     * @param string $title       title of OUR artwork
     * @param string $artistName  name of OUR artist ('' if unknown)
     * @param string $requestId   only used to link the log lines to the request
     */
    public function findArtwork(string $title, string $artistName, string $requestId = ''): array
    {
        // Our data is trusted, but keep what we send to the museum short and clean.
        $title = $this->cleanText($title, 100);
        $artistName = $this->cleanText($artistName, 100);

        if ($title === null) {
            return ['status' => 'not_found'];
        }

        try {
            $response = $this->http->request('GET', 'artworks/search', [
                'query' => [
                    'q' => trim($title . ' ' . $artistName),
                    'limit' => 5,               // we only need the best few matches
                    'fields' => self::FIELDS,   // only the fields we use
                ],
            ]);

            // ---- Check the answer before trusting it ----
            if ($response->getStatusCode() !== 200) {
                return $this->unavailable($requestId, 'upstream status ' . $response->getStatusCode());
            }

            $contentType = strtolower($response->getHeaderLine('Content-Type'));
            if (!str_contains($contentType, 'json')) {
                return $this->unavailable($requestId, 'upstream did not send JSON');
            }

            // Second safety net: even if cURL let it through, refuse anything too big.
            $body = (string) $response->getBody();
            if (strlen($body) > self::MAX_RESPONSE_BYTES) {
                return $this->unavailable($requestId, 'upstream response too large');
            }

            $json = json_decode($body, true, 10); // 10 = max nesting depth
            if (!is_array($json) || !isset($json['data']) || !is_array($json['data'])) {
                return $this->unavailable($requestId, 'upstream sent unexpected JSON');
            }

            // The museum tells us the base address of its images.
            $iiifUrl = $json['config']['iiif_url'] ?? '';

            return $this->pickBestMatch($json['data'], $title, (string) $artistName, $iiifUrl);

        } catch (\Throwable $e) {
            // Timeouts, DNS errors, connection refused... all end up here.
            return $this->unavailable($requestId, get_class($e) . ': ' . $e->getMessage());
        }
    }

    // Finds the first search result that really is our artwork.
    private function pickBestMatch(array $results, string $title, string $artistName, string $iiifUrl): array
    {
        // The artist's last word is enough to recognise them ("Vincent van Gogh" -> "gogh").
        $nameParts = explode(' ', $this->normalize($artistName));
        $lastName = end($nameParts);

        foreach ($results as $item) {
            if (!is_array($item)) {
                continue;
            }

            // Title must be very similar to ours...
            similar_text($this->normalize($title), $this->normalize((string) ($item['title'] ?? '')), $percent);
            if ($percent < 80) {
                continue;
            }

            // ...and the artist must match too (when we know ours).
            if ($lastName !== '' && !str_contains($this->normalize((string) ($item['artist_title'] ?? '')), $lastName)) {
                continue;
            }

            return ['status' => 'ok', 'data' => $this->buildResult($item, $iiifUrl)];
        }

        return ['status' => 'not_found'];
    }

    // Builds the small, clean object that we send to OUR clients.
    // Nothing from the museum is copied blindly: every value is cut to a safe length.
    private function buildResult(array $item, string $iiifUrl): array
    {
        $id = isset($item['id']) ? (int) $item['id'] : 0;
        $isPublicDomain = ($item['is_public_domain'] ?? false) === true;

        // Image: only for public domain works (the museum asks for this), and only
        // if the pieces look exactly like what we expect.
        $imageUrl = null;
        $imageId = (string) ($item['image_id'] ?? '');
        if (
            $isPublicDomain
            && preg_match('/^[a-f0-9-]{36}$/', $imageId)
            && str_starts_with($iiifUrl, 'https://www.artic.edu/')
        ) {
            $imageUrl = rtrim($iiifUrl, '/') . '/' . $imageId . '/full/843,/0/default.jpg';
        }

        return [
            'source' => 'Art Institute of Chicago',
            'sourceUrl' => $id > 0 ? 'https://www.artic.edu/artworks/' . $id : null,
            'attribution' => 'Data courtesy of the Art Institute of Chicago (CC0 1.0)',
            'title' => $this->cleanText((string) ($item['title'] ?? ''), 255),
            'artist' => $this->cleanText((string) ($item['artist_title'] ?? ''), 150),
            'dateDisplay' => $this->cleanText((string) ($item['date_display'] ?? ''), 100),
            'medium' => $this->cleanText((string) ($item['medium_display'] ?? ''), 255),
            'placeOfOrigin' => $this->cleanText((string) ($item['place_of_origin'] ?? ''), 100),
            'department' => $this->cleanText((string) ($item['department_title'] ?? ''), 100),
            'description' => $this->cleanText((string) ($item['short_description'] ?? ''), 500),
            'isPublicDomain' => $isPublicDomain,
            'imageUrl' => $imageUrl,
        ];
    }

    // Removes HTML tags, extra spaces and cuts the text to a maximum length.
    // Empty text becomes null.
    private function cleanText(string $text, int $maxLength): ?string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)));
        if ($text === '') {
            return null;
        }
        return mb_substr($text, 0, $maxLength);
    }

    // Lower case, letters and numbers only, so "Van Gogh!" and "van gogh" compare equal.
    private function normalize(string $text): string
    {
        return trim(preg_replace('/[^a-z0-9 ]+/', '', strtolower($text)));
    }

    // Writes the technical reason in the log (never sent to the client) and returns "unavailable".
    private function unavailable(string $requestId, string $reason): array
    {
        $this->logger->warning('external service unavailable', [
            'requestId' => $requestId,
            'service' => 'artic',
            'reason' => $reason,
        ]);
        return ['status' => 'unavailable'];
    }
}