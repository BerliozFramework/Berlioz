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
use Berlioz\Core\Debug\Snapshot\PhpErrorSet;
use PHPUnit\Framework\TestCase;

class PhpErrorSetTest extends TestCase
{
    public function test()
    {
        $phpErrorSet = new PhpErrorSet(
            $error1 = new PhpError(123, 'MESSAGE'),
            $error2 = new PhpError(666, 'MESSAGE'),
        );

        $this->assertSame([$error1, $error2], iterator_to_array($phpErrorSet->getErrors(), false));
    }
}
