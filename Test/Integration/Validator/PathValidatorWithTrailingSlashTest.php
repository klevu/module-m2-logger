<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Test\Integration\Validator;

use Klevu\Logger\Validator\PathValidatorWithTrailingSlash;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Filesystem\Directory\PathValidatorInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

class PathValidatorWithTrailingSlashTest extends TestCase
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

    public function testImplements_PathValidatorInterface(): void
    {
        $this->assertInstanceOf(
            PathValidatorInterface::class,
            $this->instantiatePathValidatorWithTrailingSlash(),
        );
    }

    /**
     * @dataProvider testIsValid_DoesNotThrowException_ForValidAbsolutePaths_dataProvider
     */
    public function testIsValid_DoesNotThrowException_ForValidAbsolutePaths(string $path): void
    {
        $directoryList = $this->objectManager->get(DirectoryList::class);
        $logDirectory = $directoryList->getPath(DirectoryList::LOG);
        $expectedPath = $logDirectory . DIRECTORY_SEPARATOR . 'klevu' . DIRECTORY_SEPARATOR;
        $actualPath = $directoryList->getPath(DirectoryList::VAR_DIR) . DIRECTORY_SEPARATOR . $path;

        $pathValidator = $this->instantiatePathValidatorWithTrailingSlash();
        $pathValidator->validate(
            directoryPath: $expectedPath,
            path: $actualPath,
            scheme: null,
            absolutePath: true,
        );
    }

    /**
     * @return string[][]
     */
    public function testIsValid_DoesNotThrowException_ForValidAbsolutePaths_dataProvider(): array
    {
        return [
            ['log' . DIRECTORY_SEPARATOR . 'klevu' . DIRECTORY_SEPARATOR . 'default'],
            ['log' . DIRECTORY_SEPARATOR . 'klevu' . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR],
            ['log' . DIRECTORY_SEPARATOR . 'klevu'],
            ['log' . DIRECTORY_SEPARATOR . 'klevu' . DIRECTORY_SEPARATOR],
        ];
    }

    /**
     * @dataProvider testIsValid_ThrowsException_ForInvalidAbsolutePaths_dataProvider
     */
    public function testIsValid_ThrowsException_ForInvalidAbsolutePaths(string $actualPath): void
    {
        $directoryList = $this->objectManager->get(DirectoryList::class);
        $logDirectory = $directoryList->getPath(DirectoryList::LOG);
        $expectedPath = $logDirectory . DIRECTORY_SEPARATOR . 'klevu' . DIRECTORY_SEPARATOR;
        $actualPath = $directoryList->getPath(DirectoryList::VAR_DIR) . DIRECTORY_SEPARATOR . $actualPath;

        $this->expectException(ValidatorException::class);
        $this->expectExceptionMessage(
            sprintf(
                "Path '%s' cannot be used with directory '%s'",
                $actualPath,
                $expectedPath,
            ),
        );

        $pathValidator = $this->instantiatePathValidatorWithTrailingSlash();
        $pathValidator->validate(
            directoryPath: $expectedPath,
            path: $actualPath,
            scheme: null,
            absolutePath: true,
        );
    }

    /**
     * @return string[][]
     */
    public function testIsValid_ThrowsException_ForInvalidAbsolutePaths_dataProvider(): array
    {
        return [
            ['logs' . DIRECTORY_SEPARATOR . 'klevu' . DIRECTORY_SEPARATOR . 'default'],
            ['logs' . DIRECTORY_SEPARATOR . 'klevu' . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR],
            ['log' . DIRECTORY_SEPARATOR . 'klevu-logs' . DIRECTORY_SEPARATOR . 'default'],
            ['log' . DIRECTORY_SEPARATOR . 'klevu-logs' . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR],
            ['log' . DIRECTORY_SEPARATOR . 'klevu-default'],
            ['log' . DIRECTORY_SEPARATOR . 'klevu-default' . DIRECTORY_SEPARATOR],
            ['log' . DIRECTORY_SEPARATOR . 'default'],
            ['log' . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR],
        ];
    }

    /**
     * @param mixed[]|null $params
     *
     * @return PathValidatorWithTrailingSlash
     */
    private function instantiatePathValidatorWithTrailingSlash(?array $params = []): PathValidatorWithTrailingSlash
    {
        return $this->objectManager->create(
            PathValidatorWithTrailingSlash::class,
            $params,
        );
    }
}
