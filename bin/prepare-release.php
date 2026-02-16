#!/usr/bin/env php
<?php
// Berlioz Framework – prepare-release.php
// Logic:
// 1. Load config.subsplit-publish.json to find packages.
// 2. For each package: Merge Unreleased -> Version, collect items.
// 3. For Root: Aggregate package items into root CHANGELOG.

declare(strict_types=1);

// ------- Args & Setup -----------------------------------------------------
$args = array_values(array_slice($argv, 1));
$dryRun = false;
$filtered = [];
foreach ($args as $a) {
    if ($a === '--dry-run') {
        $dryRun = true;
    } else {
        $filtered[] = $a;
    }
}
$args = $filtered;

if (count($args) < 2) {
    fwrite(STDERR, "Usage: php bin/prepare-release.php <version> <date:YYYY-MM-DD> [PackageName ...] [--dry-run]\n");
    exit(1);
}

$version = $args[0];
$date = $args[1];
$explicitPkgs = array_slice($args, 2);

$rootDir = realpath(__DIR__ . '/..') ?: getcwd();
$rootFile = $rootDir . '/CHANGELOG.md';
$configFile = $rootDir . '/config.subsplit-publish.json';

// --- Loading Subsplit Config ---
if (!is_file($configFile)) {
    fwrite(STDERR, "Error: config.subsplit-publish.json not found at $configFile\n");
    exit(1);
}
$config = json_decode(file_get_contents($configFile), true);
$subsplits = $config['sub-splits'] ?? [];

// Standard Keep a Changelog order
const CATEGORY_ORDER = ['Added', 'Changed', 'Deprecated', 'Removed', 'Fixed', 'Security', 'Docs'];
const PLACEHOLDER = '_No changes in this release._';

// ------- Helpers (reused from your original script) -----------------------

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

echo "Preparing release $version ($date) for Berlioz Framework...\n";
$packageBuckets = [];

foreach ($subsplits as $sub) {
    $pkgName = $sub['name'];
    $pkgDir = $rootDir . '/' . $sub['directory'];

    // Si on a spécifié des packages en argument, on ignore les autres
    if (!empty($explicitPkgs) && !in_array($pkgName, $explicitPkgs)) {
        continue;
    }

    $clPath = $pkgDir . '/CHANGELOG.md';
    if (!is_file($clPath)) {
        echo "  [SKIP] $pkgName: No CHANGELOG.md found in " . $sub['directory'] . "\n";
        continue;
    }

    $content = normEol(file_get_contents($clPath));
    $reUnreleased = '/^##\s*\[?Unreleased\]?\s*$/m';
    $reVersion = '/^##\s*\[' . preg_quote($version, '/') . '\]\s*(?:-\s*[\d-]{10})?\s*$/m';

    $blockUnreleased = extractRawSection($content, $reUnreleased);
    $blockVersion = extractRawSection($content, $reVersion);

    $finalVersionBody = '';
    $newPkgContent = $content;

    if ($blockUnreleased) {
        $actionLog = "";
        // Merge or Rename logic (same as your original script)
        if ($blockVersion) {
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
            $actionLog = "Merged into existing [$version]";
        } else {
            $finalVersionBody = $blockUnreleased['body'] ?: PLACEHOLDER;
            $replacement = "## [Unreleased]\n\n## [$version] - $date\n\n" . $finalVersionBody . "\n\n";
            $newPkgContent = substr_replace($content, $replacement, $blockUnreleased['start'],
                $blockUnreleased['length']);
            $actionLog = "Tagged as [$version]";
        }

        if (!$dryRun) {
            file_put_contents($clPath, $newPkgContent);
            echo "  [OK]   $pkgName: $actionLog\n";
        } else {
            echo "  [DRY]  $pkgName: $actionLog\n";
        }
    }

    // --- Aggregate for Root ---
    if ($finalVersionBody !== '' && trim($finalVersionBody) !== PLACEHOLDER) {
        $cats = parseKeepAChangelog($finalVersionBody);
        // Prefix with berlioz/ (or whatever prefix you use in config)
        $prefix = 'berlioz/' . $pkgName;
        foreach ($cats as $cat => $items) {
            foreach ($items as $it) {
                $packageBuckets[$cat][] = "**$prefix**: " . $it;
            }
        }
    }
}

