<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Service;

use Klevu\Logger\Exception\EmptyDirectoryException;
use Klevu\Logger\Exception\InvalidArchiverException;
use Klevu\Logger\Exception\InvalidDirectoryException;
use Klevu\Logger\Exception\InvalidFileExtensionException;
use Klevu\Logger\Exception\InvalidFilePathException;
use Klevu\Logger\Exception\NoLogsException;
use Klevu\LoggerApi\Service\Action\Directory\ArchiveActionInterface;
use Klevu\LoggerApi\Service\Action\Directory\DeleteActionInterface;
use Klevu\LoggerApi\Service\ArchiveLogsInterface;
use Klevu\LoggerApi\Service\Provider\StoreLogsDirectoryPathProviderInterface;
use Klevu\LoggerApi\Service\Provider\StoreLogsDirectoryProviderInterface;
use Klevu\LoggerApi\Validator\LogValidatorInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

class ArchiveLogs implements ArchiveLogsInterface
{
    /**
     * @var StoreLogsDirectoryProviderInterface
     */
    private readonly StoreLogsDirectoryProviderInterface $logsDirectoryProvider;
    /**
     * @var StoreLogsDirectoryPathProviderInterface
     */
    private readonly StoreLogsDirectoryPathProviderInterface $logsArchiveDirectoryPathProvider;
    /**
     * @var StoreLogsDirectoryPathProviderInterface
     */
    private readonly StoreLogsDirectoryPathProviderInterface $logsDirectoryPathProvider;
    /**
     * @var ArchiveActionInterface
     */
    private readonly ArchiveActionInterface $archiveAction;
    /**
     * @var DeleteActionInterface
     */
    private readonly DeleteActionInterface $deleteAction;
    /**
     * @var LogValidatorInterface
     */
    private readonly LogValidatorInterface $archiveValidator;
    /**
     * @var StoreManagerInterface
     */
    private readonly StoreManagerInterface $storeManager;

    /**
     * @param StoreLogsDirectoryProviderInterface $logsDirectoryProvider
     * @param StoreLogsDirectoryPathProviderInterface $logsDirectoryPathProvider
     * @param StoreLogsDirectoryPathProviderInterface $logsArchiveDirectoryPathProvider
     * @param ArchiveActionInterface $archiveAction
     * @param DeleteActionInterface $deleteAction
     * @param LogValidatorInterface $archiveValidator
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        StoreLogsDirectoryProviderInterface $logsDirectoryProvider,
        StoreLogsDirectoryPathProviderInterface $logsDirectoryPathProvider,
        StoreLogsDirectoryPathProviderInterface $logsArchiveDirectoryPathProvider,
        ArchiveActionInterface $archiveAction,
        DeleteActionInterface $deleteAction,
        LogValidatorInterface $archiveValidator,
        StoreManagerInterface $storeManager,
    ) {
        $this->logsDirectoryProvider = $logsDirectoryProvider;
        $this->logsDirectoryPathProvider = $logsDirectoryPathProvider;
        $this->logsArchiveDirectoryPathProvider = $logsArchiveDirectoryPathProvider;
        $this->archiveAction = $archiveAction;
        $this->deleteAction = $deleteAction;
        $this->archiveValidator = $archiveValidator;
        $this->storeManager = $storeManager;
    }

    /**
     * @param int $storeId
     *
     * @return string
     * @throws EmptyDirectoryException
     * @throws FileSystemException
     * @throws InvalidArchiverException
     * @throws InvalidDirectoryException
     * @throws InvalidFileExtensionException
     * @throws InvalidFilePathException
     * @throws NoLogsException
     * @throws NoSuchEntityException
     */
    public function execute(int $storeId): string
    {
        $this->validateStoreHasLogs(storeId: $storeId);
        $directory = $this->logsDirectoryPathProvider->get(storeId: $storeId);
        $archive = $this->archiveAction->execute(
            directory: $directory,
            archiveDirectory: $this->logsArchiveDirectoryPathProvider->get(storeId: $storeId),
        );
        $this->validateArchivePath(archive: $archive);
        $this->deleteAction->execute(directory: $directory);

        return $archive;
    }

    /**
     * @param int $storeId
     *
     * @return void
     * @throws NoLogsException
     */
    private function validateStoreHasLogs(int $storeId): void
    {
        if ($this->logsDirectoryProvider->hasLogs(storeId: $storeId)) {
            return;
        }
        $store = $this->getStore(storeId: $storeId);
        $message = $store
            ? __(
                'No logs to archive for store %1: %2 (%3).',
                $store->getId(),
                $store->getName(),
                $store->getCode(),
            )
            : __(
                'No logs to archive for store ID: %1.',
                $storeId,
            );

        throw new NoLogsException(
            phrase: $message,
        );
    }

    /**
     * @param string $archive
     *
     * @return void
     * @throws InvalidFilePathException
     */
    private function validateArchivePath(string $archive): void
    {
        if ($this->archiveValidator->isValid(value: $archive)) {
            return;
        }
        $errors = [];
        if ($this->archiveValidator->hasMessages()) {
            $errors = $this->archiveValidator->getMessages();
        }
        throw new InvalidFilePathException(
            phrase: __(
                'Invalid archive path returned. %1: %2',
                __METHOD__,
                implode(separator: ',', array: $errors),
            ),
        );
    }

    /**
     * @param int $storeId
     *
     * @return StoreInterface|null
     */
    private function getStore(int $storeId): ?StoreInterface
    {
        try {
            $return = $this->storeManager->getStore(storeId: $storeId);
        } catch (NoSuchEntityException) {
            $return = null;
        }

        return $return;
    }
}
