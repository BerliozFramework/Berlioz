<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2026 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\Package\Hector\Tests\Debug;

use Berlioz\Package\Hector\Debug\HectorSection;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class HectorSectionTest extends TestCase
{
    public function testUnserializeInitializesLoggers(): void
    {
        $section = new HectorSection();
        $unserializedSection = unserialize(serialize($section));

        $this->assertInstanceOf(HectorSection::class, $unserializedSection);

        $loggersProperty = new ReflectionProperty(HectorSection::class, 'loggers');

        $this->assertTrue($loggersProperty->isInitialized($unserializedSection));
        $this->assertSame([], $loggersProperty->getValue($unserializedSection));
    }
}
