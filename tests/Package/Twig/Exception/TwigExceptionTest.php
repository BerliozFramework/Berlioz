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

namespace Berlioz\Package\Twig\Tests\Exception;

use Berlioz\Package\Twig\Exception\TwigException;
use Berlioz\Package\Twig\TwigAwareInterface;
use PHPUnit\Framework\TestCase;
use Twig\Extension\ExtensionInterface;

class TwigExceptionTest extends TestCase
{
    public function testInvalidExtension(): void
    {
        $exception = TwigException::invalidExtension('invalid');

        $this->assertInstanceOf(TwigException::class, $exception);
        $this->assertSame(
            sprintf(
                'Twig extension must implement "%s" interface, actual "%s"',
                ExtensionInterface::class,
                'string'
            ),
            $exception->getMessage()
        );
    }

    public function testNotLoaded(): void
    {
        $exception = TwigException::notLoaded();

        $this->assertInstanceOf(TwigException::class, $exception);
        $this->assertSame(
            sprintf('Twig is not loaded with method "%s::setTwig()"', TwigAwareInterface::class),
            $exception->getMessage()
        );
    }
}
