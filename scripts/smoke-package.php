<?php

// Builds the package exactly as Packagist users download it (GitHub serves `git archive`, which honours
// .gitattributes export-ignore), installs it into an empty project, then uses it the way a customer would.
// It archives the working tree as it stands (minus .gitignore), through a throwaway git index.
// [PKG-001] Composer autoloading, and init.php for projects without Composer (WordPress plugins)
// [PKG-002] the public types check at PHPStan level max in a customer project, and catch a misuse
// [PKG-003] the archive holds only src/, init.php, composer.json, LICENSE and README.md
// [PKG-004] CI runs this on every supported PHP (8.1 to the newest)
// [PKG-005] alternative runtimes: the stream transport (hosts without ext-curl) over real HTTP, and one
//           client reused for many requests, as in long-running workers (Octane, RoadRunner, FrankenPHP)
// [PKG-006] KetaPay::VERSION equals the release version in .release-please-manifest.json
// [PKG-007] no runtime dependencies: Composer installs nothing but this package

declare(strict_types=1);

$root = dirname(__DIR__);
$manifest = json_decode((string) file_get_contents("$root/.release-please-manifest.json"), true);
$version = is_array($manifest) && is_string($manifest['.'] ?? null) ? $manifest['.'] : throw new RuntimeException('no version in .release-please-manifest.json');
$dir = sys_get_temp_dir() . '/ketapay-smoke-' . bin2hex(random_bytes(4));
mkdir($dir);

function run(string $command, ?string $cwd = null): string
{
    exec(($cwd !== null ? 'cd ' . escapeshellarg($cwd) . ' && ' : '') . $command . ' 2>&1', $out, $code);
    if ($code !== 0) {
        throw new RuntimeException("Failed: $command\n" . implode("\n", $out));
    }

    return implode("\n", $out);
}

