<?php

// [SPEC-004] Fails when a synced file was edited by hand: its contents must match the hash sync-spec wrote.

declare(strict_types=1);

$failed = false;
foreach (['spec/v1.yaml', 'spec/test-catalogue.md'] as $file) {
    $text = (string) file_get_contents(__DIR__ . "/../$file");
    if (!preg_match('/^(?:# |<!-- )GENERATED: .*\n(?:# |<!-- )sha256: ([0-9a-f]{64})(?: -->)?\n\n/', $text, $m)) {
        fwrite(STDERR, "$file has no GENERATED header. Update it with `composer sync-spec`, never by hand.\n");
        $failed = true;
        continue;
    }
    if (!hash_equals($m[1], hash('sha256', substr($text, strlen($m[0]))))) {
        fwrite(STDERR, "$file was edited by hand. Change it in the documentation repo, then `composer sync-spec`.\n");
        $failed = true;
        continue;
    }
    echo "$file matches its recorded hash\n";
}
exit($failed ? 1 : 0);