// --- PART 2: UPDATE ROOT CHANGELOG ---

if (!is_file($rootFile)) {
    echo "Root CHANGELOG.md not found.\n";
    exit(0);
}

$rootContent = normEol(file_get_contents($rootFile));

// Regex for Root sections
$reRootUnreleased = '/^##\s*\[?Unreleased\]?\s*$/m';
$reRootVersion = '/^##\s*\[' . preg_quote($version, '/') . '\]\s*(?:-\s*[\d-]{10})?\s*$/m';

$blkRootUnrel = extractRawSection($rootContent, $reRootUnreleased);
$blkRootVer = extractRawSection($rootContent, $reRootVersion);

$existingRootItems = [];
$unreleasedRootItems = [];

if ($blkRootVer) {
    $existingRootItems = parseKeepAChangelog($blkRootVer['body']);
}
if ($blkRootUnrel) {
    $unreleasedRootItems = parseKeepAChangelog($blkRootUnrel['body']);
}

// MERGE: Existing Root Version + Root Unreleased + New Package Items
// array_merge_recursive peut créer des tableaux de tableaux, on aplatit et on déduplique
$allRootItems = [];
$categories = array_unique(array_merge(
    array_keys($existingRootItems),
    array_keys($unreleasedRootItems),
    array_keys($packageBuckets)
));

foreach ($categories as $cat) {
    $combined = array_merge(
        $existingRootItems[$cat] ?? [],
        $unreleasedRootItems[$cat] ?? [],
        $packageBuckets[$cat] ?? []
    );

    // Déduplication intelligente : on nettoie les espaces et on retire les doublons exacts
    $allRootItems[$cat] = array_values(array_unique(array_map('trim', $combined)));
}

if (empty(array_filter($allRootItems))) {
    echo "No content to update in Root CHANGELOG.\n";
    exit(0);
}

$newRootBody = rebuildBody($allRootItems);
$newRootSection = "## [$version] - $date\n\n" . $newRootBody . "\n\n";
$emptyUnreleased = "## [Unreleased]\n\n";

// --- RECONSTRUCTION DU FICHIER ---
// Stratégie de reconstruction sécurisée pour éviter les décalages d'index
if ($blkRootVer && $blkRootUnrel) {
    // Cas le plus courant : Unreleased est en haut, la Version cible est juste en dessous
    $parts = [];
    $parts[] = substr($rootContent, 0, $blkRootUnrel['start']);
    $parts[] = $emptyUnreleased;

    // On garde ce qu'il y a entre Unreleased et la Version cible (souvent juste des sauts de ligne)
    $gap = substr($rootContent, $blkRootUnrel['start'] + $blkRootUnrel['length'],
        $blkRootVer['start'] - ($blkRootUnrel['start'] + $blkRootUnrel['length']));
    $parts[] = $gap;

    $parts[] = $newRootSection;
    $parts[] = substr($rootContent, $blkRootVer['start'] + $blkRootVer['length']);

    $newRootContent = implode('', $parts);
    $action = "Merged Unreleased & Existing Version into [$version]";

} elseif ($blkRootUnrel) {
    // Premier tag de cette version : On transforme Unreleased en Version et on recrée un Unreleased vide
    $replacement = $emptyUnreleased . $newRootSection;
    $newRootContent = substr_replace($rootContent, $replacement, $blkRootUnrel['start'], $blkRootUnrel['length']);
    $action = "Created [$version] from Unreleased + Packages";

} elseif ($blkRootVer) {
    // On met juste à jour le bloc de la version existante
    $newRootContent = substr_replace($rootContent, $newRootSection, $blkRootVer['start'], $blkRootVer['length']);
    $action = "Updated existing [$version] block";
}

if ($dryRun) {
    echo "\n===== ROOT PREVIEW ($action) =====\n" . $newRootSection . "==================================\n";
} else {
    file_put_contents($rootFile, $newRootContent);
    echo "Root CHANGELOG processed: $action.\n";
}

echo "Done.\n";
