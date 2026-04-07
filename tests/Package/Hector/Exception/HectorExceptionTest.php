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

namespace Berlioz\Package\Hector\Tests\Exception;

use Berlioz\Package\Hector\Exception\HectorException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class HectorExceptionTest extends TestCase
{
    public function testTypesConfig(): void
    {
        $previous = new RuntimeException('previous');

        $exception = HectorException::typesConfig($previous);

        $this->assertInstanceOf(HectorException::class, $exception);
        $this->assertSame('Types config error', $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
