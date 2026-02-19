#!/usr/bin/env php
<?php
// Berlioz Framework – prepare-release.php
// Logic:
// 1. Load config.subsplit-publish.json to find packages.
// 2. For each package: Merge Unreleased -> Version, collect items.
// 3. For Root: Aggregate package items into root CHANGELOG with nested scoping.

declare(strict_types=1);

// ------- Args & Setup -----------------------------------------------------
$args = array_values(array_slice($argv, 1));
$dryRun = false;
$help = false;
$filtered = [];
foreach ($args as $a) {
    if ($a === '--dry-run') {
        $dryRun = true;
    } elseif ($a === '--help' || $a === '-h') {
        $help = true;
    } else {
        $filtered[] = $a;
    }
}
$args = $filtered;

$rootDir = realpath(__DIR__ . '/..') ?: getcwd();
$rootFile = $rootDir . '/CHANGELOG.md';
$configFile = $rootDir . '/config.subsplit-publish.json';

// --- Loading Subsplit Config ---
if (!is_file($configFile)) {
    fwrite(STDERR, "Error: config.subsplit-publish.json not found at $configFile\n");
    exit(1);
}
$configJson = file_get_contents($configFile);
$config = json_decode($configJson, true);
if (!is_array($config) || empty($config['sub-splits'])) {
    fwrite(STDERR, "Error: config.subsplit-publish.json is malformed or contains no sub-splits.\n");
    exit(1);
}
$subsplits = $config['sub-splits'];
$validPkgNames = array_column($subsplits, 'name');

// --- Help ---
if ($help || count($args) < 2) {
    $usage = <<<USAGE
    Usage: php bin/prepare-release.php <version> <date:YYYY-MM-DD> [package-names...] [--dry-run] [--help]

    Arguments:
      version       Semantic version number (e.g. 3.1.0, 3.1.0-beta.1)
      date          Release date in YYYY-MM-DD format

    Options:
      --dry-run     Preview changes without writing files
      --help, -h    Show this help message

    Available packages:
      %s

    Examples:
      php bin/prepare-release.php 3.1.0 2026-03-15              # All packages
      php bin/prepare-release.php 3.1.0 2026-03-15 router core  # Specific packages
      php bin/prepare-release.php 3.1.0 2026-03-15 --dry-run    # Preview only
    USAGE;

    fwrite($help ? STDOUT : STDERR, sprintf($usage, implode(', ', $validPkgNames)) . "\n");
    exit($help ? 0 : 1);
}

$version = $args[0];
$date = $args[1];
$explicitPkgs = array_slice($args, 2);

// --- Validate version ---
if (!preg_match('/^\d+\.\d+\.\d+(?:-[\w.]+)?$/', $version)) {
    fwrite(STDERR, "Error: Invalid version format '$version'. Expected semver (e.g. 3.1.0, 3.1.0-beta.1).\n");
    exit(1);
}

// --- Validate date ---
if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $dateMatch) || !checkdate((int)$dateMatch[2], (int)$dateMatch[3], (int)$dateMatch[1])) {
    fwrite(STDERR, "Error: Invalid date format '$date'. Expected YYYY-MM-DD with a valid date.\n");
    exit(1);
}

// --- Validate explicit package names ---
if (!empty($explicitPkgs)) {
    $unknownPkgs = array_diff($explicitPkgs, $validPkgNames);
    if (!empty($unknownPkgs)) {
        fwrite(STDERR, "Error: Unknown package(s): " . implode(', ', $unknownPkgs) . "\n");
        fwrite(STDERR, "Available packages: " . implode(', ', $validPkgNames) . "\n");
        exit(1);
    }
}

// Standard Keep a Changelog order
const CATEGORY_ORDER = ['Added', 'Changed', 'Deprecated', 'Removed', 'Fixed', 'Security', 'Docs'];
const PLACEHOLDER = '_No changes in this release._';

// ------- Helpers ----------------------------------------------------------

function normEol(string $s): string
{
    return str_replace(["\r\n", "\r"], "\n", $s);
}

function extractRawSection(string $md, string $headerRegex): ?array
{
    if (preg_match($headerRegex, $md, $m, PREG_OFFSET_CAPTURE)) {
        $start = (int)$m[0][1];
        $headerLen = strlen($m[0][0]);
        $bodyStart = $start + $headerLen;
        if (preg_match('/^##\s*\[/m', substr($md, $bodyStart), $nextM, PREG_OFFSET_CAPTURE)) {
            $len = $nextM[0][1];
        } else {
            $len = strlen($md) - $bodyStart;
        }
        return [
            'full_match' => substr($md, $start, $headerLen + $len),
            'body' => trim(substr($md, $bodyStart, $len)),
            'start' => $start,
            'length' => $headerLen + $len
        ];
    }
    return null;
}

