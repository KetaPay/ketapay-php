<?php

declare(strict_types=1);

namespace KetaPay\Tests\Unit;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * Every scenario in the shared SDK test catalogue (spec/test-catalogue.md, synced from the documentation
 * repo) must have at least one test tagged with its ID, and no test may carry an ID the catalogue doesn't
 * have. Adding a scenario to the catalogue fails this test until it is covered here.
 */
final class CatalogueCoverageTest extends TestCase
{
    // IDs look like SEC-002 or E2E-014: a letter, then letters or digits, a dash and three digits.
    private const ID = '[A-Z][A-Z0-9]{1,4}-\d{3}';

    /** @return array<string, string> ID => "layer: must hold" */
    private static function catalogue(): array
    {
        $ids = [];
        foreach (file(__DIR__ . '/../../spec/test-catalogue.md', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (preg_match('/^\| (' . self::ID . ') \| (\w+) \| (.+) \|$/', $line, $m)) {
                $ids[$m[1]] = "$m[2]: $m[3]";
            }
        }

        return $ids;
    }

    /** @return array<string, list<string>> ID => files tagging it, from test names and the package-check scripts */
    private static function tagged(): array
    {
        $tagged = [];
        foreach (['tests', 'scripts'] as $dir) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . "/../../$dir", \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                    continue;
                }
                preg_match_all('/\[(' . self::ID . ')\]/', (string) file_get_contents($file->getPathname()), $m);
                foreach ($m[1] as $id) {
                    $tagged[$id][] = $file->getFilename();
                }
            }
        }

        return $tagged;
    }

    #[TestDox('reads the catalogue')]
    public function testReadsCatalogue(): void
    {
        self::assertGreaterThan(50, count(self::catalogue()));
        self::assertArrayHasKey('E2E-001', self::catalogue());
    }

    #[TestDox('[SPEC-006] has a tagged test for every scenario in the catalogue')]
    public function testEveryScenarioTagged(): void
    {
        // A retired row keeps its ID (never reused) but needs no test
        $live = array_filter(self::catalogue(), static fn(string $what): bool => !str_contains($what, ': retired'));
        $missing = array_diff_key($live, self::tagged());
        $list = implode('; ', array_map(static fn(string $id, string $what): string => "$id ($what)", array_keys($missing), $missing));
        self::assertSame([], array_keys($missing), "Write tests tagged with: $list");
    }

    #[TestDox('[SPEC-006] tags no test with an ID the catalogue does not have')]
    public function testNoUnknownTags(): void
    {
        $unknown = array_keys(array_diff_key(self::tagged(), self::catalogue()));
        self::assertSame([], $unknown, 'Not in the catalogue: ' . implode(', ', $unknown));
    }
}
