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

namespace Berlioz\Core\Tests\Debug;

use Berlioz\Core\Core;
use Berlioz\Core\Debug\SnapshotCleaner;
use Berlioz\Core\Filesystem\FilesystemInterface;
use Berlioz\Core\Tests\Directories\FakeDefaultDirectories;
use Berlioz\Core\Tests\RestoresErrorHandler;
use PHPUnit\Framework\TestCase;

class SnapshotCleanerTest extends TestCase
{
    use RestoresErrorHandler;

    private Core $core;
    private FilesystemInterface $filesystem;
    private string $debugDir;

    protected function setUp(): void
    {
        $directories = new FakeDefaultDirectories();
        $this->core = new Core($directories);
        $this->filesystem = $this->core->getFilesystem();
        $this->debugDir = $directories->getDebugDir();

        // Clean debug directory before each test
        foreach ($this->filesystem->listContents('debug://') as $item) {
            if ($item->isFile()) {
                $this->filesystem->delete($item->path());
            }
        }
    }

    /**
     * Create a fake snapshot file with the given age (in days).
     */
    private function createSnapshot(string $uniqid, int $ageInDays = 0): void
    {
        $this->filesystem->write(sprintf('debug://%s.debug', $uniqid), 'fake');

        if ($ageInDays > 0) {
            $realPath = $this->debugDir . DIRECTORY_SEPARATOR . $uniqid . '.debug';
            touch($realPath, time() - ($ageInDays * 86400));
        }
    }

    private function countSnapshots(): int
    {
        return count(
            $this->filesystem
                ->listContents('debug://')
                ->filter(fn($attr) => $attr->isFile())
                ->toArray()
        );
    }

    public function testListSnapshots(): void
    {
        $this->createSnapshot('aaa');
        $this->createSnapshot('bbb');

        $cleaner = new SnapshotCleaner($this->filesystem);
        $snapshots = $cleaner->listSnapshots();

        $this->assertCount(2, $snapshots);
    }

    public function testCleanByAge(): void
    {
        $this->createSnapshot('recent', 1);
        $this->createSnapshot('old', 10);

        $cleaner = new SnapshotCleaner($this->filesystem);
        $deleted = $cleaner->clean(7, null);

        $this->assertSame(1, $deleted);
        $this->assertTrue($this->filesystem->fileExists('debug://recent.debug'));
        $this->assertFalse($this->filesystem->fileExists('debug://old.debug'));
    }

    public function testCleanByMaxFiles(): void
    {
        $this->createSnapshot('s1', 5);
        $this->createSnapshot('s2', 4);
        $this->createSnapshot('s3', 3);
        $this->createSnapshot('s4', 2);
        $this->createSnapshot('s5', 1);

        $cleaner = new SnapshotCleaner($this->filesystem);
        $deleted = $cleaner->clean(null, 2);

        // Keep the 2 most recent (s5, s4), delete the 3 oldest
        $this->assertSame(3, $deleted);
        $this->assertSame(2, $this->countSnapshots());
        $this->assertTrue($this->filesystem->fileExists('debug://s5.debug'));
        $this->assertTrue($this->filesystem->fileExists('debug://s4.debug'));
    }

    public function testCleanCombined(): void
    {
        $this->createSnapshot('veryOld', 30);
        $this->createSnapshot('old', 10);
        $this->createSnapshot('r1', 3);
        $this->createSnapshot('r2', 2);
        $this->createSnapshot('r3', 1);

        $cleaner = new SnapshotCleaner($this->filesystem);
        // Remove >7 days (veryOld, old), then keep only 2 most recent of remaining (r3, r2)
        $deleted = $cleaner->clean(7, 2);

        $this->assertSame(3, $deleted);
        $this->assertSame(2, $this->countSnapshots());
        $this->assertTrue($this->filesystem->fileExists('debug://r3.debug'));
        $this->assertTrue($this->filesystem->fileExists('debug://r2.debug'));
    }

    public function testCleanNoLimit(): void
    {
        $this->createSnapshot('aaa');
        $this->createSnapshot('bbb');

        $cleaner = new SnapshotCleaner($this->filesystem);
        $deleted = $cleaner->clean(null, null);

        $this->assertSame(0, $deleted);
        $this->assertSame(2, $this->countSnapshots());
    }

    public function testClear(): void
    {
        $this->createSnapshot('aaa');
        $this->createSnapshot('bbb');
        $this->createSnapshot('ccc');

        $cleaner = new SnapshotCleaner($this->filesystem);
        $deleted = $cleaner->clear();

        $this->assertSame(3, $deleted);
        $this->assertSame(0, $this->countSnapshots());
    }

    public function testDelete(): void
    {
        $this->createSnapshot('aaa');
        $this->createSnapshot('bbb');

        $cleaner = new SnapshotCleaner($this->filesystem);
        $cleaner->delete('aaa');

        $this->assertFalse($this->filesystem->fileExists('debug://aaa.debug'));
        $this->assertTrue($this->filesystem->fileExists('debug://bbb.debug'));
    }

    public function testDeleteWithExtension(): void
    {
        $this->createSnapshot('aaa');

        $cleaner = new SnapshotCleaner($this->filesystem);
        $cleaner->delete('aaa.debug');

        $this->assertFalse($this->filesystem->fileExists('debug://aaa.debug'));
    }
}
