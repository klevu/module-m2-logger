<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\WebApi;

use Klevu\LoggerApi\Api\ClearLogsInterface;
use Klevu\LoggerApi\Api\Data\LogResponseInterface;
use Klevu\LoggerApi\Api\Data\LogResponseInterfaceFactory;
use Klevu\LoggerApi\Service\ClearLogsInterface as ClearLogsServiceInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class ClearLogs implements ClearLogsInterface
{
    /**
     * @var ClearLogsServiceInterface
     */
    private readonly ClearLogsServiceInterface $clearLogs;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var LogResponseInterfaceFactory
     */
    private readonly LogResponseInterfaceFactory $responseFactory;
    /**
     * @var StoreManagerInterface
     */
    private readonly StoreManagerInterface $storeManager;

    /**
     * @param ClearLogsServiceInterface $clearLogs
     * @param LoggerInterface $logger
     * @param LogResponseInterfaceFactory $responseFactory
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ClearLogsServiceInterface $clearLogs,
        LoggerInterface $logger,
        LogResponseInterfaceFactory $responseFactory,
        StoreManagerInterface $storeManager,
    ) {
        $this->clearLogs = $clearLogs;
        $this->logger = $logger;
        $this->responseFactory = $responseFactory;
        $this->storeManager = $storeManager;
    }

    /**
     * @param int $store
     *
     * @return LogResponseInterface
     */
    public function execute(int $store): LogResponseInterface
    {
        try {
            $this->clearLogs->execute(storeId: $store);
            $currentStore = $this->getStore($store);
            $message = $currentStore
                ? __(
                    'Logs cleared for store %1: %2 (%3).',
                    $currentStore->getId(),
                    $currentStore->getName(),
                    $currentStore->getCode(),
                )
                : __(
                    'Logs cleared for store ID: %1.',
                    $store,
                );
            $response = [
                'status' => 'success',
                'message' => $message,
                'code' => 200,
            ];
        } catch (NoSuchEntityException $exception) {
            $this->logger->error(
                message: 'Method: {method} - Error: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => $exception->getMessage(),
                ],
            );
            $response = [
                'status' => 'error',
                'message' => __('Store ID %1 not found.', $store),
                'code' => 404,
            ];
        } catch (\Exception $exception) {
            $this->logger->error(
                message: 'Method: {method} - Error: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => $exception->getMessage(),
                ],
            );
            $currentStore = $this->getStore($store);
            $message = $currentStore
                ? __(
                    'Internal error: Archive could not be deleted. ' .
                    'Check logs for store %1: %2 (%3) for more information.',
                    $currentStore->getId(),
                    $currentStore->getName(),
                    $currentStore->getCode(),
                )
                : __(
                    'Internal error: Archive could not be deleted. ' .
                    'Check logs for store %1 for more information.',
                    $store,
                );
            $response = [
                'status' => 'error',
                'message' => $message,
                'code' => 500,
            ];
        }

        return $this->createResponse(data: $response);
    }

    /**
     * @param mixed[] $data
     *
     * @return LogResponseInterface
     */
    private function createResponse(array $data): LogResponseInterface
    {
        $response = $this->responseFactory->create();
        $response->setStatus(status: $data['status'] ?? '');
        $response->setCode(code: $data['code'] ?? '');
        $response->setMessage(message: $data['message'] ?? null);

        return $response;
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
