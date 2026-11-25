<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\RequestProcessor;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class RequestProcessorTest extends TestCase
{
    public function testParsesJsonXmlAndYamlMappings(): void
    {
        $processor = new RequestProcessor();

        $json = $processor->processBody($this->request(
            '{"name":"Test Museum","city":"Paris"}',
            'application/json'
        ));
        self::assertTrue($json['success']);
        self::assertSame(['name' => 'Test Museum', 'city' => 'Paris'], $json['data']);
        self::assertSame('json', $json['format']);

        $xml = $processor->processBody($this->request(
            '<museum><name>Test Museum</name><creationYear>1895</creationYear></museum>',
            'application/xml'
        ));
        self::assertTrue($xml['success']);
        self::assertSame(['name' => 'Test Museum', 'creationYear' => 1895], $xml['data']);
        self::assertSame('xml', $xml['format']);

        $yaml = $processor->processBody($this->request(
            "name: Test Museum\ncity: Paris\n",
            'application/yaml'
        ));
        self::assertTrue($yaml['success']);
        self::assertSame(['name' => 'Test Museum', 'city' => 'Paris'], $yaml['data']);
        self::assertSame('yaml', $yaml['format']);
    }

    public function testRejectsEmptyMalformedAndUnsupportedBodies(): void
    {
        $processor = new RequestProcessor();

        foreach ([
            ['', 'application/json', 400],
            ['{invalid', 'application/json', 400],
            ['<museum>', 'application/xml', 400],
            ["name: [unclosed\n", 'application/yaml', 400],
            ['name: Test', 'text/plain', 415],
        ] as [$body, $contentType, $status]) {
            $result = $processor->processBody($this->request($body, $contentType));
            self::assertFalse($result['success'], $contentType . ' body');
            self::assertSame($status, $result['status']);
        }
    }

    public function testRejectsXmlDoctypesAndYamlObjectTags(): void
    {
        $processor = new RequestProcessor();

        $xml = $processor->processBody($this->request(
            '<!DOCTYPE museum [<!ENTITY x SYSTEM "file:///etc/passwd">]><museum><name>&x;</name></museum>',
            'application/xml'
        ));
        self::assertFalse($xml['success']);
        self::assertSame(400, $xml['status']);

        $yaml = $processor->processBody($this->request(
            "!php/object 'O:8:\"stdClass\":0:{}'\n",
            'application/yaml'
        ));
        self::assertFalse($yaml['success']);
        self::assertSame(400, $yaml['status']);
    }

    public function testRejectsNonObjectJsonRoots(): void
    {
        $result = (new RequestProcessor())->processBody(
            $this->request('["not","a","mapping"]', 'application/json')
        );

        self::assertFalse($result['success']);
        self::assertSame(400, $result['status']);
    }

    private function request(string $body, string $contentType): \Psr\Http\Message\ServerRequestInterface
    {
        $factory = new Psr17Factory();
        return $factory
            ->createServerRequest('POST', 'http://localhost/museums')
            ->withHeader('Content-Type', $contentType)
            ->withBody($factory->createStream($body));
    }
}
