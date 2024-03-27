<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Service;

use Klevu\Logger\Exception\InvalidDirectoryException;
use Klevu\LoggerApi\Service\Action\Directory\DeleteActionInterface;
use Klevu\LoggerApi\Service\ClearLogsInterface;
use Klevu\LoggerApi\Service\Provider\StoreLogsDirectoryPathProviderInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\NoSuchEntityException;

class ClearLogs implements ClearLogsInterface
{
    /**
     * @var StoreLogsDirectoryPathProviderInterface
     */
    private readonly StoreLogsDirectoryPathProviderInterface $directoryPathProvider;
    /**
     * @var DeleteActionInterface
     */
    private readonly DeleteActionInterface $deleteAction;

    /**
     * @param StoreLogsDirectoryPathProviderInterface $directoryPathProvider
     * @param DeleteActionInterface $deleteAction
     */
    public function __construct(
        StoreLogsDirectoryPathProviderInterface $directoryPathProvider,
        DeleteActionInterface $deleteAction,
    ) {
        $this->directoryPathProvider = $directoryPathProvider;
        $this->deleteAction = $deleteAction;
    }

    /**
     * @param int $storeId
     *
     * @return bool
     * @throws FileSystemException
     * @throws InvalidDirectoryException
     * @throws NoSuchEntityException
     */
    public function execute(int $storeId): bool
    {
        return $this->deleteAction->execute(
            directory: $this->directoryPathProvider->get(storeId: $storeId),
        );
    }
}
