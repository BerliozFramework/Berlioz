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

namespace Berlioz\Package\Twig\TestProject;

use Berlioz\ServiceContainer\Container;
use Berlioz\ServiceContainer\Provider\AbstractServiceProvider;

/**
 * Class TestServiceProvider.
 */
class TestServiceProvider extends AbstractServiceProvider
{
    protected array $provides = [Service::class, 'service'];

    /**
     * @inheritDoc
     */
    public function register(Container $container): void
    {
        $container->add(Service::class, 'service');
    }
}
