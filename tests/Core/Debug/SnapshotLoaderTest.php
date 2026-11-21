<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2021 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\Core\Tests\Debug;

use Berlioz\Config\Adapter\ArrayAdapter;
use Berlioz\Core\Core;
use Berlioz\Core\Debug\DebugHandler;
use Berlioz\Core\Debug\Snapshot;
use Berlioz\Core\Debug\Snapshot\Event;
use Berlioz\Core\Debug\SnapshotLoader;
use Berlioz\Core\Debug\SnapshotStatus;
use Berlioz\Core\Exception\BerliozException;
use Berlioz\Core\Tests\Directories\FakeDefaultDirectories;
use Berlioz\Core\Tests\RestoresErrorHandler;
use DateTime;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;

class SnapshotLoaderTest extends TestCase
{
    use RestoresErrorHandler;

    #[Depends('testSave')]
    public function testLoad(Snapshot $snapshot)
    {
        $handler = new DebugHandler();
        $handler->handle($core = new Core(new FakeDefaultDirectories()));
        $handler->setEnabled(true);

        $loader = new SnapshotLoader($core->getFilesystem());
        $snapshot2 = $loader->load($snapshot->getUniqid());

        $this->assertEquals($snapshot, $snapshot2);
    }

    public function testLoad_invalid()
    {
        $handler = new DebugHandler();
        $handler->handle($core = new Core(new FakeDefaultDirectories()));
        $handler->setEnabled(true);

        $loader = new SnapshotLoader($core->getFilesystem());
        $this->expectException(BerliozException::class);
        $loader->load('FAKE');
    }

    public function testSave()
    {
        $this->expectNotToPerformAssertions();

        $handler = new DebugHandler();
        $handler->handle($core = new Core(new FakeDefaultDirectories()));
        $handler->setEnabled(true);
        $handler->newActivity('foo');
        $handler->newEventActivity(new Event('anEvent', new DateTime()));

        $loader = new SnapshotLoader($core->getFilesystem());
        $loader->save($snapshot = $handler->getSnapshot());

        return $snapshot;
    }

    public function testStatus()
    {
        $handler = new DebugHandler();
        $handler->handle($core = new Core(new FakeDefaultDirectories()));
        $handler->setEnabled(true);
        $uniqid = $handler->getUniqid();
        $loader = new SnapshotLoader($core->getFilesystem(), $core->getConfig());

        $this->assertSame(SnapshotStatus::MISSING, $loader->status($uniqid));
        $this->assertNull($loader->getPending($uniqid));

        $loader->markPending($uniqid, ['responseTime' => 0.123]);

        $this->assertSame(SnapshotStatus::PENDING, $loader->status($uniqid));
        $pending = $loader->getPending($uniqid);
        $this->assertSame($uniqid, $pending['uniqid']);
        $this->assertSame(0.123, $pending['responseTime']);
        $this->assertEqualsWithDelta($pending['time'] + $loader->getPendingTtl(), $pending['expire'], 0.001);

        $loader->save($handler->getSnapshot());

        $this->assertSame(SnapshotStatus::READY, $loader->status($uniqid));
        $this->assertNull($loader->getPending($uniqid));
        $this->assertFalse($core->getFilesystem()->fileExists(sprintf('debug://%s.pending', $uniqid)));
    }

    public function testStatus_abandoned()
    {
        $core = new Core(new FakeDefaultDirectories());
        $uniqid = uniqid();
        $core->getFilesystem()->write(
            sprintf('debug://%s.pending', $uniqid),
            json_encode(['uniqid' => $uniqid, 'time' => microtime(true) - 20, 'expire' => microtime(true) - 10])
        );
        $loader = new SnapshotLoader($core->getFilesystem());

        $this->assertSame(SnapshotStatus::ABANDONED, $loader->status($uniqid));
    }

    public function testGetPendingTtl()
    {
        $core = new Core(new FakeDefaultDirectories());
        $maxExecutionTime = ini_get('max_execution_time');

        try {
            $loader = new SnapshotLoader($core->getFilesystem());

            ini_set('max_execution_time', '0');
            $this->assertSame(SnapshotLoader::DEFAULT_PENDING_TTL, $loader->getPendingTtl());

            ini_set('max_execution_time', '45');
            $this->assertSame(45, $loader->getPendingTtl());

            $core->getConfig()->addConfig(new ArrayAdapter(['berlioz' => ['debug' => ['pending_ttl' => 12]]]));
            $loader = new SnapshotLoader($core->getFilesystem(), $core->getConfig());
            $this->assertSame(12, $loader->getPendingTtl());
        } finally {
            ini_set('max_execution_time', (string)$maxExecutionTime);
        }
    }
}
