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

use JsonSerializable;

interface JobDescriptorInterface extends JsonSerializable
{
    /**
     * Get job name.
     *
     * @return string|null
     */
    public function getName(): ?string;

    /**
     * Get decoded payload.
     *
     * @return PayloadInterface
     */
    public function getPayload(): PayloadInterface;
}
