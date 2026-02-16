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

namespace Berlioz\Package\Hector\Tests;

use Hector\Connection\Connection;
use Hector\Schema\Generator\Sqlite;
use PHPUnit\Framework\TestCase;

abstract class AbstractTestCase extends TestCase
{
    protected static ?FakeOrm $orm = null;

    protected function setUp(): void
    {
        FakeOrm::reset();
        $this->getOrm();
    }

    protected function getOrm(): FakeOrm
    {
        if (null !== self::$orm) {
            return self::$orm;
        }

        $connection = new Connection('sqlite:memory:');
        $schemaGenerator = new Sqlite($connection);
        $schemaContainer = $schemaGenerator->generateSchemas();

        self::$orm = new FakeOrm($connection, $schemaContainer);

        return self::$orm;
    }
}