<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Test\Integration\Traits;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File;

trait FileSystemTrait
{
    /**
     * @param string|null $fileName
     * @param string|null $path
     * @param string|null $storeCode
     *
     * @return void
     * @throws FileSystemException
     */
    private function createLogFile(
        ?string $fileName = 'test.log',
        ?string $path = null,
        ?string $storeCode = 'default',
    ): void {
        $this->createStoreLogsDirectory($path, $storeCode);
        $directoryPath = $this->getStoreLogsDirectoryPath($path, $storeCode);
        $systemFileDriver = $this->objectManager->create(File::class);
        $systemFileDriver->touch($directoryPath . DIRECTORY_SEPARATOR . $fileName);
    }

    /**
     * @param string|null $fileName
     * @param string|null $path
     * @param string|null $storeCode
     *
     * @return void
     * @throws FileSystemException
     */
    private function createLogFileWithContents(
        ?string $fileName = 'test_file_content.log',
        ?string $path = null,
        ?string $storeCode = 'default',
    ): void {
        $this->createLogFile($fileName, $path, $storeCode);
        $directoryPath = $this->getStoreLogsDirectoryPath($path, $storeCode);
        $systemFileDriver = $this->objectManager->create(File::class);
        $systemFileDriver->filePutContents(
            $directoryPath . DIRECTORY_SEPARATOR . $fileName,
            'this is the content of the file',
        );
    }

    /**
     * @param string $directoryPath
     * @param int $permissions
     *
     * @return void
     * @throws FileSystemException
     */
    private function createDirectory(string $directoryPath, int $permissions = 0777): void
    {
        $systemFileDriver = $this->objectManager->create(File::class);
        $systemFileDriver->createDirectory($directoryPath, $permissions);
    }

    /**
     * @return void
     * @throws FileSystemException
     */
    private function deleteAllLogs(): void
    {
        $directoryList = $this->objectManager->get(DirectoryList::class);
        $directoryPath = $directoryList->getPath(DirectoryList::LOG);
        $directoryPath .= DIRECTORY_SEPARATOR . 'klevu';

        $systemFileDriver = $this->objectManager->create(File::class);
        if ($systemFileDriver->isExists($directoryPath)) {
            $systemFileDriver->deleteDirectory($directoryPath);
        }
    }

    /**
     * @param string|null $path
     * @param string|null $storeCode
     *
     * @return void
     * @throws FileSystemException
     */
    private function createStoreLogsDirectory(
        ?string $path = null,
        ?string $storeCode = 'default',
    ): void {
        $directoryList = $this->objectManager->get(DirectoryList::class);
        $directoryPath = $directoryList->getPath(DirectoryList::LOG);
        $this->createDirectory($directoryPath);
        $directoryPath .= DIRECTORY_SEPARATOR . 'klevu';
        $this->createDirectory($directoryPath);
        if ($path) {
            $directoryPath .= DIRECTORY_SEPARATOR . $path;
            $this->createDirectory($directoryPath);
        }
        $directoryPath .= DIRECTORY_SEPARATOR . $storeCode;
        $this->createDirectory($directoryPath);
    }

    /**
     * @param string|null $path
     * @param string|null $storeCode
     *
     * @return string
     * @throws FileSystemException
     */
    private function getStoreLogsDirectoryPath(
        ?string $path = null,
        ?string $storeCode = 'default',
    ): string {
        $directoryList = $this->objectManager->get(DirectoryList::class);
        $directoryPath = $directoryList->getPath(DirectoryList::LOG);
        $directoryPath .= DIRECTORY_SEPARATOR . 'klevu';
        if ($path) {
            $directoryPath .= DIRECTORY_SEPARATOR . $path;
        }
        $directoryPath .= DIRECTORY_SEPARATOR . $storeCode;

        return $directoryPath;
    }
}
