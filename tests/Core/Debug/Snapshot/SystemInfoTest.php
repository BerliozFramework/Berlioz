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

namespace Berlioz\Core\Tests\Debug\Snapshot;

use Berlioz\Core\Debug\Snapshot\SystemInfo;
use PHPUnit\Framework\TestCase;

class SystemInfoTest extends TestCase
{
    public function test()
    {
        $systemInfo = new SystemInfo();

        $this->assertEquals(php_uname(), $systemInfo->getUname());
        $this->assertEquals(get_current_user(), $systemInfo->getCurrentUser());
        $this->assertEquals(getmyuid() ?: null, $systemInfo->getUid());
        $this->assertEquals(getmygid() ?: null, $systemInfo->getGid());
        $this->assertEquals(getmypid() ?: null, $systemInfo->getPid());
        $this->assertEquals(getmyinode() ?: null, $systemInfo->getInode());
        $this->assertEquals(sys_get_temp_dir(), $systemInfo->getTmpDir());

        $this->assertIsInt($systemInfo->getOpenResources());
        $this->assertGreaterThan(0, $systemInfo->getOpenResources());
    }
}
