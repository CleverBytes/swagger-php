<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests;

use OpenApi\Generator;
use OpenApi\Processors\OperationId;
use OpenApi\Util;

class GeneratorTest extends OpenApiTestCase
{
    public static function sourcesProvider(): iterable
    {
        $sourceDir = static::example('swagger-spec/petstore-simple');

        yield 'dir-list' => [$sourceDir, [$sourceDir]];
        yield 'file-list' => [$sourceDir, ["$sourceDir/SimplePet.php", "$sourceDir/SimplePetsController.php", "$sourceDir/OpenApiSpec.php"]];
        yield 'finder' => [$sourceDir, Util::finder($sourceDir)];
        yield 'finder-list' => [$sourceDir, [Util::finder($sourceDir)]];
    }

    /**
     * @dataProvider sourcesProvider
     */
    public function testGenerate(string $sourceDir, iterable $sources): void
    {
        $openapi = (new Generator())
            ->setAnalyser($this->getAnalyzer())
            ->generate($sources);

        $this->assertSpecEquals(file_get_contents(sprintf('%s/%s.yaml', $sourceDir, basename($sourceDir))), $openapi);
    }

    /**
     * @dataProvider sourcesProvider
     */
    public function testScan(string $sourceDir, iterable $sources): void
    {
        $analyzer = $this->getAnalyzer();
        $processor = (new Generator())
            ->getProcessorPipeline();

        $openapi = Generator::scan($sources, ['processor' => $processor, 'analyser' => $analyzer]);

        $this->assertSpecEquals(file_get_contents(sprintf('%s/%s.yaml', $sourceDir, basename($sourceDir))), $openapi);
    }

    public function testScanInvalidSource(): void
    {
        $this->assertOpenApiLogEntryContains('Skipping invalid source: /tmp/__swagger_php_does_not_exist__');
        $this->assertOpenApiLogEntryContains('Required @OA\PathItem() not found');
        $this->assertOpenApiLogEntryContains('Required @OA\Info() not found');

        (new Generator($this->getTrackingLogger()))
            ->setAnalyser($this->getAnalyzer())
            ->generate(['/tmp/__swagger_php_does_not_exist__']);
    }

    public static function processorCases(): iterable
    {
        return [
            [new OperationId(), true],
            [new class(false) extends OperationId {
            }, false],
        ];
    }

    public function testAddAlias(): void
    {
        $generator = new Generator();
        $generator->addAlias('foo', 'Foo\\Bar');

        $this->assertEquals(['oa' => 'OpenApi\\Annotations', 'foo' => 'Foo\\Bar'], $generator->getAliases());
    }

    public function testAddNamespace(): void
    {
        $generator = new Generator();
        $generator->addNamespace('Foo\\Bar\\');

        $this->assertEquals(['OpenApi\\Annotations\\', 'Foo\\Bar\\'], $generator->getNamespaces());
    }

    protected function assertOperationIdHash(Generator $generator, bool $expected): void
    {
        $generator->getProcessorPipeline()->walk(function ($processor) use ($expected) {
            if ($processor instanceof OperationId) {
                $this->assertEquals($expected, $processor->isHash());
            }
        });
    }

    public static function configCases(): iterable
    {
        return [
            'default' => [[], true],
            'nested' => [['operationId' => ['hash' => false]], false],
            'dots-kv' => [['operationId.hash' => false], false],
            'dots-string' => [['operationId.hash=false'], false],
        ];
    }

    /**
     * @dataProvider configCases
     */
    public function testConfig(array $config, bool $expected): void
    {
        $generator = new Generator();
        $this->assertOperationIdHash($generator, true);

        $generator->setConfig($config);
        $this->assertOperationIdHash($generator, $expected);
    }
}
