<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2024 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\Package\QueueManager\Tests;

use Berlioz\Config\Adapter\JsonAdapter;
use Berlioz\Core\Core;
use Berlioz\Core\Tests\RestoresErrorHandler;
use Berlioz\Package\QueueManager\BerliozPackage;
use Berlioz\Package\QueueManager\TestProject\TestEnvDirectories;
use Berlioz\QueueManager\Handler\JobHandlerManager;
use Berlioz\QueueManager\QueueManager;
use Berlioz\QueueManager\Worker;
use PHPUnit\Framework\TestCase;

class BerliozPackageTest extends TestCase
{
    use RestoresErrorHandler;

    public function testConfig()
    {
        $configFromPackage = BerliozPackage::config();
        $config = new JsonAdapter(__DIR__ . '/../../../src/Package/QueueManager/resources/config.default.json5', true);

        $this->assertEquals($config->getArrayCopy(), $configFromPackage->getArrayCopy());
    }

    public function testRegister()
    {
        $core = new Core(new TestEnvDirectories(), cache: false);
        $core->getContainer()->autoWiring(false);

        $this->assertFalse($core->getContainer()->has(QueueManager::class));
        $this->assertFalse($core->getContainer()->has(Worker::class));
        $this->assertFalse($core->getContainer()->has(JobHandlerManager::class));

        BerliozPackage::register($core->getContainer());

        $this->assertTrue($core->getContainer()->has(QueueManager::class));
        $this->assertTrue($core->getContainer()->has(Worker::class));
        $this->assertTrue($core->getContainer()->has(JobHandlerManager::class));
    }
}
