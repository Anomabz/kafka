<?php

namespace App\Services\Kafka;

use RuntimeException;

class KafkaRestException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus = 0,
        public readonly mixed $errorCode = null,
    ) {
        parent::__construct($message);
    }

    /** Instance consumer sudah tidak dikenal proxy (kedaluwarsa/proxy restart). */
    public function isConsumerGone(): bool
    {
        return $this->httpStatus === 404 || (int) $this->errorCode === 40403;
    }
}
