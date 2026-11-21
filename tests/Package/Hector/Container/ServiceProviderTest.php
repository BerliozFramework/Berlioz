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

namespace Berlioz\Package\Hector\Tests\Container;

use Berlioz\Config\Adapter\ArrayAdapter;
use Berlioz\Core\Core;
use Berlioz\Core\Tests\RestoresErrorHandler;
use Berlioz\Package\Hector\BerliozPackage;
use Berlioz\Package\Hector\Container\ServiceProvider;
use Berlioz\Package\Hector\Exception\HectorException;
use Berlioz\Package\Hector\TestProject\TestEnvDirectories;
use Berlioz\Package\Hector\Tests\FakeOrm;
use Hector\Connection\Connection;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ServiceProviderTest extends TestCase
{
    use RestoresErrorHandler;

    public const NOT_INT_CONSTANT = 'foo';

    protected function setUp(): void
    {
        FakeOrm::reset();
        parent::setUp();
    }

    private function resolvePdoOptions(array $options): array
    {
        $method = new ReflectionMethod(ServiceProvider::class, 'resolvePdoOptions');

        return $method->invoke(new ServiceProvider(), $options);
    }

    public static function provideResolvePdoOptions(): array
    {
        return [
            'empty' => [[], []],
            'map with integer keys' => [
                ['2' => 5, 20 => false],
                [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_EMULATE_PREPARES => false],
            ],
            'map with constant names' => [
                ['PDO::ATTR_TIMEOUT' => 5, 'PDO::ATTR_EMULATE_PREPARES' => false],
                [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_EMULATE_PREPARES => false],
            ],
            'map mixed' => [
                ['PDO::ATTR_TIMEOUT' => 5, 20 => false],
                [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_EMULATE_PREPARES => false],
            ],
            'map looking like a list' => [
                [0 => false],
                [PDO::ATTR_AUTOCOMMIT => false],
            ],
            'map last wins' => [
                [2 => 5, 'PDO::ATTR_TIMEOUT' => 10],
                [PDO::ATTR_TIMEOUT => 10],
            ],
            'list' => [
                [
                    ['attribute' => 'PDO::ATTR_TIMEOUT', 'value' => 5],
                    ['attribute' => PDO::ATTR_EMULATE_PREPARES, 'value' => false],
                ],
                [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_EMULATE_PREPARES => false],
            ],
            'list last wins' => [
                [
                    ['attribute' => 'PDO::ATTR_TIMEOUT', 'value' => 5],
                    ['attribute' => PDO::ATTR_EMULATE_PREPARES, 'value' => false],
                    ['attribute' => PDO::ATTR_TIMEOUT, 'value' => 10],
                ],
                [PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_TIMEOUT => 10],
            ],
        ];
    }

    #[DataProvider('provideResolvePdoOptions')]
    public function testResolvePdoOptions(array $options, array $expected): void
    {
        $this->assertSame($expected, $this->resolvePdoOptions($options));
    }

    public static function provideResolvePdoOptions_invalid(): array
    {
        return [
            'unknown constant' => [
                ['PDO::ATTR_UNKNOWN' => 5],
                'PDO options config error: unknown attribute "PDO::ATTR_UNKNOWN"',
            ],
            'not integer constant' => [
                [self::class . '::NOT_INT_CONSTANT' => 5],
                sprintf('PDO options config error: constant "%s::NOT_INT_CONSTANT" is not an integer', self::class),
            ],
            'list entry without value' => [
                [
                    ['attribute' => 'PDO::ATTR_TIMEOUT', 'value' => 5],
                    ['attribute' => 'PDO::ATTR_EMULATE_PREPARES'],
                ],
                'PDO options config error: entry #1 must have "attribute" and "value" keys',
            ],
            'list entry not array' => [
                [['attribute' => 'PDO::ATTR_TIMEOUT', 'value' => 5], 'foo'],
                'PDO options config error: entry #1 must have "attribute" and "value" keys',
            ],
            'list unknown attribute' => [
                [['attribute' => 'foo', 'value' => 5]],
                'PDO options config error: unknown attribute "foo"',
            ],
            'list invalid attribute type' => [
                [['attribute' => null, 'value' => 5]],
                'PDO options config error: unknown attribute "null"',
            ],
        ];
    }

    #[DataProvider('provideResolvePdoOptions_invalid')]
    public function testResolvePdoOptions_invalid(array $options, string $message): void
    {
        $this->expectException(HectorException::class);
        $this->expectExceptionMessage($message);

        $this->resolvePdoOptions($options);
    }

    public function testConnection_withOptions(): void
    {
        $core = new Core(new TestEnvDirectories(), cache: false);
        $core->getConfig()->addConfig(
            new ArrayAdapter(
                [
                    'hector' => [
                        'options' => [
                            'PDO::ATTR_TIMEOUT' => 5,
                            '20' => false,
                        ],
                    ],
                ],
                priority: 1000,
            )
        );
        BerliozPackage::register($core->getContainer());

        $connection = $core->getContainer()->get(Connection::class);

        $this->assertSame(
            [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_EMULATE_PREPARES => false],
            $connection->__serialize()['options'],
        );
    }
}
