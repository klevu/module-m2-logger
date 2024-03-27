<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Test\Integration\Service\Action\Directory;

use Klevu\Logger\Exception\EmptyDirectoryException;
use Klevu\Logger\Exception\InvalidDirectoryException;
use Klevu\Logger\Service\Action\Directory\DownloadAction;
use Klevu\Logger\Test\Integration\Traits\FileSystemTrait;
use Klevu\LoggerApi\Service\Action\Directory\DownloadActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * @covers DownloadAction
 */
class DownloadActionTest extends TestCase
{
    use FileSystemTrait;

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

    public function testImplements_DownloadActionInterface(): void
    {
        $this->assertInstanceOf(
            DownloadActionInterface::class,
            $this->instantiateDownloadAction(),
        );
    }

    public function testPreference_ForDownloadActionInterface(): void
    {
        $this->assertInstanceOf(
            DownloadAction::class,
            $this->objectManager->create(DownloadActionInterface::class),
        );
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_ThrowsException_ForEmptyDirectoryPath(): void
    {
        $directory = '';
        $this->expectException(InvalidDirectoryException::class);
        $this->expectExceptionMessage('Invalid Directory: Directory Path cannot be empty.');

        $downloadActionService = $this->instantiateDownloadAction();
        $downloadActionService->execute($directory);
    }

    /**
     * @magentoAppIsolation enabled
     * @dataProvider testExecute_ThrowsException_ForDirectoryPathContainingIllegalCharacters_DataProvider
     */
    public function testExecute_ThrowsException_ForDirectoryPathContainingIllegalCharacters(string $directory): void
    {
        $this->expectException(InvalidDirectoryException::class);
        $this->expectExceptionMessage(
            "Invalid Directory: Directory Path value contains illegal characters. " .
            "Received '" . $directory . "'. " .
            "Please ensure filename contains only alphanumeric, underscore, dash, or period characters.",
        );

        $downloadActionService = $this->instantiateDownloadAction();
        $downloadActionService->execute($directory);
    }

    /**
     * @return string[][]
     */
    public function testExecute_ThrowsException_ForDirectoryPathContainingIllegalCharacters_DataProvider(): array
    {
        return [
            ['@12344'],
            ['aefnoin!'],
            ['default~store/'],
            ['store\\'],
            ['fashion\'s'],
        ];
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_ThrowsException_ForUnexpectedDirectoryPath(): void
    {
        $directoryList = $this->objectManager->get(DirectoryList::class);
        $expectedPath = $directoryList->getPath(DirectoryList::LOG);
        $expectedPath .= DIRECTORY_SEPARATOR . 'klevu' . DIRECTORY_SEPARATOR;

        $directory = $directoryList->getPath(DirectoryList::LOG) . DIRECTORY_SEPARATOR;

        $this->expectException(InvalidDirectoryException::class);
        $this->expectExceptionMessage(
            'Invalid Directory: Directory path (' . $directory . ') ' .
            'does not contain required path (' . $expectedPath . ')',
        );

        $downloadActionService = $this->instantiateDownloadAction();
        $downloadActionService->execute($directory);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_ThrowsException_ForMissingDirectory(): void
    {
        $this->deleteAllLogs();
        $directoryList = $this->objectManager->get(DirectoryList::class);
        $directory = $directoryList->getPath(DirectoryList::LOG);
        $directory .= DIRECTORY_SEPARATOR . 'klevu' . DIRECTORY_SEPARATOR . 'default';

        $this->expectException(InvalidDirectoryException::class);
        $this->expectExceptionMessage(
            'Invalid Directory: Directory (' . $directory . ') does not exist',
        );

        $downloadActionService = $this->instantiateDownloadAction();
        $downloadActionService->execute($directory);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_ThrowsException_ForDirectoryWithWrongPermissions(): void
    {
        $this->deleteAllLogs();
        $directoryList = $this->objectManager->get(DirectoryList::class);
        $directory = $directoryList->getPath(DirectoryList::LOG);
        $directory .= DIRECTORY_SEPARATOR . 'klevu' . DIRECTORY_SEPARATOR;

        $this->createDirectory($directory, 0555);

        $this->expectException(InvalidDirectoryException::class);
        $this->expectExceptionMessage(
            'Invalid Directory: Directory (' . $directory . ') is not writable',
        );

        $downloadActionService = $this->instantiateDownloadAction();
        $downloadActionService->execute($directory);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_ThrowsException_ForEmptyDirectory(): void
    {
        $this->deleteAllLogs();
        $directoryList = $this->objectManager->get(DirectoryList::class);
        $directory = $directoryList->getPath(DirectoryList::LOG);
        $directory .= DIRECTORY_SEPARATOR . 'klevu' . DIRECTORY_SEPARATOR . 'default';

        $this->createDirectory($directory);

        $this->expectException(EmptyDirectoryException::class);
        $this->expectExceptionMessage(
            'Requested directory (' . $directory . ') is empty and can not be archived',
        );

        $downloadActionService = $this->instantiateDownloadAction();
        $downloadActionService->execute($directory);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_ReturnsString(): void
    {
        $this->deleteAllLogs();
        $directoryList = $this->objectManager->get(DirectoryList::class);
        $logDirectory = $directoryList->getPath(DirectoryList::LOG);
        $directory = $logDirectory . DIRECTORY_SEPARATOR . 'klevu' . DIRECTORY_SEPARATOR . 'default';

        $this->createDirectory($directory);
        $this->createLogFile();

        $downloadActionService = $this->instantiateDownloadAction();
        $response = $downloadActionService->execute($directory);

        $expectedPath = '#klevu\\' . DIRECTORY_SEPARATOR . 'klevu_log_default_\d*\.tar\.gz#';

        $this->assertMatchesRegularExpression(
            pattern: $expectedPath,
            string: $response,
        );
    }

    /**
     * @param mixed[]|null $params
     *
     * @return DownloadAction
     */
    private function instantiateDownloadAction(?array $params = []): DownloadAction
    {
        return $this->objectManager->create(
            DownloadAction::class,
            $params,
        );
    }
}
