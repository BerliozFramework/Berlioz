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

use Berlioz\Core\Core;
use Berlioz\Core\Debug\DebugHandler;
use Berlioz\Core\Debug\Snapshot;
use Berlioz\Core\Debug\Snapshot\Event;
use Berlioz\Core\Debug\SnapshotLoader;
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
}