function extractItems(string $text): array
{
    $out = [];
    foreach (explode("\n", trim($text)) as $l) {
        if (preg_match('/^\s*[-*]\s+(.*)$/', $l, $matches)) {
            $out[] = trim($matches[1]);
        }
    }
    return $out;
}

function parseKeepAChangelog(string $body): array
{
    $sections = [];
    $current = null;
    $buf = '';
    $map = [
        'added' => 'Added',
        'new' => 'Added',
        'changed' => 'Changed',
        'updated' => 'Changed',
        'deprecated' => 'Deprecated',
        'removed' => 'Removed',
        'fixed' => 'Fixed',
        'bugfix' => 'Fixed',
        'security' => 'Security',
        'docs' => 'Docs',
    ];
    foreach (explode("\n", $body) as $line) {
        if (preg_match('/^###\s+(.+?)\s*$/', $line, $m)) {
            if ($current !== null && isset($map[$current])) {
                $sections[$map[$current]] = array_merge($sections[$map[$current]] ?? [], extractItems($buf));
            }
            $current = strtolower(trim($m[1]));
            $buf = '';
            continue;
        }
        $buf .= $line . "\n";
    }
    if ($current !== null && isset($map[$current])) {
        $sections[$map[$current]] = array_merge($sections[$map[$current]] ?? [], extractItems($buf));
    }
    if (empty($sections) && trim($body) !== '' && trim($body) !== PLACEHOLDER) {
        $items = extractItems($body);
        if (!empty($items)) {
            $sections['Changed'] = $items;
        }
    }
    return $sections;
}

function rebuildBody(array $categories): string
{
    $output = "";
    foreach (CATEGORY_ORDER as $cat) {
        if (empty($categories[$cat])) {
            continue;
        }
        $sb = "### $cat\n\n";
        foreach ($categories[$cat] as $item) {
            $sb .= "- $item\n";
        }
        $output .= $sb . "\n";
    }
    return trim($output);
}

// ------- Core Logic -------------------------------------------------------

echo "Preparing release $version ($date) for Berlioz Framework...";
if ($dryRun) {
    echo " [DRY-RUN]";
}
echo "\n";

$packageBuckets = [];
$processedCount = 0;

foreach ($subsplits as $sub) {
    $pkgName = $sub['name'];
    $pkgDir = $rootDir . '/' . $sub['directory'];

    if (!empty($explicitPkgs) && !in_array($pkgName, $explicitPkgs)) {
        continue;
    }

    $clPath = $pkgDir . '/CHANGELOG.md';
    if (!is_file($clPath)) {
        fwrite(STDERR, "  [SKIP] $pkgName: no CHANGELOG.md found.\n");
        continue;
    }

    $content = normEol(file_get_contents($clPath));
    $reUnreleased = '/^##\s*\[?Unreleased\]?\s*$/m';
    $reVersion = '/^##\s*\[' . preg_quote($version, '/') . '\]\s*(?:-\s*[\d-]{10})?\s*$/m';

    $blockUnreleased = extractRawSection($content, $reUnreleased);
    $blockVersion = extractRawSection($content, $reVersion);

    $finalVersionBody = '';
    if ($blockUnreleased) {
        if ($blockVersion) {
            // Ensure [Unreleased] appears before [$version] in the file
            if ($blockUnreleased['start'] > $blockVersion['start']) {
                fwrite(STDERR, "  [ERROR] $pkgName: [Unreleased] section appears after [$version] in CHANGELOG. Skipping to avoid corruption.\n");
                continue;
            }

            $unreleasedItems = parseKeepAChangelog($blockUnreleased['body']);
            $versionItems = parseKeepAChangelog($blockVersion['body']);
            $mergedItems = array_merge_recursive($versionItems, $unreleasedItems);
            $finalVersionBody = rebuildBody($mergedItems) ?: PLACEHOLDER;

            $newVersionBlock = "## [$version] - $date\n\n" . $finalVersionBody . "\n\n";
            $newUnreleasedBlock = "## [Unreleased]\n\n";

            $head = substr($content, 0, $blockUnreleased['start']);
            $between = substr($content, $blockUnreleased['start'] + $blockUnreleased['length'],
                $blockVersion['start'] - ($blockUnreleased['start'] + $blockUnreleased['length']));
            $tail = substr($content, $blockVersion['start'] + $blockVersion['length']);
            $newPkgContent = $head . $newUnreleasedBlock . $between . $newVersionBlock . $tail;
        } else {
            $finalVersionBody = $blockUnreleased['body'] ?: PLACEHOLDER;
            $replacement = "## [Unreleased]\n\n## [$version] - $date\n\n" . $finalVersionBody . "\n\n";
            $newPkgContent = substr_replace($content, $replacement, $blockUnreleased['start'],
                $blockUnreleased['length']);
        }

        if ($dryRun) {
            echo "  [DRY-RUN] $pkgName would be updated.\n";
        } else {
            if (file_put_contents($clPath, $newPkgContent) === false) {
                fwrite(STDERR, "  [ERROR] $pkgName: failed to write CHANGELOG at $clPath\n");
                continue;
            }
            echo "  [OK] $pkgName updated.\n";
        }
        $processedCount++;
    }

    // --- Aggregate for Root with Scope Transformation ---
    if ($finalVersionBody !== '' && trim($finalVersionBody) !== PLACEHOLDER) {
        $cats = parseKeepAChangelog($finalVersionBody);
        foreach ($cats as $cat => $items) {
            foreach ($items as $it) {
                $packageBuckets[$cat][] = "[$pkgName] $it";
            }
        }
    }
}

