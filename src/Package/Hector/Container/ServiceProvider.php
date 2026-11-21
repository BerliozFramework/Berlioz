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
use Hector\Migration\MigrationRunner;
use Hector\Migration\Provider\DirectoryProvider;
use Hector\Migration\Provider\MigrationProviderInterface;
use Hector\Migration\Provider\Psr4Provider;
use Hector\Migration\Tracker\DbTracker;
use Hector\Migration\Tracker\FileTracker;
use Hector\Migration\Tracker\MigrationTrackerInterface;
use Hector\Orm\Orm;
use Hector\Orm\OrmFactory;
use Hector\Schema\Plan\Compiler\AutoCompiler;
use Hector\Schema\Schema;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Class ServiceProvider.
 */
class ServiceProvider extends AbstractServiceProvider
{
    protected array $provides = [
        Connection::class,
        Orm::class,
        MigrationRunner::class,
        'dbConnection',
        'orm',
        'migrationRunner',
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
                    $config = $core->getConfig();
                    $options = $config->get('hector');
                    $options['log'] ??= $core->getDebug()->isEnabled();
                    $options['options'] = $this->resolvePdoOptions((array)($options['options'] ?? []));

                    $connection = OrmFactory::connection($options);

                    if (true === $core->getDebug()->isEnabled()) {
                        $section = new HectorSection($connection->getLogger());
                        $section
                            ->setThresholds(
                                (float)$config->get('hector.debug.slow_query', 50),
                                (float)$config->get('hector.debug.very_slow_query', 100),
                            )
                            ->setDuplicateThreshold((int)$config->get('hector.debug.duplicate_threshold', 2));

                        $core->getDebug()->addSection($section);
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

        $migrationService = $container->add(MigrationRunner::class, 'migrationRunner');
        $migrationService
            ->setFactory(
                function (Core $core, Connection $connection) {
                    $config = (array)$core->getConfig()->get('hector.migration', []);

                    $logger = null;
                    if ($core->getContainer()->has(LoggerInterface::class)) {
                        $logger = $core->getContainer()->get(LoggerInterface::class);
                    }

                    return new MigrationRunner(
                        provider: $this->getMigrationProvider($config, $core),
                        tracker: $this->getMigrationTracker($config, $core, $connection),
                        compiler: new AutoCompiler($connection),
                        connection: $connection,
                        schema: $this->getMigrationSchema($config, $core),
                        logger: $logger,
                        eventDispatcher: $core->getEventDispatcher(),
                    );
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
     * Resolve PDO options.
     *
     * Accepts a map (`attribute => value`) or a list of `{attribute, value}` entries.
     * Attributes may be integers or names of constants resolving to an integer.
     * When an attribute is defined several times, the last one wins.
     *
     * @param array $options
     *
     * @return array<int, mixed>
     * @throws HectorException
     */
    protected function resolvePdoOptions(array $options): array
    {
        $resolved = [];

        if ($this->isPdoOptionsList($options)) {
            foreach ($options as $entry) {
                $attribute = $this->resolvePdoAttribute($entry['attribute']);
                unset($resolved[$attribute]);
                $resolved[$attribute] = $entry['value'];
            }

            return $resolved;
        }

        foreach ($options as $attribute => $value) {
            $attribute = $this->resolvePdoAttribute($attribute);
            unset($resolved[$attribute]);
            $resolved[$attribute] = $value;
        }

        return $resolved;
    }

    /**
     * Is PDO options in list format?
     *
     * @param array $options
     *
     * @return bool
     * @throws HectorException
     */
    private function isPdoOptionsList(array $options): bool
    {
        if ([] === $options || !array_is_list($options)) {
            return false;
        }

        $entries = array_filter(
            $options,
            fn(mixed $entry): bool => is_array($entry) && array_key_exists('attribute', $entry),
        );

        if ([] === $entries) {
            return false;
        }

        foreach ($options as $index => $entry) {
            if (!is_array($entry) || !array_key_exists('attribute', $entry) || !array_key_exists('value', $entry)) {
                throw HectorException::pdoOptionsConfig(
                    sprintf('entry #%d must have "attribute" and "value" keys', $index)
                );
            }
        }

        return true;
    }

    /**
     * Resolve PDO attribute.
     *
     * @param mixed $attribute
     *
     * @return int
     * @throws HectorException
     */
    private function resolvePdoAttribute(mixed $attribute): int
    {
        if (is_int($attribute)) {
            return $attribute;
        }

        if (!is_string($attribute) || !defined($attribute)) {
            throw HectorException::pdoOptionsConfig(
                sprintf('unknown attribute "%s"', is_scalar($attribute) ? $attribute : get_debug_type($attribute))
            );
        }

        $value = constant($attribute);

        if (!is_int($value)) {
            throw HectorException::pdoOptionsConfig(sprintf('constant "%s" is not an integer', $attribute));
        }

        return $value;
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

    /**
     * Get migration provider.
     *
     * @param array $config
     * @param Core $core
     *
     * @return MigrationProviderInterface
     * @throws HectorException
     */
    protected function getMigrationProvider(array $config, Core $core): MigrationProviderInterface
    {
        try {
            $providerConfig = (array)($config['provider'] ?? []);
            $type = $providerConfig['type'] ?? 'directory';
            $container = $core->getContainer();

            return match ($type) {
                'directory' => new DirectoryProvider(
                    directory: $providerConfig['directory'] ?? '',
                    pattern: $providerConfig['pattern'] ?? '*.php',
                    depth: (int)($providerConfig['depth'] ?? 0),
                    container: $container,
                ),
                'psr4' => new Psr4Provider(
                    namespace: $providerConfig['namespace'] ?? '',
                    directory: $providerConfig['directory'] ?? '',
                    pattern: $providerConfig['pattern'] ?? '*.php',
                    depth: (int)($providerConfig['depth'] ?? -1),
                    container: $container,
                ),
                default => $container->get($type),
            };
        } catch (Throwable $exception) {
            throw HectorException::migrationConfig($exception);
        }
    }

    /**
     * Get migration tracker.
     *
     * @param array $config
     * @param Core $core
     * @param Connection $connection
     *
     * @return MigrationTrackerInterface
     * @throws HectorException
     */
    protected function getMigrationTracker(array $config, Core $core, Connection $connection): MigrationTrackerInterface
    {
        try {
            $trackerConfig = (array)($config['tracker'] ?? []);
            $type = $trackerConfig['type'] ?? 'db';

            return match ($type) {
                'db' => new DbTracker(
                    connection: $connection,
                    tableName: $trackerConfig['table'] ?? 'hector_migrations',
                ),
                'file' => new FileTracker(
                    filePath: $trackerConfig['file']
                        ?? $core->getDirectories()->getVarDir() . '/hector.migrations.json',
                ),
                default => $core->getContainer()->get($type),
            };
        } catch (Throwable $exception) {
            throw HectorException::migrationConfig($exception);
        }
    }

    /**
     * Get migration schema for introspection.
     *
     * @param array $config
     * @param Core $core
     *
     * @return Schema|null
     * @throws HectorException
     */
    protected function getMigrationSchema(array $config, Core $core): ?Schema
    {
        $schemaName = $config['schema'] ?? null;

        if (null === $schemaName) {
            return null;
        }

        try {
            return $core->getContainer()->get(Orm::class)->getSchemaContainer()->getSchema($schemaName);
        } catch (Throwable $exception) {
            throw HectorException::migrationConfig($exception);
        }
    }
}