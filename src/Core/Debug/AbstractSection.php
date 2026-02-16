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

namespace Berlioz\Core\Debug;

use Berlioz\Core\Debug\Snapshot\Section;

/**
 * Class AbstractSection.
 */
abstract class AbstractSection implements Section
{
    /**
     * @inheritDoc
     */
    public function getSectionId(): string
    {
        $name = mb_strtolower($this->getSectionName());
        $name = preg_replace('#[^\w\-\s]#i', '', $name);
        $name = preg_replace(['#\s+#', '#-{2,}#', '#_{2,}#i'], ['-', '-', '_'], $name);

        return $name;
    }
}