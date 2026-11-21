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

namespace Berlioz\Core\Event;

use Berlioz\Core\App\AbstractApp;

/**
 * Class TerminateEvent.
 *
 * Dispatched by AbstractApp::terminate(), once the application has done its job
 * (e.g. after the HTTP response has been sent to the client).
 */
class TerminateEvent
{
    public function __construct(protected AbstractApp $app)
    {
    }

    /**
     * Get application.
     *
     * @return AbstractApp
     */
    public function getApp(): AbstractApp
    {
        return $this->app;
    }
}
