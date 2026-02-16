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

class JobDescriptor implements JobDescriptorInterface
{
    private readonly Payload $payload;

    public function __construct(
        private readonly ?string $name,
        PayloadInterface|array $payload,
    ) {
        is_array($payload) && $payload = new Payload($payload);
        $this->payload = $payload;
    }

    public function jsonSerialize(): array
    {
        return [
            ...$this->payload->getArrayCopy(),
            'jobName' => $this->name,
        ];
    }

    /**
     * @inheritDoc
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    public function getPayload(): Payload
    {
        return $this->payload;
    }
}
