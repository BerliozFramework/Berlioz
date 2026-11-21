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

namespace Berlioz\Cli\Core\Event;

use Berlioz\Cli\Core\App\CliApp;
use Berlioz\Core\Event\TerminateEvent;

/**
 * Class CliTerminateEvent.
 */
class CliTerminateEvent extends TerminateEvent
{
    public function __construct(
        CliApp $app,
        protected ?int $exitCode = null,
    ) {
        parent::__construct($app);
    }

    /**
     * @inheritDoc
     */
    public function getApp(): CliApp
    {
        /** @var CliApp */
        return $this->app;
    }

    /**
     * Get exit code of the command, null if the command has not been handled.
     *
     * @return int|null
     */
    public function getExitCode(): ?int
    {
        return $this->exitCode;
    }
}
