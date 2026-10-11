<?php

declare(strict_types=1);

namespace KetaPay\Tests\Support;

/** Starts `php -S` with tests/Support/server.php once per test run. */
final class LocalServer
{
    private static ?string $url = null;

    /** @var resource|null */
    private static $process = null;

    public static function url(): string
    {
        if (self::$url !== null) {
            return self::$url;
        }
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        if ($socket === false) {
            throw new \RuntimeException('no free port');
        }
        $port = (int) substr((string) strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        $process = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:$port", __DIR__ . '/server.php'],
            [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']],
            $pipes,
            null,
            null,
        );
        if ($process === false) {
            throw new \RuntimeException('could not start php -S');
        }
        self::$process = $process;
        register_shutdown_function(static fn() => is_resource(self::$process) ? proc_terminate(self::$process) : null);

        for ($i = 0; $i < 100; $i++) {
            $conn = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
            if ($conn !== false) {
                fclose($conn);

                return self::$url = "http://127.0.0.1:$port";
            }
            usleep(50_000);
        }
        throw new \RuntimeException('php -S did not start');
    }
}
