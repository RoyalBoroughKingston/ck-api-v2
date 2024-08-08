<?php

namespace Tests\Unit\Generators;

use Tests\TestCase;
use App\Models\Model;
use Illuminate\Database\Query\Builder;
use App\Generators\UniqueSlugGenerator;
use Illuminate\Database\DatabaseManager;

class UniqueSlugGeneratorTest extends TestCase
{
    /**
     * @test
     *
     * @dataProvider generateDataProvider
     */
    public function generate_works(string $string, string $expected): void
    {
        $builderMock = $this->createMock(Builder::class);
        $builderMock->expects($this->once())
            ->method('where')
            ->willReturn($builderMock);
        $builderMock->expects($this->once())
            ->method('when')
            ->willReturn($builderMock);
        $builderMock->expects($this->once())
            ->method('exists')
            ->willReturn(false);

        $dbMock = $this->createMock(DatabaseManager::class);
        $dbMock->expects($this->once())
            ->method('__call')
            ->with('table', ['test-table'])
            ->willReturn($builderMock);

        $modelMock = $this->createMock(Model::class);
        $modelMock->expects($this->once())
            ->method('getTable')
            ->willReturn('test-table');

        $generator = new UniqueSlugGenerator($dbMock);
        $result = $generator->generate($string, $modelMock);

        $this->assertEquals($expected, $result);
    }

    /**
     * @test
     *
     * @dataProvider generateUsedDataProvider
     */
    public function test_generate_works_with_used_slug(string $string, string $expected, int $usedCount): void
    {
        $builderMock = $this->createMock(Builder::class);
        $builderMock->expects($this->exactly($usedCount + 1))
            ->method('where')
            ->willReturn($builderMock);
        $builderMock->expects($this->exactly($usedCount + 1))
            ->method('when')
            ->willReturn($builderMock);
        $builderMock->expects($this->exactly($usedCount + 1))
            ->method('exists')
            ->willReturnOnConsecutiveCalls(
                ...array_map(function ($value) use ($usedCount) {
                    return $value < $usedCount;
                }, range(0, $usedCount))
            );

        $dbMock = $this->createMock(DatabaseManager::class);
        $dbMock->expects($this->exactly($usedCount + 1))
            ->method('__call')
            ->with('table', ['test-table'])
            ->willReturn($builderMock);

        $modelMock = $this->createMock(Model::class);
        $modelMock->expects($this->exactly($usedCount + 1))
            ->method('getTable')
            ->willReturn('test-table');

        $generator = new UniqueSlugGenerator($dbMock);
        $result = $generator->generate($string, $modelMock);

        $this->assertEquals($expected, $result);
    }

    /**
     * @test
     *
     * @dataProvider compareEqualsDataProvider
     */
    public function compareEquals_works(string $string, string $slug, bool $expected): void
    {
        $dbMock = $this->createMock(DatabaseManager::class);

        $generator = new UniqueSlugGenerator($dbMock);
        $result = $generator->compareEquals($string, $slug);

        $this->assertEquals($expected, $result);
    }

    /**
     * @return \string[][]
     */
    public function generateDataProvider(): array
    {
        return [
            ['string' => 'Test Org', 'expected' => 'test-org'],
            ['string' => 'TestOrg', 'expected' => 'testorg'],
            ['string' => 'Test Org PLC', 'expected' => 'test-org-plc'],
        ];
    }

    /**
     * @return array[]
     */
    public function generateUsedDataProvider(): array
    {
        return [
            ['string' => 'Test Org', 'expected' => 'test-org-3', 'usedCount' => 3],
            ['string' => 'TestOrg', 'expected' => 'testorg-5', 'usedCount' => 5],
            ['string' => 'Test Org PLC', 'expected' => 'test-org-plc-10', 'usedCount' => 10],
        ];
    }

    public function compareEqualsDataProvider(): array
    {
        return [
            ['string' => 'Test Org', 'slug' => 'test-org-4', 'expected' => true],
            ['string' => 'TestOrg', 'slug' => 'testorg-6', 'expected' => true],
            ['string' => 'Test Org PLC', 'slug' => 'test-org-plc-11', 'expected' => true],
            ['string' => 'Test Org', 'slug' => 'test-org-plc-', 'expected' => false],
            ['string' => 'Test Org', 'slug' => 'test-org-plc-11a', 'expected' => false],
            ['string' => 'Test Org', 'slug' => 'test-org-plc-11-', 'expected' => false],
            ['string' => 'Test Org', 'slug' => 'test-org-plc-11-1', 'expected' => false],
            ['string' => 'Test Org', 'slug' => '1', 'expected' => false],
            ['string' => 'Test Org', 'slug' => '', 'expected' => false],
            ['string' => 'Test Org', 'slug' => 'testorg', 'expected' => false],
            ['string' => 'Test Org', 'slug' => '1-test-org', 'expected' => false],
        ];
    }
}
