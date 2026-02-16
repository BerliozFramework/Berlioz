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

namespace Berlioz\Package\Twig\Container;

use Berlioz\Core\Core;
use Berlioz\Package\Twig\Debug\TwigSection;
use Berlioz\Package\Twig\Twig;
use Berlioz\Package\Twig\TwigAwareInterface;
use Berlioz\ServiceContainer\Container;
use Berlioz\ServiceContainer\Inflector\Inflector;
use Berlioz\ServiceContainer\Provider\AbstractServiceProvider;
use Berlioz\ServiceContainer\Service\Service;

class ServiceProvider extends AbstractServiceProvider
{
    protected array $provides = [
        Twig::class,
        'twig',
    ];

    /**
     * @inheritDoc
     */
    public function register(Container $container): void
    {
        $container->addService(
            new Service(
                class: Twig::class,
                alias: 'twig',
                factory: function (Core $core): Twig {
                    $twig = new Twig($core, ...$core->getConfig()->get('twig'));

                    if ($core->getDebug()->isEnabled() && null !== $twig->getProfile()) {
                        $core->getDebug()->addSection(new TwigSection($twig->getProfile()));
                    }

                    return $twig;
                }
            )
        );
    }

    /**
     * @inheritDoc
     */
    public function boot(Container $container): void
    {
        $container->addInflector(new Inflector(TwigAwareInterface::class, 'setTwig', ['twig' => '@twig']));
    }
}