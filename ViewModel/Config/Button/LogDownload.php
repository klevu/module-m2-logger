<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\ViewModel\Config\Button;

use Klevu\Configuration\Service\Provider\StoreScopeProviderInterface;
use Klevu\Configuration\ViewModel\ButtonInterface;
use Klevu\LoggerApi\Service\Provider\StoreLogsDirectoryProviderInterface;
use Magento\Framework\Phrase;
use Magento\Framework\UrlInterface;

class LogDownload implements ButtonInterface
{
    /**
     * @var StoreScopeProviderInterface
     */
    private readonly StoreScopeProviderInterface $storeScopeProvider;
    /**
     * @var UrlInterface
     */
    private readonly UrlInterface $urlBuilder;
    /**
     * @var StoreLogsDirectoryProviderInterface
     */
    private readonly StoreLogsDirectoryProviderInterface $storeLogsDirectoryProvider;
    /**
     * @var bool[]
     */
    private array $hasLogs = [];

    /**
     * @param StoreScopeProviderInterface $storeScopeProvider
     * @param UrlInterface $urlBuilder
     * @param StoreLogsDirectoryProviderInterface $storeLogsDirectoryProvider
     */
    public function __construct(
        StoreScopeProviderInterface $storeScopeProvider,
        UrlInterface $urlBuilder,
        StoreLogsDirectoryProviderInterface $storeLogsDirectoryProvider,
    ) {
        $this->storeScopeProvider = $storeScopeProvider;
        $this->urlBuilder = $urlBuilder;
        $this->storeLogsDirectoryProvider = $storeLogsDirectoryProvider;
    }

    /**
     * @return string
     */
    public function getAction(): string
    {
        if (!$this->hasLogs()) {
            return '';
        }
        $store = $this->storeScopeProvider->getCurrentStore();
        if (!$store) {
            return '';
        }
        $route = 'klevu_logger/download/logs';
        $params = [
            'store' => $store->getId(),
        ];

        return 'setLocation(\'' . $this->urlBuilder->getUrl(routePath: $route, routeParams: $params) . '\')';
    }

    /**
     * @return string|null
     */
    public function getClass(): ?string
    {
        return null;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return 'klevu_logger_download_logs_button';
    }

    /**
     * @return Phrase
     */
    public function getLabel(): Phrase
    {
        $label = $this->hasLogs()
            ? 'Download Logs'
            : 'No Logs To Download';

        return __($label);
    }

    /**
     * @return string|null
     */
    public function getStyle(): ?string
    {
        return null;
    }

    /**
     * @return bool
     */
    public function isDisabled(): bool
    {
        return !$this->hasLogs();
    }

    /**
     * @return bool
     */
    public function isVisible(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    private function hasLogs(): bool
    {
        $store = $this->storeScopeProvider->getCurrentStore();
        if (!$store) {
            return false;
        }
        $storeId = $store->getId();

        return $this->hasLogs[$storeId] ??= $this->storeLogsDirectoryProvider->hasLogs(storeId: (int)$storeId);
    }
}
