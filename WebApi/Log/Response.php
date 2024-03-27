<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\WebApi\Log;

use Klevu\LoggerApi\Api\Data\LogResponseInterface;
use Magento\Framework\Phrase;

class Response implements LogResponseInterface
{
    /**
     * @var int
     */
    private int $code = 0;
    /**
     * @var Phrase|null
     */
    private ?Phrase $message = null;
    /**
     * @var string
     */
    private string $status = '';

    /**
     * @return int
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * @param int $code
     *
     * @return void
     */
    public function setCode(int $code): void
    {
        $this->code = $code;
    }

    /**
     * @return string|null
     */
    public function getMessage(): ?string
    {
        return $this->message?->render();
    }

    /**
     * @param Phrase|null $message
     *
     * @return void
     */
    public function setMessage(?Phrase $message): void
    {
        $this->message = $message;
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @param string $status
     *
     * @return void
     */
    public function setStatus(string $status): void
    {
        $this->status = $status;
    }
}
