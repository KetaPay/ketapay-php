<?php

// Starts a Prism mock from spec/v1.yaml (request validation on), runs the contract tests against it,
// then stops it. Exits with the tests' exit code. Needs Node (npx); extra arguments go to PHPUnit.

declare(strict_types=1);

const PRISM = '@stoplight/prism-cli@5.16.0';

$root = dirname(__DIR__);
$port = getenv('PRISM_PORT') ?: '4010';
$url = "http://127.0.0.1:$port";

$prism = proc_open(
    ['npx', '--yes', PRISM, 'mock', "$root/spec/v1.yaml", '--port', $port, '--errors'],
    [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']],
    $pipes,
);
if ($prism === false) {
    fwrite(STDERR, "Could not start Prism\n");
    exit(1);
}

$code = 1;
try {
    $up = false;
    for ($i = 0; $i < 120; $i++) {
        $conn = @fsockopen('127.0.0.1', (int) $port, $errno, $errstr, 0.5);
        if ($conn !== false) {
            fclose($conn);
            $up = true;
            break;
        }
        usleep(500_000);
    }
    if (!$up) {
        throw new RuntimeException('Prism did not start');
    }
    $tests = proc_open(
        [PHP_BINARY, "$root/vendor/bin/phpunit", '--testsuite', 'contract', ...array_slice($_SERVER['argv'] ?? [], 1)],
        [STDIN, STDOUT, STDERR],
        $pipes,
        $root,
        [...getenv(), 'KETAPAY_MOCK_URL' => $url],
    );
    $code = $tests === false ? 1 : proc_close($tests);
} finally {
    // npx starts Prism as a child: stop the whole group.
    $status = proc_get_status($prism);
    if ($status['running']) {
        exec('pkill -TERM -P ' . (int) $status['pid'] . ' 2>/dev/null');
        proc_terminate($prism);
    }
    proc_close($prism);
}
exit($code);
