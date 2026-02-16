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

use Berlioz\Core\Debug\AbstractSection;
use Berlioz\Core\Debug\DebugHandler;
use PHPUnit\Framework\TestCase;

class AbstractSectionTest extends TestCase
{
    public function testGetSectionId()
    {
        $section = new class extends AbstractSection {
            /**
             * @inheritDoc
             */
            public function getSectionName(): string
            {
                return 'My Section Nameé';
            }

            /**
             * @inheritDoc
             */
            public function snap(DebugHandler $debug): void
            {
            }
        };

        $this->assertEquals('my-section-name', $section->getSectionId());
    }
}
