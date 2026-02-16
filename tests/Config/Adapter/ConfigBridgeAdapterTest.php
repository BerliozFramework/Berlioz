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

namespace Berlioz\Config\Tests\Adapter;

use Berlioz\Config\Adapter\ArrayAdapter;
use Berlioz\Config\Adapter\ConfigBridgeAdapter;
use PHPUnit\Framework\TestCase;

class ConfigBridgeAdapterTest extends TestCase
{
    public function testGetArrayCopy()
    {
        $sourceAdapter = new ArrayAdapter(
            [
                "qux" => "value1",
                "section" => [
                    "foo" => "value",
                    "qux" => "value2"
                ],
                "section2" => [
                    "bar" => "value3"
                ],
            ]
        );
        $adapter = new ConfigBridgeAdapter($sourceAdapter);

        $this->assertEquals($sourceAdapter->getArrayCopy(), $adapter->getArrayCopy());
    }
}
