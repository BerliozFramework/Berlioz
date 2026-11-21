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

// Stub of the PHP-FPM function, loaded only in isolated test processes.
function fastcgi_finish_request(): bool
{
    $GLOBALS['berlioz_test_fastcgi_finish_request'] = ($GLOBALS['berlioz_test_fastcgi_finish_request'] ?? 0) + 1;

    return true;
}
