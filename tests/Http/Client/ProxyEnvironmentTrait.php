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

namespace Berlioz\Http\Client\Tests;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;

/**
 * Isolate tests from the proxy environment variables, restored after each test.
 */
trait ProxyEnvironmentTrait
{
    private const PROXY_ENV = [
        'http_proxy',
        'HTTP_PROXY',
        'https_proxy',
        'HTTPS_PROXY',
        'all_proxy',
        'ALL_PROXY',
        'no_proxy',
        'NO_PROXY',
    ];

    /** @var array<string, string|false> */
    private array $proxyEnvBackup = [];

    #[Before]
    protected function backupProxyEnv(): void
    {
        foreach (self::PROXY_ENV as $name) {
            $this->proxyEnvBackup[$name] = getenv($name);
            putenv($name);
        }
    }

    #[After]
    protected function restoreProxyEnv(): void
    {
        foreach ($this->proxyEnvBackup as $name => $value) {
            putenv(false === $value ? $name : $name . '=' . $value);
        }
        $this->proxyEnvBackup = [];
    }

    /**
     * Set proxy environment variables.
     *
     * @param array<string, string> $env
     */
    protected function setProxyEnv(array $env): void
    {
        foreach ($env as $name => $value) {
            putenv($name . '=' . $value);
        }
    }
}
