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

class FakeSection extends AbstractSection
{
    private string $foo = 'bar';

    public function getSectionName(): string
    {
        return 'Fake section';
    }

    public function __serialize(): array
    {
        return ['foo' => $this->foo];
    }

    public function __unserialize(array $data): void
    {
        $this->foo = $data['foo'];
    }

    public function snap(DebugHandler $debug): void
    {
    }
}