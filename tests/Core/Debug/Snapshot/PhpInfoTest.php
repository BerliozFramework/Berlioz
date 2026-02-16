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

use Berlioz\Core\Debug\Snapshot\PhpInfo;
use PHPUnit\Framework\TestCase;

class PhpInfoTest extends TestCase
{
    public function test()
    {
        $phpinfo = new PhpInfo();

        $this->assertEquals(phpversion(), $phpinfo->getVersion());
        $this->assertEquals(php_sapi_name(), $phpinfo->getSapiName());
        $this->assertEquals(b_size_from_ini(ini_get('memory_limit')), $phpinfo->getMemoryLimit());
        $this->assertEquals(get_loaded_extensions(), $phpinfo->getExtensions());
    }
}
