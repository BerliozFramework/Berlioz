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

namespace Berlioz\Package\Hector\Container;

use Berlioz\Core\Core;
use Berlioz\Package\Hector\Debug\HectorSection;
use Berlioz\Package\Hector\DefaultCacheDriver;
use Berlioz\Package\Hector\Event\EventSubscriber;
use Berlioz\Package\Hector\Exception\HectorException;
use Berlioz\Package\Hector\HectorAwareInterface;
use Berlioz\ServiceContainer\Container;
use Berlioz\ServiceContainer\Inflector\Inflector;
use Berlioz\ServiceContainer\Provider\AbstractServiceProvider;
use Hector\Connection\Connection;
use Hector\Orm\Orm;
use Hector\Orm\OrmFactory;
use Throwable;

/**
 * Class ServiceProvider.
 */
class ServiceProvider extends AbstractServiceProvider
{
    protected array $provides = [
        Connection::class,
        Orm::class,
        'dbConnection',
        'orm',
    ];

    /**
     * @inheritDoc
     */
    public function register(Container $container): void
    {
        $connectionService = $container->add(Connection::class, 'dbConnection');
        $connectionService
            ->setFactory(
                function (Core $core) {
                    $options = $core->getConfig()->get('hector');
                    $options['log'] = $options['log'] ?? $core->getDebug()->isEnabled();

                    $connection = OrmFactory::connection($options);

                    if (true === $core->getDebug()->isEnabled()) {
                        $core->getDebug()->addSection(new HectorSection($connection->getLogger()));
                    }

                    return $connection;
                }
            );

        $ormService = $container->add(Orm::class, 'orm');
        $ormService
            ->setFactory(
                function (Core $core, Connection $connection) {
                    $orm = OrmFactory::orm(
                        $core->getConfig()->get('hector'),
                        $connection,
                        $core->getEventDispatcher(),
                        new DefaultCacheDriver($core->getDirectories()),
                    );

                    $this->setOrmTypes($orm, $core);

                    // Dynamic events
                    if ($core->getConfig()->get('hector.dynamic_events', true)) {
                        $core->getEventDispatcher()->addSubscriber(new EventSubscriber($core->getContainer()));
                    }

                    return $orm;
                }
            )
            ->addArgument('connection', '@dbConnection');
    }

    /**
     * @inheritDoc
     */
    public function boot(Container $container): void
    {
        $container->addInflector(new Inflector(HectorAwareInterface::class, 'setOrm', ['orm' => '@orm']));
    }

    /**
     * Set ORM types.
     *
     * @param Orm $orm
     * @param Core $core
     *
     * @throws HectorException
     */
    protected function setOrmTypes(Orm $orm, Core $core): void
    {
        try {
            foreach ((array)$core->getConfig()->get('hector.types', []) as $type => $obj) {
                $orm->getTypes()->add($type, $core->getContainer()->get($obj));
            }
        } catch (Throwable $exception) {
            throw HectorException::typesConfig($exception);
        }
    }
}