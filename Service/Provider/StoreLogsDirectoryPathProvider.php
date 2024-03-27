<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Service\Provider;

use Klevu\LoggerApi\Service\Provider\StoreLogsDirectoryPathProviderInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class StoreLogsDirectoryPathProvider implements StoreLogsDirectoryPathProviderInterface
{
    /**
     * @var DirectoryList
     */
    private readonly DirectoryList $directoryList;
    /**
     * @var StoreManagerInterface
     */
    private readonly StoreManagerInterface $storeManager;
    /**
     * @var string
     */
    private readonly string $appendPath;
    /**
     * @var string[]
     */
    private array $directoryPath = [];

    /**
     * @param DirectoryList $directoryList
     * @param StoreManagerInterface $storeManager
     * @param ArchiveDirectoryNameProvider $archiveDirectoryNameProvider
     * @param bool $isArchive
     */
    public function __construct(
        DirectoryList $directoryList,
        StoreManagerInterface $storeManager,
        ArchiveDirectoryNameProvider $archiveDirectoryNameProvider,
        bool $isArchive = false,
    ) {
        $this->directoryList = $directoryList;
        $this->storeManager = $storeManager;
        $this->appendPath = $isArchive ? $archiveDirectoryNameProvider->get() : '';
    }

    /**
     * @param int $storeId
     *
     * @return string|null
     * @throws FileSystemException
     * @throws NoSuchEntityException
     */
    public function get(int $storeId): ?string
    {
        $cacheKey = $this->getCacheKey($storeId);

        return $this->directoryPath[$cacheKey] ??= $this->buildDirectoryPath($storeId);
    }

    /**
     * @param int $storeId
     *
     * @return string
     * @throws FileSystemException
     * @throws NoSuchEntityException
     */
    private function buildDirectoryPath(int $storeId): string
    {
        $store = $this->storeManager->getStore($storeId);

        return implode(
            DIRECTORY_SEPARATOR,
            array_filter([
                $this->directoryList->getPath(DirectoryList::LOG),
                StoreLogsDirectoryProvider::KLEVU_LOGS_DIRECTORY,
                $this->appendPath,
                $store->getCode(),
            ]),
        );
    }

    /**
     * @param int $storeId
     *
     * @return string
     */
    private function getCacheKey(int $storeId): string
    {
        return ((string)$storeId) . $this->appendPath;
    }
}
