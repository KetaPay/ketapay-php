<?php

// Copies the bundled public spec and the SDK test catalogue from the documentation repo (their source of
// truth) into spec/, each with a GENERATED header and a hash of its contents, so hand edits fail CI.
// Usage: composer sync-spec [-- path/to/documentation/openapi/public]

declare(strict_types=1);

$dir = $argv[1] ?? __DIR__ . '/../../documentation/openapi/public';
$files = [
    ['from' => "$dir/dist/v1.yaml", 'to' => __DIR__ . '/../spec/v1.yaml', 'comment' => fn(string $s): string => "# $s"],
    ['from' => "$dir/SDK_TEST_CATALOGUE.md", 'to' => __DIR__ . '/../spec/test-catalogue.md', 'comment' => fn(string $s): string => "<!-- $s -->"],
];

foreach ($files as $file) {
    if (!is_file($file['from'])) {
        fwrite(STDERR, "Not found: {$file['from']}. Run `npm run public:bundle` in the documentation repo.\n");
        exit(1);
    }
    $body = (string) file_get_contents($file['from']);
    $header = $file['comment']('GENERATED: do not edit. Copied from documentation/openapi/public by `composer sync-spec`.') . "\n"
        . $file['comment']('sha256: ' . hash('sha256', $body)) . "\n\n";
    file_put_contents($file['to'], $header . $body);
    echo "Copied {$file['from']} -> " . basename(dirname($file['to'])) . '/' . basename($file['to']) . "\n";
}
echo "Now run: composer generate && composer test && composer test:contract\n";
