<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\ViewModel\Config\Button;

use Klevu\Configuration\Service\GetBearerTokenInterface;
use Klevu\Configuration\Service\Provider\StoreScopeProviderInterface;
use Klevu\Configuration\ViewModel\ButtonInterface;
use Klevu\LoggerApi\Service\Provider\StoreLogsDirectoryProviderInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;

class LogClear implements ButtonInterface
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
     * @var FormKey
     */
    private readonly FormKey $formKey;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var GetBearerTokenInterface
     */
    private readonly GetBearerTokenInterface $getBearerToken;
    /**
     * @var bool[]
     */
    private array $hasLogs = [];

    /**
     * @param StoreScopeProviderInterface $storeScopeProvider
     * @param UrlInterface $urlBuilder
     * @param StoreLogsDirectoryProviderInterface $storeLogsDirectoryProvider
     * @param FormKey $formKey
     * @param LoggerInterface $logger
     * @param GetBearerTokenInterface $getBearerToken
     */
    public function __construct(
        StoreScopeProviderInterface $storeScopeProvider,
        UrlInterface $urlBuilder,
        StoreLogsDirectoryProviderInterface $storeLogsDirectoryProvider,
        FormKey $formKey,
        LoggerInterface $logger,
        GetBearerTokenInterface $getBearerToken,
    ) {
        $this->storeScopeProvider = $storeScopeProvider;
        $this->urlBuilder = $urlBuilder;
        $this->storeLogsDirectoryProvider = $storeLogsDirectoryProvider;
        $this->formKey = $formKey;
        $this->logger = $logger;
        $this->getBearerToken = $getBearerToken;
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
        $url = $this->urlBuilder->getUrl(
            routePath:'rest/' . $store->getCode() . '/V1/klevu_logger/clear_logs',
            routeParams: [
                'store' => $store->getId(),
            ],
        );

        return $this->getOnClickAjax(url: $url);
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
        return 'klevu_logger_clear_logs_button';
    }

    /**
     * @return Phrase
     */
    public function getLabel(): Phrase
    {
        $label = $this->hasLogs()
            ? 'Clear Archive'
            : 'No Archive To Clear';

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

    /**
     * @param string $url
     *
     * @return string
     */
    private function getOnClickAjax(string $url): string
    {
        try {
            $formKey = $this->formKey->getFormKey();
        } catch (LocalizedException $exception) {
            $this->logger->error(
                message: 'Method: {method} - Error: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => $exception->getMessage(),
                ],
            );

            return 'alert("Form Key is missing: Check logs for more information.")';
        }
        $ajaxParams = '{
            form_key: "' . $formKey . '",
            isAjax: true
        }';
        $headers = '{
            "Content-type": "application/json",
            "Authorization": "Bearer ' . $this->getBearerToken->execute() . '"
        }';

        return ';jQuery.ajax({
            showLoader: true,
            url: "' . $url . '",
            data: JSON.stringify(' . $ajaxParams . '),
            method: "POST",
            contentType: "application/json; charset=UTF-8",
            headers: ' . $headers . ',
            beforeSend: function (xhr) {
                // Empty to remove Magento default handler
            }
        }).done(function (data) {
            require(["Magento_Ui/js/modal/alert"], function (alert) {
                function showAlert() {
                    alert({
                        title: data.status.toLowerCase().replace(/(?<= )[^\s]|^./g, a => a.toUpperCase()),
                        content: data.message,
                        clickableOverlay: false,
                        actions: {
                            always: function () {
                                window.location.reload();
                            }
                        }
                    });
                }
                showAlert();
            });
        });';
    }
}
