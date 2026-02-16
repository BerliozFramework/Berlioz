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

namespace Berlioz\Package\Twig\Debug;

use Berlioz\Core\Debug\AbstractSection;
use Berlioz\Core\Debug\DebugHandler;
use Countable;
use Twig\Profiler\Profile;

class TwigSection extends AbstractSection implements Countable
{
    public function __construct(private Profile $profile)
    {
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->profile->getProfiles());
    }

    /**
     * @inheritDoc
     */
    public function getSectionName(): string
    {
        return 'Twig';
    }

    /**
     * Get template name.
     */
    public function getTemplateName(): string
    {
        return '@Berlioz-TwigPackage/Twig/Debug/twig.html.twig';
    }

    /**
     * @inheritDoc
     */
    public function snap(DebugHandler $debug): void
    {
    }

    /**
     * Get profile.
     *
     * @return Profile
     */
    public function getProfile(): Profile
    {
        return $this->profile;
    }
}