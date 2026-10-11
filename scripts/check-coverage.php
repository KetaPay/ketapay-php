<?php

// Fails when line coverage of src/ drops below the threshold. Raise it when you add tests; never lower it
// to get a PR through. Reads coverage/clover.xml from `composer test:ci`.

declare(strict_types=1);

const MIN_LINES = 90.0;

$file = dirname(__DIR__) . '/coverage/clover.xml';
if (!is_file($file)) {
    fwrite(STDERR, "No $file: run `composer test:ci` with pcov or xdebug enabled.\n");
    exit(1);
}
$xml = simplexml_load_file($file);
$metrics = $xml === false ? null : $xml->project->metrics;
$total = (int) ($metrics['statements'] ?? 0);
$covered = (int) ($metrics['coveredstatements'] ?? 0);
if ($total === 0) {
    fwrite(STDERR, "No coverage data in $file\n");
    exit(1);
}
$pct = round($covered / $total * 100, 2);
if ($pct < MIN_LINES) {
    fwrite(STDERR, "Line coverage $pct% ($covered/$total) is below " . MIN_LINES . "%.\n");
    exit(1);
}
echo "Line coverage $pct% ($covered/$total), threshold " . MIN_LINES . "%\n";
