<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Test\Integration\Service;

use Klevu\Logger\Service\FileNameSanitizerService;
use Klevu\LoggerApi\Service\FileNameSanitizerServiceInterface;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * @covers FileNameSanitizerService
 */
class FileNameSanitizerServiceTest extends TestCase
{
    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->objectManager = ObjectManager::getInstance();
    }

    public function testImplements_FileNameSanitizerServiceInterface(): void
    {
        $this->assertInstanceOf(
            FileNameSanitizerServiceInterface::class,
            $this->instantiateFileNameSanitizer(),
        );
    }

    public function testPreference_ForFileNameSanitizerServiceInterface(): void
    {
        $this->assertInstanceOf(
            FileNameSanitizerService::class,
            $this->objectManager->create(FileNameSanitizerServiceInterface::class),
        );
    }

    public function testExecute_ForEmptyString(): void
    {
        $this->expectException(ValidatorException::class);
        $this->expectExceptionMessage('Filename Validation Failed: File Name value cannot be empty.');

        $fileNameSanitizer = $this->instantiateFileNameSanitizer();
        $fileNameSanitizer->execute('');
    }

    public function testExecute_WithRegularFilename(): void
    {
        $fileNameSanitizer = $this->instantiateFileNameSanitizer();
        $expectedFilename = 'klevu_french_analytics.log';
        $actual = $fileNameSanitizer->execute($expectedFilename);
        $this->assertEquals(
            $expectedFilename,
            $actual,
        );
        $this->assertIsString($actual);
    }

    public function testExecute_LeadingSlashFilename(): void
    {
        $fileNameSanitizer = $this->instantiateFileNameSanitizer();
        $expectedFilename = '/var/log/klevu_arabic_configuration.log';
        $actual = $fileNameSanitizer->execute($expectedFilename);
        $this->assertEquals(
            $expectedFilename,
            $actual,
        );
        $this->assertIsString($actual);
    }

    public function testExecute_OneParentLevelFolder(): void
    {
        $fileNameSanitizer = $this->instantiateFileNameSanitizer();
        $actual = $fileNameSanitizer->execute('../var/log/klevu_arabic_configuration.log');
        $this->assertEquals(
            'var/log/klevu_arabic_configuration.log',
            $actual,
        );
    }

    public function testExecute_TwoParentLevelFolder(): void
    {
        $fileNameSanitizer = $this->instantiateFileNameSanitizer();
        $actual = $fileNameSanitizer->execute('../../../var/log/klevu-default-indexing.log');
        $this->assertEquals(
            'var/log/klevu-default-indexing.log',
            $actual,
        );
    }

    public function testExecute_OneDotSlashFolder(): void
    {
        $fileNameSanitizer = $this->instantiateFileNameSanitizer();
        $actual = $fileNameSanitizer->execute('./var/log/klevu-default-analytics.log');
        $this->assertEquals(
            'var/log/klevu-default-analytics.log',
            $actual,
        );
    }

    public function testExecute_TrowsException_ForInvalidFileName(): void
    {
        $this->expectException(ValidatorException::class);
        $fileNameSanitizer = $this->instantiateFileNameSanitizer();
        $fileNameSanitizer->execute('./var/log/W*RY7239854R0-%$@&$.log');
    }

    /**
     * @param mixed[]|null $arguments
     *
     * @return FileNameSanitizerService
     */
    private function instantiateFileNameSanitizer(?array $arguments = []): FileNameSanitizerService
    {
        return $this->objectManager->create(
            type: FileNameSanitizerService::class,
            arguments: $arguments,
        );
    }
}
