<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Service\Provider;

use Klevu\Logger\Exception\InvalidDirectoryException;
use Klevu\LoggerApi\Service\Provider\StoreLogsDirectoryPathProviderInterface;
use Klevu\LoggerApi\Service\Provider\StoreLogsDirectoryProviderInterface;
use Klevu\LoggerApi\Validator\LogValidatorInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem\Directory\WriteInterface as DirectoryWriteInterface;
use Psr\Log\LoggerInterface;

class StoreLogsDirectoryProvider implements StoreLogsDirectoryProviderInterface
{
    public const KLEVU_LOGS_DIRECTORY = 'klevu';
    public const DIRECTORY_PERMISSIONS = 0755;

    /**
     * @var StoreLogsDirectoryPathProviderInterface
     */
    private readonly StoreLogsDirectoryPathProviderInterface $directoryPathProvider;
    /**
     * @var LogValidatorInterface
     */
    private readonly LogValidatorInterface $directoryValidator;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var DirectoryWriteInterface
     */
    private readonly DirectoryWriteInterface $fileSystemWrite;
    /**
     * @var string[]
     */
    private array $storeLogs = [];

    /**
     * @param StoreLogsDirectoryPathProviderInterface $directoryPathProvider
     * @param LogValidatorInterface $directoryValidator
     * @param LoggerInterface $logger
     * @param DirectoryWriteInterface $fileSystemWrite
     */
    public function __construct(
        StoreLogsDirectoryPathProviderInterface $directoryPathProvider,
        LogValidatorInterface $directoryValidator,
        LoggerInterface $logger,
        DirectoryWriteInterface $fileSystemWrite,
    ) {
        $this->directoryPathProvider = $directoryPathProvider;
        $this->directoryValidator = $directoryValidator;
        $this->logger = $logger;
        $this->fileSystemWrite = $fileSystemWrite;
    }

    /**
     * @param string $directory
     *
     * @return string[]
     * @throws InvalidDirectoryException
     */
    public function getLogs(string $directory): array
    {
        return $this->getStoreLogs($directory);
    }

    /**
     * @param int $storeId
     *
     * @return bool
     */
    public function hasLogs(int $storeId): bool
    {
        $directory = null;
        try {
            $directory = $this->directoryPathProvider->get($storeId);
        } catch (NoSuchEntityException | FileSystemException $exception) {
            $this->logger->error($exception->getMessage());
        }
        if (!$directory) {
            return false;
        }
        $files = [];
        try {
            $files = $this->getStoreLogs($directory);
        } catch (InvalidDirectoryException $exception) {
            $this->logger->error($exception->getMessage());
        }

        return (bool)count($files);
    }

    /**
     * @param string $directory
     *
     * @return string[][]
     */
    public function getLogsWithFileSize(string $directory): array
    {
        $logFiles = [];
        try {
            $files = $this->getStoreLogs(directoryPath: $directory);
        } catch (InvalidDirectoryException $exception) {
            $this->logger->error($exception->getMessage());

            return $logFiles;
        }
        foreach ($files as $file) {
            try {
                if (!$this->fileSystemWrite->isReadable(path: $file)) {
                    $this->logger->info(
                        sprintf(
                            "There are un-readable files in %s",
                            $directory,
                        ),
                    );
                    continue;
                }
                $data = [];
                $data['file'] = $file;
                $data['size'] = (string)$this->fileSystemWrite->stat(path: $file)['size'];
                $logFiles[] = $data;
            } catch (FileSystemException $exception) {
                $this->logger->error(
                    'Method: {method} - Info: {message}',
                    [
                        'method' => __METHOD__,
                        'message' => $exception->getMessage(),
                    ],
                );
            }
        }

        return $logFiles;
    }

    /**
     * @param string $directoryPath
     *
     * @return string[]
     * @throws InvalidDirectoryException
     */
    private function getStoreLogs(string $directoryPath): array
    {
        $cacheKey = $this->getCacheKey(directoryPath: $directoryPath);
        if (!isset($this->storeLogs[$cacheKey])) {
            $this->validateProvidedDirectory(directory: $directoryPath);
            try {
                if (!$this->fileSystemWrite->isExist(path: $directoryPath)) {
                    $this->fileSystemWrite->create(path: $directoryPath);
                    $this->fileSystemWrite->changePermissions(
                        path: $directoryPath,
                        permissions: static::DIRECTORY_PERMISSIONS,
                    );
                } else {
                    $this->storeLogs[$cacheKey] = $this->fileSystemWrite->read(path: $directoryPath);
                }
            } catch (FileSystemException $exception) {
                $this->logger->error(
                    'Method: {method} - Info: {message}',
                    [
                        'method' => __METHOD__,
                        'message' => $exception->getMessage(),
                    ],
                );
            }
        }

        return $this->storeLogs[$cacheKey] ?? [];
    }

    /**
     * @param string $directory
     *
     * @return void
     * @throws InvalidDirectoryException
     */
    private function validateProvidedDirectory(string $directory): void
    {
        if ($this->directoryValidator->isValid(value: $directory)) {
            return;
        }
        $exceptionMessage = $this->directoryValidator->hasMessages()
            ? implode(',', $this->directoryValidator->getMessages())
            : '';

        throw new InvalidDirectoryException(
            __(
                'Invalid Directory Provided: %1 - %2',
                __METHOD__,
                $exceptionMessage,
            ),
        );
    }

    /**
     * @param string $directoryPath
     *
     * @return string
     */
    private function getCacheKey(string $directoryPath): string
    {
        $logDir = DIRECTORY_SEPARATOR . DirectoryList::VAR_DIR . DIRECTORY_SEPARATOR . DirectoryList::LOG;
        $path = explode(separator: $logDir, string: $directoryPath);

        return $path[1] ?? $directoryPath;
    }
}
