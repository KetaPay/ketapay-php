<?php

declare(strict_types=1);

namespace KetaPay\Errors;

/**
 * Base class for every error this library throws. Branch on `$e->code`, never on the message.
 *
 * `$code` is the KetaPay error code (a string such as `dispute_open`), like PDOException's SQLSTATE.
 */
class KetaPayError extends \RuntimeException
{
    /** @var string */
    public $code;

    /**
     * @param 'invalid_request_error'|'authentication_error'|'permission_error'|'idempotency_error'|'rate_limit_error'|'api_error'|'connection_error'|'aborted'|'webhook_signature_error' $type
     */
    public function __construct(
        string $message,
        public readonly string $type,
        string $code,
        public readonly ?string $param = null,
        public readonly ?int $statusCode = null,
        public readonly ?string $requestId = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
        $this->code = $code;
    }
}
