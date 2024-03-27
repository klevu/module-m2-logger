<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Controller\Adminhtml\Download;

use Klevu\Logger\Exception\NoLogsException;
use Klevu\LoggerApi\Service\DownloadLogsInterface;
use Magento\Backend\App\AbstractAction;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Response\Http\FileFactory as HttpResponseFileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Io\File as FileIo;
use Psr\Log\LoggerInterface;

/**
 * Extending Magento\Backend\App\AbstractAction till Magento 2.5
 * Decomposition of Magento Controllers
 */
class Logs extends AbstractAction implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Klevu_LoggerApi::developer_logging';

    /**
     * @var DownloadLogsInterface
     */
    private readonly DownloadLogsInterface $downloadLogs;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var HttpResponseFileFactory
     */
    private readonly HttpResponseFileFactory $httpResponseFileFactory;
    /**
     * @var FileIo
     */
    private readonly FileIo $fileIo;

    /**
     * @param Context $context
     * @param DownloadLogsInterface $downloadLogs
     * @param LoggerInterface $logger
     * @param HttpResponseFileFactory $httpResponseFileFactory
     * @param FileIo $fileIo
     */
    public function __construct(
        Context $context,
        DownloadLogsInterface $downloadLogs,
        LoggerInterface $logger,
        HttpResponseFileFactory $httpResponseFileFactory,
        FileIo $fileIo,
    ) {
        parent::__construct($context);

        $this->downloadLogs = $downloadLogs;
        $this->logger = $logger;
        $this->httpResponseFileFactory = $httpResponseFileFactory;
        $this->fileIo = $fileIo;
    }

    /**
     * @return ResponseInterface
     */
    public function execute(): ResponseInterface
    {
        try {
            $request = $this->getRequest();
            $storeId = (int)$request->getParam(key: 'store');
            $fileToDownload = $this->downloadLogs->execute(storeId: $storeId);
            $fileName = $this->getFileName(file: $fileToDownload);

            return $this->httpResponseFileFactory->create(
                fileName: $fileName,
                content: [
                    'type' => 'filename',
                    'value' => $fileToDownload,
                    'rm' => true,
                ],
            );
        } catch (\InvalidArgumentException | NoLogsException $exception) {
            $this->messageManager->addNoticeMessage(message: $exception->getMessage());
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage(message: $exception->getMessage());
            $this->logger->error(
                message: 'Method: {method} - Error: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => $exception->getMessage(),
                    'exception' => $exception::class,
                ],
            );
        } catch (\Exception $exception) {
            $this->messageManager->addErrorMessage(
                message: (string)__('The request could not be processed. Please check server logs for details'),
            );
            $this->logger->error(
                message: 'Method: {method} - Error: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => $exception->getMessage(),
                    'exception' => $exception::class,
                ],
            );
        }

        return $this->_redirect(path: $this->_redirect->getRefererUrl());
    }

    /**
     * @param string $file
     *
     * @return string
     * @throws \InvalidArgumentException
     */
    private function getFileName(string $file): string
    {
        if (!trim(string: $file)) {
            throw new \InvalidArgumentException(
                message: __('File to download can not be empty.')->render(),
            );
        }
        $pathInfo = $this->fileIo->getPathInfo(path: $file);

        return $pathInfo['basename'];
    }
}
