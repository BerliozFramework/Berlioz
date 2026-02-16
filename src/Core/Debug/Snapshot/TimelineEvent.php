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

namespace Berlioz\Core\Debug\Snapshot;

/**
 * Class TimelineEvent.
 */
class TimelineEvent extends TimelineActivity
{
    public function __construct(protected Event $event)
    {
        parent::__construct($event->getName(), 'Events');
    }
}