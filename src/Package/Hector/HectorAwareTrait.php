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

declare(strict_types=1);

namespace Berlioz\Package\Hector;

use Hector\Orm\Orm;

/**
 * Describes a hector-aware instance.
 */
trait HectorAwareTrait
{
    protected Orm|null $orm = null;

    /**
     * Get hector orm.
     *
     * @return Orm|null
     */
    public function getOrm(): ?Orm
    {
        return $this->orm;
    }

    /**
     * Set hector orm.
     *
     * @param Orm $orm
     *
     * @return static
     */
    public function setOrm(Orm $orm): static
    {
        $this->orm = $orm;

        return $this;
    }
}