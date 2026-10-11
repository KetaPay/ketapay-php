<?php

// [SPEC-005] Fails when src/Models, src/Enums or src/Types no longer match the spec snapshot (edited by hand, or not
// regenerated after a sync).

declare(strict_types=1);

$root = dirname(__DIR__);
passthru(PHP_BINARY . ' ' . escapeshellarg("$root/scripts/generate.php"), $code);
if ($code !== 0) {
    exit($code);
}
// Changed or deleted against the index (what CI checked out, or what you staged), plus new untracked files.
$git = 'git -C ' . escapeshellarg($root);
exec("$git diff --name-only -- src/Models src/Enums src/Types", $changed, $code1);
exec("$git ls-files --others --exclude-standard -- src/Models src/Enums src/Types", $added, $code2);
if ($code1 !== 0 || $code2 !== 0) {
    fwrite(STDERR, "git failed\n");
    exit(1);
}
$changes = [...$changed, ...$added];
if ($changes !== []) {
    fwrite(STDERR, "src/Models, src/Enums or src/Types is out of date: run `composer generate` and commit the result.\n" . implode("\n", $changes) . "\n");
    passthru('git -C ' . escapeshellarg($root) . ' --no-pager diff --stat -- src/Models src/Enums src/Types');
    exit(1);
}
echo "generated models match the spec\n";
