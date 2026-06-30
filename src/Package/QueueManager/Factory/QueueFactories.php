<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2024 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\Package\QueueManager\Factory;

use Berlioz\Config\Config;
use Berlioz\Core\Exception\ConfigException;
use Berlioz\QueueManager\Queue\QueueInterface;

class QueueFactories
{
    private array $factories = [];

    public function __construct(
        private readonly Config $config,
    ) {
        foreach ($this->config->get('berlioz.queues.factories', []) as $factoryClass) {
            if (!is_a($factoryClass, QueueFactory::class, true)) {
                throw new ConfigException(sprintf('Invalid queue factory class "%s"', $factoryClass));
            }

            $this->factories[$factoryClass::getQueueClass()] = $factoryClass;
        }
    }

    /**
     * Create queue from config with factories.
     *
     * @param array $config
     *
     * @return QueueInterface[]
     * @throws ConfigException
     */
    public function createFromConfig(array $config): iterable
    {
            $config['type'] ?? throw new ConfigException(sprintf('No queue type given'));

        if (false === array_key_exists($config['type'], $this->factories)) {
            throw new ConfigException(sprintf('No factory found for queue of type "%s"', $config['type']));
        }

        yield from ($this->factories[$config['type']])::createFromConfig($config);
    }
}
