<?php
/**
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2020 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\Package\Twig\Tests;

use Berlioz\Package\Twig\BerliozPackage;
use PHPUnit\Framework\TestCase;

class BerliozPackageTest extends TestCase
{
    public function testConfig()
    {
        $configFromPackage = (new BerliozPackage)->config()->getArrayCopy();

        $this->assertArrayHasKey('twig', $configFromPackage);
        $this->assertArrayHasKey('extensions', $configFromPackage['twig']);
    }
}