if ($processedCount === 0) {
    fwrite(STDERR, "Warning: No packages were processed. Check that CHANGELOG.md files exist and contain an [Unreleased] section.\n");
}

// --- PART 2: UPDATE ROOT CHANGELOG ---

if (is_file($rootFile)) {
    $rootContent = normEol(file_get_contents($rootFile));
    $reRootUnreleased = '/^##\s*\[?Unreleased\]?\s*$/m';
    $reRootVersion = '/^##\s*\[' . preg_quote($version, '/') . '\]\s*(?:-\s*[\d-]{10})?\s*$/m';

    $blkRootUnrel = extractRawSection($rootContent, $reRootUnreleased);
    $blkRootVer = extractRawSection($rootContent, $reRootVersion);

    $allRootItems = [];
    $existingItems = $blkRootVer ? parseKeepAChangelog($blkRootVer['body']) : [];
    $unreleasedItems = $blkRootUnrel ? parseKeepAChangelog($blkRootUnrel['body']) : [];

    foreach (CATEGORY_ORDER as $cat) {
        $combined = array_merge($existingItems[$cat] ?? [], $unreleasedItems[$cat] ?? [], $packageBuckets[$cat] ?? []);
        $allRootItems[$cat] = array_values(array_unique(array_map('trim', $combined)));
    }

    $newRootSection = "## [$version] - $date\n\n" . rebuildBody($allRootItems) . "\n\n";
    $emptyUnrel = "## [Unreleased]\n\n";

    if ($blkRootVer && $blkRootUnrel) {
        if ($blkRootUnrel['start'] > $blkRootVer['start']) {
            fwrite(STDERR, "Error: Root CHANGELOG has [Unreleased] after [$version]. Skipping root update to avoid corruption.\n");
        } else {
            $parts = [
                substr($rootContent, 0, $blkRootUnrel['start']),
                $emptyUnrel,
                substr($rootContent, $blkRootUnrel['start'] + $blkRootUnrel['length'],
                    $blkRootVer['start'] - ($blkRootUnrel['start'] + $blkRootUnrel['length'])),
                $newRootSection,
                substr($rootContent, $blkRootVer['start'] + $blkRootVer['length'])
            ];
            $newRootContent = implode('', $parts);
        }
    } elseif ($blkRootUnrel) {
        $newRootContent = substr_replace($rootContent, $emptyUnrel . $newRootSection, $blkRootUnrel['start'],
            $blkRootUnrel['length']);
    } else {
        $newRootContent = $rootContent . "\n" . $newRootSection;
    }

    if (isset($newRootContent)) {
        if ($dryRun) {
            echo "\n  [DRY-RUN] Root CHANGELOG would be updated with:\n";
            echo "  ┌──────────────────────────────────────────────\n";
            foreach (explode("\n", rtrim($newRootSection)) as $line) {
                echo "  │ $line\n";
            }
            echo "  └──────────────────────────────────────────────\n";
        } else {
            if (file_put_contents($rootFile, $newRootContent) === false) {
                fwrite(STDERR, "Error: Failed to write root CHANGELOG at $rootFile\n");
            } else {
                echo "Root CHANGELOG updated.\n";
            }
        }
    }
}

echo "Done. You can now commit with: git commit -m \"chore(release): prepare v$version\"\n";
