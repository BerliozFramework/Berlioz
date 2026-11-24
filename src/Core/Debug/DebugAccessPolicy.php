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

namespace Berlioz\Core\Debug;

use Berlioz\Config\ConfigInterface;
use Berlioz\Config\Exception\ConfigException;
use Berlioz\Helpers\NetworkHelper;

/**
 * Class DebugAccessPolicy.
 *
 * Decides whether a client may use the debug features, from the `berlioz.debug` configuration.
 */
final readonly class DebugAccessPolicy
{
    /** Allowed addresses when `berlioz.debug.ip` is not configured. */
    public const DEFAULT_ALLOWED = ['127.0.0.1', '::1'];

    private const FORWARDING_HEADERS = ['HTTP_FORWARDED', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP'];
    private const ALIASES = ['loopback', 'private', 'public', '*'];

    public function __construct(private ConfigInterface $config)
    {
    }

    /**
     * Is debug enabled in configuration?
     *
     * @throws ConfigException
     */
    public function isEnabled(): bool
    {
        return true === $this->config->get('berlioz.debug.enable', false);
    }

    /**
     * Is a local process (CLI, without remote client) allowed?
     *
     * A local process is allowed when the allow-list allows loopback clients.
     *
     * @throws ConfigException
     */
    public function isLocalProcessAllowed(): bool
    {
        $allowed = $this->getAllowed();
        if (null === $allowed) {
            return false;
        }

        return $this->allowsEveryone($allowed)
            || $this->matches(self::DEFAULT_ALLOWED[0], $allowed)
            || $this->matches(self::DEFAULT_ALLOWED[1], $allowed);
    }

    /**
     * Is the client of the given server parameters allowed?
     *
     * @param array $serverParams Server parameters (`$_SERVER` or PSR-7 server params)
     *
     * @throws ConfigException
     */
    public function isClientAllowed(array $serverParams): bool
    {
        $allowed = $this->getAllowed();
        if (null === $allowed) {
            return false;
        }

        // Client identity is irrelevant when everyone is allowed
        if ($this->allowsEveryone($allowed)) {
            return true;
        }

        $clientIp = $this->resolveClientIp($serverParams);
        if (null === $clientIp) {
            return false;
        }

        return $this->matches($clientIp, $allowed);
    }

    /**
     * Get the allow-list, or null when debug is disabled or nobody is allowed.
     *
     * @throws ConfigException
     */
    private function getAllowed(): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $allowed = $this->config->get('berlioz.debug.ip') ?? self::DEFAULT_ALLOWED;
        if (!is_array($allowed) || [] === $allowed) {
            return null;
        }

        return $allowed;
    }

    private function allowsEveryone(array $allowed): bool
    {
        return in_array('*', array_map(fn(mixed $entry) => is_string($entry) ? trim($entry) : $entry, $allowed), true);
    }

    /**
     * Resolve the client IP, or null when it cannot be determined reliably.
     *
     * @throws ConfigException
     */
    private function resolveClientIp(array $serverParams): ?string
    {
        $remoteAddr = trim((string)($serverParams['REMOTE_ADDR'] ?? ''));
        if (!NetworkHelper::isValidIp($remoteAddr)) {
            return null;
        }

        $trustedProxies = $this->config->get('berlioz.proxies.trusted', []);
        $trustedProxies = is_array($trustedProxies) ? array_filter($trustedProxies, is_string(...)) : [];

        // Untrusted direct peer: forwarded requests come from an unknown client
        if ([] === $trustedProxies || !NetworkHelper::isTrustedProxy($remoteAddr, $trustedProxies)) {
            foreach (self::FORWARDING_HEADERS as $header) {
                if (isset($serverParams[$header])) {
                    return null;
                }
            }

            return $remoteAddr;
        }

        // Trusted proxy that does not forward any client
        if ([] === NetworkHelper::forwardedForParse((string)($serverParams['HTTP_X_FORWARDED_FOR'] ?? ''))) {
            return null;
        }

        return NetworkHelper::clientIp($trustedProxies, $serverParams);
    }

    private function matches(string $clientIp, array $allowed): bool
    {
        $hostname = null;

        foreach ($allowed as $entry) {
            if (!is_string($entry)) {
                continue;
            }

            if (NetworkHelper::isTrustedProxy($clientIp, [$entry])) {
                return true;
            }

            if ($this->isAddressExpression($entry)) {
                continue;
            }

            trigger_error(
                sprintf('Hostname "%s" in "berlioz.debug.ip" configuration is deprecated since 3.4 ', $entry)
                . 'and will be removed in 4.0. Use IP addresses, CIDR ranges or aliases instead. '
                . 'See https://getberlioz.com/docs/3.x/getting-started/upgrading',
                E_USER_DEPRECATED,
            );

            $hostname ??= gethostbyaddr($clientIp);
            if ($hostname === $entry) {
                return true;
            }
        }

        return false;
    }

    private function isAddressExpression(string $entry): bool
    {
        return in_array(strtolower(trim($entry)), self::ALIASES, true)
            || str_contains($entry, '/')
            || NetworkHelper::isValidIp($entry);
    }
}
