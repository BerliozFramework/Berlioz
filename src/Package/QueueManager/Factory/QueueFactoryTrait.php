<?php

declare(strict_types=1);

namespace Berlioz\Package\QueueManager\Factory;

use Berlioz\QueueManager\RateLimiter\MultiRateLimiter;
use Berlioz\QueueManager\RateLimiter\NullRateLimiter;
use Berlioz\QueueManager\RateLimiter\RateLimiterInterface;

trait QueueFactoryTrait
{
    private static function getRateLimiterFromConfig(mixed $rateLimits): RateLimiterInterface
    {
        return match (true) {
            is_string($rateLimits) => MultiRateLimiter::createFromString($rateLimits),
            is_array($rateLimits) => new MultiRateLimiter(...array_map(
                fn(string $rateLimit) => self::getRateLimiterFromConfig($rateLimit),
                $rateLimits,
            )),
            default => new NullRateLimiter(),
        };
    }
}
