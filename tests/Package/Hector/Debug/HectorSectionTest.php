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
use Hector\Connection\Bind\BindParam;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
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

    public function testInterpolateStatementReplacesNamedParameters(): void
    {
        $section = new HectorSection();
        $method = new ReflectionMethod(HectorSection::class, 'interpolateStatement');

        $statement = 'SELECT * FROM user WHERE id = :_h_0 AND name = :_h_1 AND active = :_h_2 AND deleted = :_h_3';
        $parameters = [
            new BindParam('_h_0', 42),
            new BindParam('_h_1', "O'Brien"),
            new BindParam('_h_2', true),
            new BindParam('_h_3', null),
        ];

        $result = $method->invoke($section, $statement, $parameters);

        $this->assertSame(
            "SELECT * FROM user WHERE id = 42 AND name = 'O''Brien' AND active = 1 AND deleted = NULL",
            $result,
        );
    }

    public function testInterpolateStatementReplacesPositionalParameters(): void
    {
        $section = new HectorSection();
        $method = new ReflectionMethod(HectorSection::class, 'interpolateStatement');

        $statement = 'SELECT * FROM user WHERE id = ? AND name = ?';
        $parameters = [
            new BindParam(1, 42),
            new BindParam(2, 'John'),
        ];

        $result = $method->invoke($section, $statement, $parameters);

        $this->assertSame("SELECT * FROM user WHERE id = 42 AND name = 'John'", $result);
    }
}
