<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\ResponseSerializer;
use DOMDocument;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class ResponseSerializerTest extends TestCase
{
    public function testSerializesAllSupportedRepresentations(): void
    {
        $serializer = new ResponseSerializer();
        $data = ['name' => 'Test Museum', 'website' => null, 'open' => true];

        $json = $serializer->serialize($data, 'json');
        self::assertSame('application/json', $json['contentType']);
        self::assertSame($data, json_decode($json['body'], true, 512, JSON_THROW_ON_ERROR));

        $xml = $serializer->serialize($data, 'xml');
        self::assertSame('application/xml', $xml['contentType']);
        $document = new DOMDocument();
        self::assertTrue($document->loadXML($xml['body']));
        self::assertSame('Test Museum', $document->getElementsByTagName('name')->item(0)?->textContent);

        $yaml = $serializer->serialize($data, 'yaml');
        self::assertSame('application/yaml', $yaml['contentType']);
        self::assertSame($data, Yaml::parse($yaml['body']));
    }

    public function testXmlSerializationRejectsInvalidElementNames(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ResponseSerializer())->serialize(['bad:tag' => 'value'], 'xml');
    }
}
