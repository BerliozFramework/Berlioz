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

namespace Berlioz\Package\Twig\Tests\Extension;

use Berlioz\Core\Core;
use Berlioz\Core\Tests\RestoresErrorHandler;
use Berlioz\Package\Twig\Extension\DefaultExtension;
use Berlioz\Package\Twig\TestProject\FakeDefaultDirectories;
use PHPUnit\Framework\TestCase;

class DefaultExtensionTest extends TestCase
{
    use RestoresErrorHandler;

    public function testFilterDateFormat()
    {
        $core = new Core(new FakeDefaultDirectories(), false);
        $extension = new DefaultExtension($core);

        $this->assertEquals('14/09/2021', $extension->filterDateFormat('2021-09-14 23:00:00'));
        $this->assertEquals(
            '14/09/2021 23:00',
            $extension->filterDateFormat('2021-09-14 23:00:00', 'dd/MM/yyyy HH:mm')
        );
    }

    public function testTestInstanceOf()
    {
        $core = new Core(new FakeDefaultDirectories(), false);
        $extension = new DefaultExtension($core);

        $this->assertTrue($extension->testInstanceOf($core, Core::class));
        $this->assertFalse($extension->testInstanceOf($core, FakeDefaultDirectories::class));
    }
}
