<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Handler;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\LoggerApi\Service\FileNameSanitizerServiceInterface;
use Klevu\LoggerApi\Service\IsLoggingEnabledServiceInterface;
use Klevu\LoggerApi\Service\Provider\LogFileNameProviderInterface;
use Klevu\LoggerApi\Service\Provider\StoreLogsDirectoryPathProviderInterface;
use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Logger\Handler\Base as BaseHandler;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
// phpcs:ignore SlevomatCodingStandard.Namespaces.UseOnlyWhitelistedNamespaces.NonFullyQualified
use Monolog\Logger;

/**
 * @property string|null $fileName
 */
class LogIfConfigured extends BaseHandler
{
    /**
     * @var IsLoggingEnabledServiceInterface
     */
    private readonly IsLoggingEnabledServiceInterface $loggingEnabledService;
    /**
     * @var ScopeProviderInterface
     */
    private readonly ScopeProviderInterface $scopeProvider;
    /**
     * @var StoreLogsDirectoryPathProviderInterface
     */
    private readonly StoreLogsDirectoryPathProviderInterface $logDirectoryProvider;
    /**
     * @var LogFileNameProviderInterface
     */
    private readonly LogFileNameProviderInterface $logFileNameProvider;
    /**
     * @var FileNameSanitizerServiceInterface
     */
    private readonly FileNameSanitizerServiceInterface $fileNameSanitizer;
    /**
     * @var StoreManagerInterface
     */
    private readonly StoreManagerInterface $storeManager;

    /**
     * @param IsLoggingEnabledServiceInterface $loggingEnabledService
     * @param ScopeProviderInterface $scopeProvider
     * @param StoreLogsDirectoryPathProviderInterface $logDirectoryProvider
     * @param LogFileNameProviderInterface $logFileNameProvider
     * @param FileNameSanitizerServiceInterface $fileNameSanitizerService
     * @param DriverInterface $filesystem
     * @param StoreManagerInterface $storeManager
     * @param string|null $filePath
     * @param string|null $fileName
     */
    public function __construct(
        IsLoggingEnabledServiceInterface $loggingEnabledService,
        ScopeProviderInterface $scopeProvider,
        StoreLogsDirectoryPathProviderInterface $logDirectoryProvider,
        LogFileNameProviderInterface $logFileNameProvider,
        FileNameSanitizerServiceInterface $fileNameSanitizerService,
        DriverInterface $filesystem,
        StoreManagerInterface $storeManager,
        ?string $filePath = null,
        ?string $fileName = null,
    ) {
        parent::__construct($filesystem, $filePath, $fileName);

        $this->loggingEnabledService = $loggingEnabledService;
        $this->scopeProvider = $scopeProvider;
        $this->logDirectoryProvider = $logDirectoryProvider;
        $this->logFileNameProvider = $logFileNameProvider;
        $this->fileNameSanitizer = $fileNameSanitizerService;
        $this->storeManager = $storeManager;
    }

    /**
     * @param mixed[] $record
     *
     * @return void
     */
    public function write(array $record): void
    {
        $stores = $this->getStores($record);
        foreach ($stores as $store) {
            $fileName = $this->logFileNameProvider->get(storeId: (int)$store->getId());
            if (!$fileName) {
                return;
            }
            if ($fileName !== $this->fileName) {
                $this->resetFileProperties();
            }
            try {
                if (null === $this->fileName) {
                    $this->fileName = $this->logDirectoryProvider->get(storeId: (int)$store->getId())
                        . DIRECTORY_SEPARATOR
                        . $this->fileNameSanitizer->execute(fileName: $fileName);
                    $this->url = $this->fileName;
                }

                $formatter = $this->getFormatter();
                $record['formatted'] = $formatter->format(record: $record);
            } catch (\Exception) {
                // Can't log the exception thrown by the logger when writing to the log
                return;
            }

            parent::write(record: $record);
        }
    }

    /**
     * @param mixed[] $record
     *
     * @return bool
     */
    public function isHandling(array $record): bool
    {
        return (bool)count($this->getStores($record));
    }

    /**
     * @param mixed[] $record
     *
     * @return StoreInterface[]
     */
    private function getStores(array $record): array
    {
        $currentScope = $this->scopeProvider->getCurrentScope();

        $scope = $currentScope->getScopeObject();
        $stores = match (true) {
            $scope instanceof StoreInterface => [$scope],
            $scope instanceof WebsiteInterface => method_exists($scope, 'getStores') ? $scope->getStores() : [],
            default => $this->storeManager->getStores(),
        };
        $level = $this->getLogLevel($record['level']);

        return array_filter(
            array: $stores,
            callback: fn ($store) => ($this->loggingEnabledService->execute(logLevel: $level, store: $store)),
        );
    }

    /**
     * @param mixed $level
     *
     * @return int
     */
    private function getLogLevel(mixed $level): int
    {
        $requestedLevel = $level ?? Logger::DEBUG;

        return is_int(value: $requestedLevel)
            ? $requestedLevel
            : Logger::DEBUG;
    }

    /**
     * @return void
     */
    private function resetFileProperties(): void
    {
        $this->fileName = null;
        $this->url = null;
        $this->close();
    }
}
