<?php
/**
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2017 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\Core\Exception;


class RoutingException extends BerliozException
{
    // Default messages
    const messages = [0   => 'Unknown error',
                      100 => 'Continue',
                      101 => 'Switching Protocols',
                      200 => 'OK',
                      201 => 'Created',
                      202 => 'Accepted',
                      203 => 'Non-Authoritative Information',
                      204 => 'No Content',
                      205 => 'Reset Content',
                      206 => 'Partial Content',
                      300 => 'Multiple Choices',
                      301 => 'Moved Permanently',
                      302 => 'Moved Temporarily',
                      303 => 'See Other',
                      304 => 'Not Modified',
                      305 => 'Use Proxy',
                      400 => 'Bad Request',
                      401 => 'Unauthorized',
                      402 => 'Payment Required',
                      403 => 'Forbidden',
                      404 => 'Not Found',
                      405 => 'Method Not Allowed',
                      406 => 'Not Acceptable',
                      407 => 'Proxy Authentication Required',
                      408 => 'Request Time-out',
                      409 => 'Conflict',
                      410 => 'Gone',
                      411 => 'Length Required',
                      412 => 'Precondition Failed',
                      413 => 'Request Entity Too Large',
                      414 => 'Request-URI Too Large',
                      415 => 'Unsupported Media Type',
                      500 => 'Internal Server Error',
                      501 => 'Not Implemented',
                      502 => 'Bad Gateway',
                      503 => 'Service Unavailable',
                      504 => 'Gateway Time-out',
                      505 => 'HTTP Version not supported'];

    /**
     * RoutingException constructor.
     *
     * @param int             $code
     * @param string          $message
     * @param \Throwable|null $previous
     */
    public function __construct(int $code = 500, $message = '', \Throwable $previous = null)
    {
        // Default message
        if (empty($message)) {
            $message = static::messages[$code] ?: static::messages[0];
        }

        parent::__construct($message, $code, $previous);
    }
}