function check(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

$server = null;
try {
    // The archive, as Packagist users get it.
    $index = 'GIT_INDEX_FILE=' . escapeshellarg("$dir/index");
    run("$index git add -A", $root);
    $tree = trim(run("$index git write-tree", $root));
    run('git archive --format=zip -o ' . escapeshellarg("$dir/package.zip") . ' ' . escapeshellarg($tree), $root);
    $zip = new ZipArchive();
    check($zip->open("$dir/package.zip") === true, 'cannot open the archive');
    $shipped = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string) $zip->getNameIndex($i);
        if (!str_ends_with($name, '/')) {
            $shipped[] = $name;
        }
    }
    $zip->extractTo("$dir/package");
    $zip->close();
    $unexpected = array_filter($shipped, static fn(string $f): bool => !preg_match('#^(src/.+\.php|init\.php|composer\.json|LICENSE|README\.md)$#', $f));
    check($unexpected === [], 'The archive would ship unexpected files: ' . implode(', ', $unexpected));
    check(in_array('src/KetaPay.php', $shipped, true) && in_array('src/Models/Escrow.php', $shipped, true), 'The archive is missing source files');
    echo 'archive ok: ' . count($shipped) . " files\n";

    // An empty customer project that requires it.
    mkdir("$dir/app");
    file_put_contents("$dir/app/composer.json", json_encode([
        'name' => 'smoke/app',
        'version' => '1.0.0',
        'require' => ['ketapay/ketapay-php' => "$version@beta"],
        'repositories' => [['type' => 'path', 'url' => "$dir/package", 'options' => ['symlink' => false, 'versions' => ['ketapay/ketapay-php' => $version]]]],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    run('composer install --no-interaction --no-progress', "$dir/app");
    $installed = json_decode((string) file_get_contents("$dir/app/vendor/composer/installed.json"), true);
    $names = array_column(is_array($installed) ? ($installed['packages'] ?? []) : [], 'name');
    check($names === ['ketapay/ketapay-php'], 'The package must have no runtime dependencies, Composer installed: ' . implode(', ', $names));
    $require = json_decode((string) file_get_contents("$dir/package/composer.json"), true)['require'] ?? [];
    check(array_filter(array_keys($require), static fn(string $p): bool => $p !== 'php' && !str_starts_with($p, 'ext-')) === [], 'composer.json requires packages');
    echo "install ok: no runtime dependencies\n";

    // A local server for real HTTP.
    $port = 0;
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr((string) strrchr((string) stream_socket_get_name($socket ?: throw new RuntimeException('no port'), false), ':'), 1);
    fclose($socket);
    $server = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", "$root/tests/Support/server.php"], [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $pipes);
    for ($i = 0; $i < 100 && @fsockopen('127.0.0.1', $port) === false; $i++) {
        usleep(50_000);
    }
    $base = "http://127.0.0.1:$port/echo";

    $customer = <<<'PHP'
        use KetaPay\Errors\InvalidRequestError;
        use KetaPay\Http\CurlTransport;
        use KetaPay\Http\StreamTransport;
        use KetaPay\KetaPay;
        use KetaPay\Webhook;

        [$_, $base, $expected] = $argv;
        foreach (['curl' => new CurlTransport(), 'streams' => new StreamTransport()] as $name => $transport) {
            $k = new KetaPay(['apiKey' => 'sk_test_smoke', 'baseUrl' => $base, 'transport' => $transport]);
            $echo = $k->escrows->retrieve('e1');
            if ($echo->path !== '/echo/escrows/e1' || $echo->getLastResponse()?->requestId !== 'req_local') {
                throw new RuntimeException("$name: request failed");
            }
            // Long-running workers reuse one client: many requests, no growing memory.
            $before = memory_get_usage();
            for ($i = 0; $i < 200; $i++) {
                $k->checkoutSessions->cancel("cs_$i");
            }
            if (memory_get_usage() - $before > 1_000_000) {
                throw new RuntimeException("$name: memory grew across requests");
            }
        }
        $body = '{"id":"evt_1","object":"event","type":"escrow.funded"}';
        if (Webhook::verify($body, Webhook::generateTestSignatureHeader($body, 'whsec_x'), 'whsec_x')->id !== 'evt_1') {
            throw new RuntimeException('webhook verify failed');
        }
        if (KetaPay::VERSION !== $expected) {
            throw new RuntimeException('KetaPay::VERSION is ' . KetaPay::VERSION . ", expected $expected");
        }
        if (!class_exists(InvalidRequestError::class)) {
            throw new RuntimeException('errors not autoloadable');
        }
        echo 'ok on PHP ' . PHP_VERSION . "\n";
        PHP;
    file_put_contents("$dir/app/composer-autoload.php", "<?php\n\ndeclare(strict_types=1);\n\nrequire __DIR__ . '/vendor/autoload.php';\n\n$customer");
    file_put_contents("$dir/app/no-composer.php", "<?php\n\ndeclare(strict_types=1);\n\nrequire __DIR__ . '/../package/init.php';\n\n$customer");
    foreach (['composer-autoload.php', 'no-composer.php'] as $script) {
        echo "$script: " . run(PHP_BINARY . " $script " . escapeshellarg($base) . ' ' . escapeshellarg($version), "$dir/app") . "\n";
    }

    // The public types, checked the way a customer's CI would.
    file_put_contents("$dir/app/typed.php", <<<'PHP'
        <?php

        declare(strict_types=1);

        use KetaPay\Errors\ConflictError;
        use KetaPay\KetaPay;
        use KetaPay\Models\Escrow;

        function payable(KetaPay $k, string $id): int
        {
            $escrow = $k->escrows->retrieve($id);
            $total = 0;
            foreach ($escrow->milestones as $milestone) {
                $total += $milestone->seller_payout;
            }

            return $total;
        }

        /** @return list<string> */
        function activeIds(KetaPay $k): array
        {
            $ids = [];
            foreach ($k->escrows->list(['status' => 'active']) as $escrow) {
                $ids[] = $escrow->id;
            }

            return $ids;
        }

        function release(KetaPay $k, Escrow $escrow, string $token): string
        {
            try {
                return $k->escrows->milestones->release($escrow->id, $escrow->milestones[0]->id, [], ['customerToken' => $token])->status;
            } catch (ConflictError $e) {
                return $e->code;
            }
        }
        PHP);
    file_put_contents("$dir/app/misuse.php", "<?php\n\ndeclare(strict_types=1);\n\nfunction misuse(KetaPay\\KetaPay \$k): int\n{\n    return \$k->escrows->retrieve('e1')->status;\n}\n");
    $phpstan = "$root/vendor/bin/phpstan analyse --no-progress --level=max --autoload-file=vendor/autoload.php --memory-limit=1G";
    run("$phpstan typed.php", "$dir/app");
    $misuse = '';
    try {
        run("$phpstan misuse.php", "$dir/app");
    } catch (RuntimeException $e) {
        $misuse = $e->getMessage();
    }
    check(str_contains($misuse, 'should return int but returns string'), "PHPStan should reject returning a string status as int:\n$misuse");
    echo "types ok (PHPStan level max)\n";

    echo "smoke test passed for ketapay/ketapay-php $version on PHP " . PHP_VERSION . "\n";
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }
    run('rm -rf ' . escapeshellarg($dir));
}
