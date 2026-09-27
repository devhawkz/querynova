<?php
/**
 * Structured log entry.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Logging;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class LogRecord {

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public readonly \DateTimeImmutable $timestamp,
        public readonly string $level,
        public readonly string $channel,
        public readonly string $message,
        public readonly string $environment,
        public readonly string $pluginVersion,
        public readonly string $wordpressVersion,
        public readonly string $woocommerceVersion,
        public readonly string $requestId,
        public readonly string $correlationId,
        public readonly ?string $jobId,
        public readonly string $module,
        public readonly string $provider,
        public readonly array $context,
        public readonly string $exceptionClass,
        public readonly string $exceptionCode,
        public readonly string $errorReference,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array {
        return [
            'timestamp'           => $this->timestamp->format( 'c' ),
            'level'               => $this->level,
            'channel'             => $this->channel,
            'message'             => $this->message,
            'environment'         => $this->environment,
            'plugin_version'      => $this->pluginVersion,
            'wordpress_version'   => $this->wordpressVersion,
            'woocommerce_version' => $this->woocommerceVersion,
            'request_id'          => $this->requestId,
            'correlation_id'      => $this->correlationId,
            'job_id'              => $this->jobId,
            'module'              => $this->module,
            'provider'            => $this->provider,
            'context'             => $this->context,
            'exception_class'     => $this->exceptionClass,
            'exception_code'      => $this->exceptionCode,
            'error_reference'     => $this->errorReference,
        ];
    }
}
