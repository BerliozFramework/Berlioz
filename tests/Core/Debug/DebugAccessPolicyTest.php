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

namespace Berlioz\Core\Tests\Debug;

use Berlioz\Config\Adapter\ArrayAdapter;
use Berlioz\Config\Config;
use Berlioz\Core\Debug\DebugAccessPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DebugAccessPolicyTest extends TestCase
{
    private function policy(array $debug, array $trustedProxies = []): DebugAccessPolicy
    {
        return new DebugAccessPolicy(new Config([
            new ArrayAdapter(json_decode(
                file_get_contents(__DIR__ . '/../../../src/Core/resources/config.default.json'),
                true,
            )),
            new ArrayAdapter(['berlioz' => ['debug' => $debug, 'proxies' => ['trusted' => $trustedProxies]]], 10),
        ]));
    }

    /**
     * @return iterable<string, array{array, array, array, bool}>
     */
    public static function provideClients(): iterable
    {
        $default = ['enable' => true];

        // Debug disabled
        yield 'disabled by default' => [[], [], ['REMOTE_ADDR' => '127.0.0.1'], false];
        yield 'disabled with remote access' => [
            ['enable' => false, 'ip' => ['*']],
            [],
            ['REMOTE_ADDR' => '127.0.0.1'],
            false,
        ];
        yield 'enable not strictly true' => [['enable' => 'true'], [], ['REMOTE_ADDR' => '127.0.0.1'], false];

        // Default policy: loopback only
        yield 'default IPv4 loopback' => [$default, [], ['REMOTE_ADDR' => '127.0.0.1'], true];
        yield 'default IPv6 loopback' => [$default, [], ['REMOTE_ADDR' => '::1'], true];
        yield 'default other loopback' => [$default, [], ['REMOTE_ADDR' => '127.0.0.2'], false];
        yield 'default external client' => [$default, [], ['REMOTE_ADDR' => '203.0.113.10'], false];
        yield 'default private client' => [$default, [], ['REMOTE_ADDR' => '172.17.0.1'], false];
        yield 'default without remote address' => [$default, [], [], false];
        yield 'default invalid remote address' => [$default, [], ['REMOTE_ADDR' => 'localhost'], false];

        // Explicit configuration
        yield 'empty allow-list' => [['enable' => true, 'ip' => []], [], ['REMOTE_ADDR' => '127.0.0.1'], false];
        yield 'invalid allow-list' => [['enable' => true, 'ip' => '*'], [], ['REMOTE_ADDR' => '127.0.0.1'], false];
        yield 'explicit remote IP' => [
            ['enable' => true, 'ip' => ['203.0.113.10']],
            [],
            ['REMOTE_ADDR' => '203.0.113.10'],
            true,
        ];
        yield 'explicit list replaces default' => [
            ['enable' => true, 'ip' => ['203.0.113.10']],
            [],
            ['REMOTE_ADDR' => '127.0.0.1'],
            false,
        ];
        yield 'explicit CIDR' => [
            ['enable' => true, 'ip' => ['203.0.113.0/24']],
            [],
            ['REMOTE_ADDR' => '203.0.113.10'],
            true,
        ];
        yield 'everyone IPv4' => [['enable' => true, 'ip' => ['*']], [], ['REMOTE_ADDR' => '203.0.113.10'], true];
        yield 'everyone IPv6' => [['enable' => true, 'ip' => ['*']], [], ['REMOTE_ADDR' => '2001:db8::1'], true];
        yield 'private alias' => [
            ['enable' => true, 'ip' => ['loopback', 'private']],
            [],
            ['REMOTE_ADDR' => '172.17.0.1'],
            true,
        ];
        yield 'private alias external' => [
            ['enable' => true, 'ip' => ['loopback', 'private']],
            [],
            ['REMOTE_ADDR' => '203.0.113.10'],
            false,
        ];

        // Untrusted proxies
        yield 'loopback proxy with X-Forwarded-For' => [
            $default,
            [],
            ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.10'],
            false,
        ];
        yield 'loopback proxy with Forwarded' => [
            $default,
            [],
            ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_FORWARDED' => 'for=203.0.113.10'],
            false,
        ];
        yield 'loopback proxy with X-Real-IP' => [
            $default,
            [],
            ['REMOTE_ADDR' => '::1', 'HTTP_X_REAL_IP' => '203.0.113.10'],
            false,
        ];
        yield 'private proxy with X-Forwarded-For' => [
            ['enable' => true, 'ip' => ['private']],
            [],
            ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.10'],
            false,
        ];
        yield 'spoofed X-Forwarded-For' => [
            $default,
            [],
            ['REMOTE_ADDR' => '203.0.113.10', 'HTTP_X_FORWARDED_FOR' => '127.0.0.1'],
            false,
        ];

        // Trusted proxies
        yield 'trusted loopback proxy, external client' => [
            $default,
            ['loopback'],
            ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.10'],
            false,
        ];
        yield 'trusted loopback proxy, allowed client' => [
            ['enable' => true, 'ip' => ['198.51.100.5']],
            ['loopback'],
            ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => '198.51.100.5'],
            true,
        ];
        yield 'trusted proxy without forwarded client' => [
            $default,
            ['loopback'],
            ['REMOTE_ADDR' => '127.0.0.1'],
            false,
        ];
        yield 'trusted proxy with invalid forwarded client' => [
            $default,
            ['loopback'],
            ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => 'unknown'],
            false,
        ];
        yield 'untrusted proxy despite trusted list' => [
            ['enable' => true, 'ip' => ['198.51.100.5']],
            ['10.0.0.0/8'],
            ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => '198.51.100.5'],
            false,
        ];
    }

    #[DataProvider('provideClients')]
    public function testIsClientAllowed(array $debug, array $trustedProxies, array $server, bool $expected): void
    {
        $this->assertSame($expected, $this->policy($debug, $trustedProxies)->isClientAllowed($server));
    }

    public function testIsLocalProcessAllowed(): void
    {
        $this->assertFalse($this->policy([])->isLocalProcessAllowed());
        $this->assertTrue($this->policy(['enable' => true])->isLocalProcessAllowed());
        $this->assertFalse($this->policy(['enable' => true, 'ip' => []])->isLocalProcessAllowed());
        $this->assertFalse($this->policy(['enable' => true, 'ip' => ['127.0.0.1']])->isLocalProcessAllowed());
    }

    public function testIsClientAllowed_hostnameIsDeprecated(): void
    {
        $hostname = gethostbyaddr('127.0.0.1');
        $policy = $this->policy(['enable' => true, 'ip' => [$hostname]]);

        $deprecations = [];
        set_error_handler(function (int $errno, string $errstr) use (&$deprecations): bool {
            if ($errno !== E_USER_DEPRECATED) {
                return false;
            }

            $deprecations[] = $errstr;

            return true;
        });

        try {
            $allowed = $policy->isClientAllowed(['REMOTE_ADDR' => '127.0.0.1']);
        } finally {
            restore_error_handler();
        }

        $this->assertTrue($allowed);
        $this->assertSame(
            [
                sprintf('Hostname "%s" in "berlioz.debug.ip" configuration is deprecated since 3.4 ', $hostname)
                . 'and will be removed in 4.0. Use IP addresses, CIDR ranges or aliases instead. '
                . 'See https://getberlioz.com/docs/3.x/getting-started/upgrading',
            ],
            $deprecations,
        );
    }

    public function testIsClientAllowed_addressesAreNotDeprecated(): void
    {
        $policy = $this->policy(['enable' => true, 'ip' => ['198.51.100.5', '10.0.0.0/8', 'Private', '0.0.0.0']]);

        $deprecations = [];
        set_error_handler(function (int $errno, string $errstr) use (&$deprecations): bool {
            $deprecations[] = $errstr;

            return true;
        });

        try {
            $allowed = $policy->isClientAllowed(['REMOTE_ADDR' => '203.0.113.10']);
        } finally {
            restore_error_handler();
        }

        $this->assertFalse($allowed);
        $this->assertSame([], $deprecations);
    }
}
