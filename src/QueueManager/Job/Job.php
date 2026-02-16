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

namespace Berlioz\QueueManager\Job;

use LogicException;

abstract class Job extends JobDescriptor implements JobInterface
{
    protected bool $released = false;
    protected bool $deleted = false;

    public function __construct(
        protected readonly string $id,
        ?string $name,
        protected readonly int $attempts,
        PayloadInterface|array $payload,
    ) {
        parent::__construct($name, $payload);
    }

    public function jsonSerialize(): array
    {
        return [
            ...parent::jsonSerialize(),
            'jobId' => $this->id,
        ];
    }

    public function __serialize(): array
    {
        throw new LogicException('Jobs are not serializable');
    }

    public function __unserialize($data): void
    {
        throw new LogicException('Jobs are not serializable');
    }

    /**
     * @inheritDoc
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @inheritDoc
     */
    public function getAttempts(): int
    {
        return $this->attempts;
    }

    /**
     * @inheritDoc
     */
    public function release(int $delay = 0): void
    {
        $this->released = true;
    }

    /**
     * @inheritDoc
     */
    public function isReleased(): bool
    {
        return $this->released;
    }

    /**
     * @inheritDoc
     */
    public function delete(): void
    {
        $this->deleted = true;
    }

    /**
     * @inheritDoc
     */
    public function isDeleted(): bool
    {
        return $this->deleted;
    }
}
