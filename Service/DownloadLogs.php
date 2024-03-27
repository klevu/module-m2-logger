<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Service;

use Klevu\Logger\Exception\ConfigurationException;
use Klevu\Logger\Exception\EmptyDirectoryException;
use Klevu\Logger\Exception\InvalidDirectoryException;
use Klevu\Logger\Exception\NoLogsException;
use Klevu\Logger\Exception\SizeExceedsLimitException;
use Klevu\LoggerApi\Service\Action\Directory\DownloadActionInterface;
use Klevu\LoggerApi\Service\DownloadLogsInterface;
use Klevu\LoggerApi\Service\Provider\StoreLogsDirectoryPathProviderInterface;
use Klevu\LoggerApi\Service\Provider\StoreLogsDirectoryProviderInterface;
use Klevu\LoggerApi\Validator\LogValidatorInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

class DownloadLogs implements DownloadLogsInterface
{
    /**
     * @var StoreLogsDirectoryProviderInterface
     */
    private readonly StoreLogsDirectoryProviderInterface $logsDirectoryProvider;
    /**
     * @var StoreLogsDirectoryPathProviderInterface
     */
    private readonly StoreLogsDirectoryPathProviderInterface $logsDirectoryPathProvider;
    /**
     * @var DownloadActionInterface
     */
    private readonly DownloadActionInterface $downloadAction;
    /**
     * @var LogValidatorInterface
     */
    private readonly LogValidatorInterface $logSizeValidator;
    /**
     * @var StoreManagerInterface
     */
    private readonly StoreManagerInterface $storeManager;

    /**
     * @param StoreLogsDirectoryProviderInterface $logsDirectoryProvider
     * @param StoreLogsDirectoryPathProviderInterface $logsDirectoryPathProvider
     * @param DownloadActionInterface $downloadAction
     * @param LogValidatorInterface $logSizeValidator
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        StoreLogsDirectoryProviderInterface $logsDirectoryProvider,
        StoreLogsDirectoryPathProviderInterface $logsDirectoryPathProvider,
        DownloadActionInterface $downloadAction,
        LogValidatorInterface $logSizeValidator,
        StoreManagerInterface $storeManager,
    ) {
        $this->logsDirectoryProvider = $logsDirectoryProvider;
        $this->logsDirectoryPathProvider = $logsDirectoryPathProvider;
        $this->downloadAction = $downloadAction;
        $this->logSizeValidator = $logSizeValidator;
        $this->storeManager = $storeManager;
    }

    /**
     * @param int $storeId
     *
     * @return string|null
     * @throws LocalizedException
     * @throws NoLogsException
     * @throws SizeExceedsLimitException
     */
    public function execute(int $storeId): ?string
    {
        $return = null;
        try {
            $return = $this->getFileToDownload(storeId: $storeId);
        } catch (EmptyDirectoryException $exception) {
            // this SHOULD never be thrown as we have already validated earlier in this method.
            $this->throwNoLogsException(storeId: $storeId);
        } catch (ConfigurationException | InvalidDirectoryException | FileSystemException $exception) {
            $this->throwInternalErrorException(storeId: $storeId, exception: $exception);
        }

        return $return;
    }

    /**
     * @param int $storeId
     *
     * @return string|null
     * @throws LocalizedException
     * @throws NoLogsException
     * @throws SizeExceedsLimitException
     */
    private function getFileToDownload(int $storeId): ?string
    {
        $this->validateStoreHasLogs(storeId: $storeId);
        $directory = $this->getLogsDirectory(storeId: $storeId);
        $this->validateLogSizeIsUnderLimit(directory: $directory);

        return $this->downloadAction->execute(directory: $directory);
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
        $this->throwNoLogsException(storeId: $storeId);
    }

    /**
     * @param int $storeId
     *
     * @return string|null
     * @throws LocalizedException
     */
    private function getLogsDirectory(int $storeId): ?string
    {
        $directory = null;
        try {
            $directory = $this->logsDirectoryPathProvider->get(storeId: $storeId);
        } catch (NoSuchEntityException | FileSystemException $exception) {
            $this->throwInternalErrorException(storeId: $storeId, exception: $exception);
        }

        return $directory;
    }

    /**
     * @param string|null $directory
     *
     * @return void
     * @throws SizeExceedsLimitException
     */
    private function validateLogSizeIsUnderLimit(?string $directory): void
    {
        if ($this->logSizeValidator->isValid(value: $directory)) {
            return;
        }
        $errors = [];
        if ($this->logSizeValidator->hasMessages()) {
            $errors = $this->logSizeValidator->getMessages();
        }
        throw new SizeExceedsLimitException(
            phrase: __(
                'File Size Exceeds Limit: %1',
                implode(',', $errors),
            ),
        );
    }

    /**
     * @param int $storeId
     *
     * @return void
     * @throws NoLogsException
     */
    private function throwNoLogsException(int $storeId): void
    {
        $store = $this->getStore(storeId: $storeId);
        $message = $store
            ? __(
                'There are no logs to download for store %1: %2 (%3).',
                $store->getId(),
                $store->getName(),
                $store->getCode(),
            )
            : __(
                'There are no logs to download for store ID %1.',
                $storeId,
            );

        throw new NoLogsException(phrase: $message);
    }

    /**
     * @param int $storeId
     * @param \Exception $exception
     *
     * @return void
     * @throws LocalizedException
     */
    private function throwInternalErrorException(
        int $storeId,
        \Exception $exception,
    ): void {
        $store = $this->getStore(storeId: $storeId);
        $message = $store
            ? __(
                'Internal error for store %1: %2 (%3).',
                $store->getId(),
                $store->getName(),
                $store->getCode(),
            )
            : __(
                'Internal error for store ID %1.',
                $storeId,
            );

        throw new LocalizedException(
            phrase: $message,
            cause: $exception,
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
