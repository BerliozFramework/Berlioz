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

use Berlioz\Core\Debug\Snapshot\PhpError;
use PHPUnit\Framework\TestCase;

class PhpErrorTest extends TestCase
{
    public function test()
    {
        $phpError = new PhpError(666, 'MESSAGE');

        $this->assertEquals(666, $phpError->getErrno());
        $this->assertEquals('MESSAGE', $phpError->getMessage());
        $this->assertNull($phpError->getFile());
        $this->assertNull($phpError->getLine());

        $phpError = new PhpError(666, 'MESSAGE', 'FILE', 123);

        $this->assertEquals(666, $phpError->getErrno());
        $this->assertEquals('MESSAGE', $phpError->getMessage());
        $this->assertEquals('FILE', $phpError->getFile());
        $this->assertEquals(123, $phpError->getLine());
    }
}
