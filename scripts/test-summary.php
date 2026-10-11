<?php

// Turns PHPUnit's JUnit results and the coverage summary into a Markdown report for the GitHub run
// summary page: totals, coverage, then every test with a pass or fail mark, grouped by class.
// Usage: php scripts/test-summary.php >> "$GITHUB_STEP_SUMMARY"

declare(strict_types=1);

$root = dirname(__DIR__);
$suites = [
    'Unit tests' => "$root/reports/unit.xml",
    'Contract tests (SDK requests against the spec)' => "$root/reports/contract.xml",
    'End-to-end tests (staging)' => "$root/reports/e2e.xml",
];

/** @return list<SimpleXMLElement> every <testcase>, however deeply suites are nested */
function cases(SimpleXMLElement $node): array
{
    return array_values($node->xpath('//testcase') ?: []);
}

function status(SimpleXMLElement $case): string
{
    return match (true) {
        isset($case->failure), isset($case->error) => 'failed',
        isset($case->skipped) => 'skipped',
        default => 'passed',
    };
}

$out = ['## Test results', '', '| Suite | Result | Passed | Failed | Skipped | Time |', '| --- | --- | --- | --- | --- | --- |'];
$results = [];
foreach ($suites as $title => $file) {
    if (!is_file($file) || ($xml = simplexml_load_file($file)) === false) {
        continue;
    }
    $all = cases($xml);
    $count = array_count_values(array_map('status', $all));
    $time = array_sum(array_map(static fn(SimpleXMLElement $c): float => (float) $c['time'], $all));
    $failed = $count['failed'] ?? 0;
    $out[] = sprintf('| %s | %s | %d | %d | %d | %.1fs |', $title, $failed === 0 ? '✅ Passed' : '❌ Failed', $count['passed'] ?? 0, $failed, $count['skipped'] ?? 0, $time);
    $results[$title] = $all;
}
if ($results === []) {
    $out[] = '| No results found | ❌ | | | | |';
}

$clover = "$root/coverage/clover.xml";
$xml = is_file($clover) ? simplexml_load_file($clover) : false;
if ($xml !== false) {
    $m = $xml->project->metrics;
    $cell = static function (string $covered, string $total) use ($m): string {
        $t = (int) $m[$total];

        return $t === 0 ? 'n/a' : sprintf('%.2f%% (%d/%d)', (int) $m[$covered] / $t * 100, (int) $m[$covered], $t);
    };
    $out[] = '';
    $out[] = '## Coverage';
    $out[] = '';
    $out[] = '| Lines | Methods |';
    $out[] = '| --- | --- |';
    $out[] = sprintf('| %s | %s |', $cell('coveredstatements', 'statements'), $cell('coveredmethods', 'methods'));
    $out[] = '';
    $out[] = 'The full HTML report is attached to this run as the `coverage-report` artifact.';
}

// Every test by its readable title (with catalogue IDs) from the TestDox file next to each JUnit file,
// then the failure messages from JUnit.
foreach ($results as $title => $all) {
    $out[] = '';
    $out[] = "## $title";
    $testdox = preg_replace('/\.xml$/', '-testdox.txt', $suites[$title]);
    $groups = is_string($testdox) && is_file($testdox) ? preg_split('/\n\s*\n/', trim((string) file_get_contents($testdox))) : [];
    foreach ($groups ?: [] as $group) {
        $lines = explode("\n", $group);
        $heading = (string) array_shift($lines);
        $failed = count(array_filter($lines, static fn(string $l): bool => str_starts_with(ltrim($l), '[ ]')));
        $class = preg_match('/\((?:KetaPay\\\\Tests\\\\)?(.+)\)$/', $heading, $m) ? $m[1] : $heading;
        $out[] = '';
        $out[] = sprintf('<details%s><summary>%s <code>%s</code> (%d tests)</summary>', $failed > 0 ? ' open' : '', $failed > 0 ? '❌' : '✅', $class, count($lines));
        $out[] = '';
        foreach ($lines as $line) {
            $line = ltrim($line);
            $out[] = '- ' . (str_starts_with($line, '[x]') ? '✅' : '❌') . ' ' . htmlspecialchars(substr($line, 4));
        }
        $out[] = '';
        $out[] = '</details>';
    }
    foreach ($all as $case) {
        if (status($case) === 'failed') {
            $out[] = '';
            $out[] = '❌ **' . htmlspecialchars((string) $case['class'] . '::' . (string) $case['name']) . '**';
            $out[] = '```';
            $out[] = implode("\n", array_slice(explode("\n", trim((string) ($case->failure ?? $case->error))), 0, 8));
            $out[] = '```';
        }
    }
}

echo implode("\n", $out), "\n";
