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

namespace Berlioz\EventManager\Tests;

use Berlioz\EventManager\EventDispatcher;
use Berlioz\EventManager\Provider\ListenerProviderInterface;
use Berlioz\EventManager\Tests\Provider\FakeSubscriberProvider;

class FakeEventDispatcher extends EventDispatcher
{
    public function __construct(
        array $providers = [],
        array $dispatchers = [],
        ListenerProviderInterface $defaultProvider = null
    ) {
        parent::__construct($providers, $dispatchers, $defaultProvider);

        $this->subscriberProvider = new FakeSubscriberProvider($this->defaultProvider);
    }

    public function getDispatchers(): array
    {
        return $this->dispatchers;
    }

    public function getProviders(): array
    {
        return $this->providers;
    }

    public function getSubscriberProvider(): FakeSubscriberProvider
    {
        return $this->subscriberProvider;
    }
}
