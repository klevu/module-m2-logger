<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Service\Provider;

use Klevu\LoggerApi\Service\Provider\LogFileNameProviderInterface;
use Klevu\LoggerApi\Validator\LogValidatorInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class LogFileNameProvider implements LogFileNameProviderInterface
{
    private const FILENAME_BASE_DEFAULT = 'general.log';
    private const FILENAME_PART_SEPARATOR = '-';
    private const FILENAME_PREFIX = 'klevu';

    /**
     * @var string
     */
    private string $baseFileName = self::FILENAME_BASE_DEFAULT;
    /**
     * @var StoreManagerInterface
     */
    private readonly StoreManagerInterface $storeManager;
    /**
     * @var LogValidatorInterface
     */
    private readonly LogValidatorInterface $fileNameValidator;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;

    /**
     * @param StoreManagerInterface $storeManager
     * @param LogValidatorInterface $fileNameValidator
     * @param LoggerInterface $logger
     * @param string|null $baseFileName
     *
     * @throws ValidatorException
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        LogValidatorInterface $fileNameValidator,
        LoggerInterface $logger,
        ?string $baseFileName = null,
    ) {
        $this->storeManager = $storeManager;
        $this->fileNameValidator = $fileNameValidator;
        $this->logger = $logger;
        if ($baseFileName) {
            $this->baseFileName = $baseFileName;
        }
        $this->validateFileName($this->baseFileName);
    }

    /**
     * @param int $storeId
     *
     * @return string
     * @throws ValidatorException
     */
    public function get(int $storeId): string
    {
        $storeCode = null;
        try {
            $store = $this->storeManager->getStore($storeId);
            $storeCode = $store->getCode();
        } catch (NoSuchEntityException $exception) {
            $this->logger->error(
                'Method: {method} - Error: {message}',
                [
                    'method' => __METHOD__,
                    'message' => $exception->getMessage(),
                ],
            );
        }

        $fileName = self::FILENAME_PREFIX;
        $fileName .= $storeCode
            ? self::FILENAME_PART_SEPARATOR . $storeCode
            : '';
        $fileName .= self::FILENAME_PART_SEPARATOR . $this->baseFileName;

        $this->validateFileName($fileName);

        return $fileName;
    }

    /**
     * @param mixed $fileName
     *
     * @return void
     * @throws ValidatorException
     */
    private function validateFileName(mixed $fileName): void
    {
        if ($this->fileNameValidator->isValid($fileName)) {
            return;
        }
        $errors = $this->fileNameValidator->hasMessages()
            ? $this->fileNameValidator->getMessages()
            : [];

        throw new ValidatorException(
            __(
                'Filename Validation Failed: %1',
                implode('; ', $errors),
            ),
        );
    }
}
