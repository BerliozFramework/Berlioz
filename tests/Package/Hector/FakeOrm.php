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

use Hector\Orm\Orm;
use Hector\Orm\Storage\EntityStorage;
use Hector\Schema\SchemaContainer;

class FakeOrm extends Orm
{
    public static function reset(): void
    {
        static::$instance = null;
    }

    public function getStorage(): EntityStorage
    {
        return $this->storage;
    }

    public function getSchemaContainer(): SchemaContainer
    {
        return $this->schemaContainer;
    }
}