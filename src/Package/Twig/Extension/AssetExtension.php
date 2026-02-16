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

namespace Berlioz\Package\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AssetExtension extends AbstractExtension
{
    /**
     * Returns a list of functions to add to the existing list.
     *
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('asset', [AssetRuntimeExtension::class, 'asset']),
            new TwigFunction('entrypoints', [AssetRuntimeExtension::class, 'entryPoints'], ['is_safe' => ['html']]),
            new TwigFunction('entrypoints_list', [AssetRuntimeExtension::class, 'entryPointsList']),
            new TwigFunction('preload', [AssetRuntimeExtension::class, 'preload']),
        ];
    }
}