<?php

declare(strict_types=1);

namespace KetaPay\Http;

/**
 * Drops messages below the configured level and prefixes the rest. Internal.
 *
 * The target is a PSR-3 logger (anything with `log($level, $message, $context)`, such as Monolog or
 * Laravel's `Log`), a callable `fn (string $level, string $message, array $context)`, or null for
 * PHP's `error_log`.
 *
 * @internal
 */
final class Logger
{
    private const RANK = ['off' => 0, 'error' => 1, 'warn' => 2, 'info' => 3, 'debug' => 4];
    private const PSR_LEVEL = ['error' => 'error', 'warn' => 'warning', 'info' => 'info', 'debug' => 'debug'];

    private readonly int $rank;

    public function __construct(string $level = 'warn', private readonly mixed $target = null)
    {
        if (!isset(self::RANK[$level])) {
            throw new \InvalidArgumentException('KetaPay: logLevel must be one of off, error, warn, info, debug');
        }
        if ($target !== null && !is_callable($target) && !(is_object($target) && method_exists($target, 'log'))) {
            throw new \InvalidArgumentException('KetaPay: logger must be a PSR-3 logger or a callable');
        }
        $this->rank = self::RANK[$level];
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function warn(string $message, array $context = []): void
    {
        $this->write('warn', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->write('info', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function debug(string $message, array $context = []): void
    {
        $this->write('debug', $message, $context);
    }

    /** @param array<string, mixed> $context */
    private function write(string $level, string $message, array $context): void
    {
        if ($this->rank < self::RANK[$level]) {
            return;
        }
        $message = "[ketapay] $message";
        $target = $this->target;
        if ($target === null) {
            error_log($message . ' ' . json_encode($context, JSON_UNESCAPED_SLASHES));
        } elseif (is_object($target) && method_exists($target, 'log')) {
            $target->log(self::PSR_LEVEL[$level], $message, $context);
        } elseif (is_callable($target)) {
            $target(self::PSR_LEVEL[$level], $message, $context);
        }
    }
}
