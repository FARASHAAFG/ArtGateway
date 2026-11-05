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
    // We never read more than this many bytes from the museum (protects our memory).
    private const MAX_RESPONSE_BYTES = 262144; // 256 KB

    // Only these fields are requested, and only these are ever used.
    private const FIELDS = 'id,title,artist_title,date_display,medium_display,place_of_origin,department_title,short_description,is_public_domain,image_id';

    // Fields requested when searching for a person (artist). The museum calls them "agents".
    private const AGENT_FIELDS = 'id,title,sort_title,alt_titles,birth_date,death_date,description,is_artist';

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

        $json = $this->search('artworks/search', [
            'q' => trim($title . ' ' . $artistName),
            'limit' => 5,               // we only need the best few matches
            'fields' => self::FIELDS,   // only the fields we use
        ], $requestId);

        if ($json === null) {
            return ['status' => 'unavailable'];
        }

        // The museum tells us the base address of its images.
        $iiifUrl = $json['config']['iiif_url'] ?? '';

        return $this->pickBestMatch($json['data'], $title, (string) $artistName, $iiifUrl);
    }

    /**
     * Looks for the ARTIST (the museum calls people "agents") in the museum's records.
     * Same return values as findArtwork().
     */
    public function findArtist(string $artistName, string $requestId = ''): array
    {
        $artistName = $this->cleanText($artistName, 100);

        if ($artistName === null) {
            return ['status' => 'not_found'];
        }

        $json = $this->search('agents/search', [
            'q' => $artistName,
            'limit' => 5,
            'fields' => self::AGENT_FIELDS,
        ], $requestId);

        if ($json === null) {
            return ['status' => 'unavailable'];
        }

        foreach ($json['data'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            // The museum also has organisations in this list. If it tells us
            // this one is not an artist, skip it.
            if (array_key_exists('is_artist', $item) && $item['is_artist'] === false) {
                continue;
            }

            // The name must be very similar to ours (check the main name and other names).
            $names = [(string) ($item['title'] ?? '')];
            if (isset($item['alt_titles']) && is_array($item['alt_titles'])) {
                foreach (array_slice($item['alt_titles'], 0, 10) as $alt) {
                    $names[] = (string) $alt;
                }
            }

            foreach ($names as $name) {
                similar_text($this->normalize($artistName), $this->normalize($name), $percent);
                if ($percent >= 85) {
                    return ['status' => 'ok', 'data' => $this->buildArtistResult($item)];
                }
            }
        }

        return ['status' => 'not_found'];
    }

    /**
     * Sends ONE request to the museum and checks the answer.
     * Returns the decoded JSON (it always has a "data" list), or null when
     * anything is wrong. Null means "unavailable"; the reason goes in the log.
     */
    private function search(string $path, array $query, string $requestId): ?array
    {
        try {
            $response = $this->http->request('GET', $path, ['query' => $query]);

            // ---- Check the answer before trusting it ----
            if ($response->getStatusCode() !== 200) {
                $this->logUnavailable($requestId, 'upstream status ' . $response->getStatusCode());
                return null;
            }

            $contentType = strtolower($response->getHeaderLine('Content-Type'));
            if (!str_contains($contentType, 'json')) {
                $this->logUnavailable($requestId, 'upstream did not send JSON');
                return null;
            }

            // Second safety net: even if cURL let it through, refuse anything too big.
            $body = (string) $response->getBody();
            if (strlen($body) > self::MAX_RESPONSE_BYTES) {
                $this->logUnavailable($requestId, 'upstream response too large');
                return null;
            }

            $json = json_decode($body, true, 10); // 10 = max nesting depth
            if (!is_array($json) || !isset($json['data']) || !is_array($json['data'])) {
                $this->logUnavailable($requestId, 'upstream sent unexpected JSON');
                return null;
            }

            return $json;

        } catch (\Throwable $e) {
            // Timeouts, DNS errors, connection refused... all end up here.
            $this->logUnavailable($requestId, get_class($e) . ': ' . $e->getMessage());
            return null;
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

    // Builds the small, clean object about the ARTIST that we send to OUR clients.
    private function buildArtistResult(array $item): array
    {
        $id = isset($item['id']) ? (int) $item['id'] : 0;

        return [
            'source' => 'Art Institute of Chicago',
            'sourceUrl' => $id > 0 ? 'https://www.artic.edu/artists/' . $id : null,
            'attribution' => 'Data courtesy of the Art Institute of Chicago (CC0 1.0)',
            'name' => $this->cleanText((string) ($item['title'] ?? ''), 150),
            'birthYear' => $this->cleanYear($item['birth_date'] ?? null),
            'deathYear' => $this->cleanYear($item['death_date'] ?? null),
            'description' => $this->cleanText((string) ($item['description'] ?? ''), 1000),
        ];
    }

    // A year must be a whole number between -3000 and 3000. Anything else becomes null.
    private function cleanYear($value): ?int
    {
        if (is_int($value) || (is_string($value) && preg_match('/^-?\d{1,4}$/', $value))) {
            $year = (int) $value;
            if ($year >= -3000 && $year <= 3000) {
                return $year;
            }
        }
        return null;
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

    // Writes the technical reason in the log (never sent to the client).
    private function logUnavailable(string $requestId, string $reason): void
    {
        $this->logger->warning('external service unavailable', [
            'requestId' => $requestId,
            'service' => 'artic',
            'reason' => $reason,
        ]);
    }
}
