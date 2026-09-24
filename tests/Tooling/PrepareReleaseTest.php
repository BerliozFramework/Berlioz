<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2026 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\Tests\Tooling;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class PrepareReleaseTest extends TestCase
{
    public function testMultilineEntriesSurviveAggregationAndRepeatedRelease(): void
    {
        $directory = sys_get_temp_dir() . '/berlioz-release-' . bin2hex(random_bytes(8));
        mkdir($directory . '/bin', 0777, true);
        mkdir($directory . '/package');

        try {
            copy(dirname(__DIR__, 2) . '/bin/prepare-release.php', $directory . '/bin/prepare-release.php');
            file_put_contents(
                $directory . '/config.subsplit-publish.json',
                json_encode(['sub-splits' => [['name' => 'form', 'directory' => 'package']]], JSON_THROW_ON_ERROR),
            );
            $entries = <<<'MARKDOWN'
- Custom mapping reads from
  and writes to an object.

  Additional details:
  - Nested property access
  * Custom transformations
* A second entry with `true`/
  `false` options.
- A single-line entry
MARKDOWN;
            $changelog = "# Change Log\n\n## [Unreleased]\n\n### Added\n\n$entries\n";
            file_put_contents($directory . '/package/CHANGELOG.md', $changelog);
            file_put_contents($directory . '/CHANGELOG.md', "# Change Log\n\n## [Unreleased]\n");

            $command = [PHP_BINARY, $directory . '/bin/prepare-release.php', '3.3.0', '2026-09-24'];
            $preview = new Process([...$command, '--dry-run'], $directory);
            $preview->mustRun();
            self::assertStringContainsString('and writes to an object.', $preview->getOutput());
            self::assertSame($changelog, file_get_contents($directory . '/package/CHANGELOG.md'));

            (new Process($command, $directory))->mustRun();
            $root = file_get_contents($directory . '/CHANGELOG.md');
            self::assertStringContainsString(
                "- [form] Custom mapping reads from\n  and writes to an object.\n\n"
                . "  Additional details:\n  - Nested property access\n  * Custom transformations",
                $root,
            );
            self::assertStringContainsString("- [form] A second entry with `true`/\n  `false` options.", $root);
            self::assertSame(3, substr_count($root, '- [form]'));
            self::assertStringContainsString('- [form] A single-line entry', $root);

            // A second run rebuilds the package version and merges the existing root entries.
            (new Process($command, $directory))->mustRun();
            self::assertSame($root, file_get_contents($directory . '/CHANGELOG.md'));
            self::assertStringContainsString(
                "Custom mapping reads from\n  and writes to an object.",
                file_get_contents($directory . '/package/CHANGELOG.md'),
            );
            self::assertStringContainsString(
                "  - Nested property access\n  * Custom transformations",
                file_get_contents($directory . '/package/CHANGELOG.md'),
            );
        } finally {
            foreach ([
                'bin/prepare-release.php',
                'config.subsplit-publish.json',
                'package/CHANGELOG.md',
                'CHANGELOG.md',
            ] as $file) {
                if (is_file($directory . '/' . $file)) {
                    unlink($directory . '/' . $file);
                }
            }
            rmdir($directory . '/package');
            rmdir($directory . '/bin');
            rmdir($directory);
        }
    }
}
