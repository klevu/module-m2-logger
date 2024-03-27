<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Service;

use Klevu\LoggerApi\Service\IsLoggingEnabledServiceInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;

class IsLoggingEnabledService implements IsLoggingEnabledServiceInterface
{
    /**
     * @var ScopeConfigInterface
     */
    private readonly ScopeConfigInterface $scopeConfig;
    /**
     * @var string|null
     */
    private readonly ?string $minLogLevelConfigPath;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param string|null $minLogLevelConfigPath
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ?string $minLogLevelConfigPath = null,
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->minLogLevelConfigPath = $minLogLevelConfigPath;
    }

    /**
     * @param int $logLevel
     * @param StoreInterface $store
     *
     * @return bool
     */
    public function execute(int $logLevel, StoreInterface $store): bool
    {
        if (!$this->minLogLevelConfigPath) {
            return true;
        }
        $minLogLevel = (int)$this->scopeConfig->getValue(
            $this->minLogLevelConfigPath,
            ScopeInterface::SCOPE_STORES,
            $store->getId(),
        );

        return max(0, $logLevel) >= $minLogLevel;
    }
}
