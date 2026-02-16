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

namespace Berlioz\Core\Tests;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;

/**
 * Trait RestoresErrorHandler.
 *
 * Restores the PHP error handler stack after each test to prevent PHPUnit from
 * flagging tests as "risky" due to a modified error handler stack.
 *
 * This is needed because Core::boot() installs a custom error handler via
 * PhpErrorHandler::handle(), and PHP's garbage collector does not guarantee
 * that Core::__destruct() (which calls restore_error_handler) will run
 * before PHPUnit checks the error handler state.
 *
 * Uses #[Before] and #[After] attributes instead of setUp()/tearDown() to
 * avoid conflicts with existing setUp()/tearDown() methods in test classes.
 */
trait RestoresErrorHandler
{
    private mixed $berliozSavedErrorHandler = null;

    #[Before]
    protected function snapshotErrorHandler(): void
    {
        // Snapshot the current error handler (PHPUnit's) by temporarily
        // replacing it and immediately restoring it.
        $this->berliozSavedErrorHandler = set_error_handler(fn() => false);
        restore_error_handler();
    }

    #[After]
    protected function restoreErrorHandler(): void
    {
        if (null === $this->berliozSavedErrorHandler) {
            return;
        }

        // Pop any extra error handlers pushed during the test until we
        // get back to PHPUnit's handler. We use a safety limit to avoid
        // an infinite loop in case something went wrong.
        for ($i = 0; $i < 10; $i++) {
            $current = set_error_handler(fn() => false);
            restore_error_handler();

            if ($current === $this->berliozSavedErrorHandler) {
                break;
            }

            restore_error_handler();
        }

        $this->berliozSavedErrorHandler = null;
    }
}
