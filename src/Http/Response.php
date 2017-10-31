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

namespace Berlioz\Core\Http;


use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class Response extends Message implements ResponseInterface
{
    // Reasons
    const REASONS = [ // 1xx Informational responses
                      100 => 'Continue',
                      101 => 'Switching Protocols',
                      102 => 'Processing',
                      // 2xx Success
                      200 => 'OK',
                      201 => 'Created',
                      202 => 'Accepted',
                      203 => 'Non-Authoritative Information',
                      204 => 'No Content',
                      205 => 'Reset Content',
                      206 => 'Partial Content',
                      207 => 'Multi-Status',
                      208 => 'Already Reported',
                      226 => 'IM Used',
                      // 3xx Redirection
                      300 => 'Multiple Choices',
                      301 => 'Moved Permanently',
                      302 => 'Found',
                      303 => 'See Other',
                      304 => 'Not Modified',
                      305 => 'Use Proxy',
                      306 => 'Switch Proxy',
                      307 => 'Temporary Redirect',
                      308 => 'Permanent Redirect',
                      // 4xx Client errors
                      400 => 'Bad Request',
                      401 => 'Unauthorized',
                      402 => 'Payment Required',
                      403 => 'Forbidden',
                      404 => 'Not Found',
                      405 => 'Method Not Allowed',
                      406 => 'Not Acceptable',
                      407 => 'Proxy Authentication Required',
                      408 => 'Request Timeout',
                      409 => 'Conflict',
                      410 => 'Gone',
                      411 => 'Length Required',
                      412 => 'Precondition Failed',
                      413 => 'Payload Too Large',
                      414 => 'URI Too Long',
                      415 => 'Unsupported Media Type',
                      416 => 'Range Not Satisfiable',
                      417 => 'Expectation Failed',
                      418 => 'I\'m a teapot',
                      421 => 'Misdirected Request',
                      422 => 'Unprocessable Entity',
                      423 => 'Locked',
                      424 => 'Failed Dependency',
                      426 => 'Upgrade Required',
                      428 => 'Precondition Required',
                      429 => 'Too Many Requests',
                      431 => 'Request Header Fields Too Large',
                      451 => 'Unavailable For Legal Reasons',
                      // 5xx Server error
                      500 => 'Internal Server Error',
                      501 => 'Not Implemented',
                      502 => 'Bad Gateway',
                      503 => 'Service Unavailable',
                      504 => 'Gateway Timeout',
                      505 => 'HTTP Version Not Supported',
                      506 => 'Variant Also Negotiates',
                      507 => 'Insufficient Storage',
                      508 => 'Loop Detected',
                      510 => 'Not Extended',
                      511 => 'Network Authentication Required',
                      // Unofficial codes
                      103 => 'Checkpoint',
                      420 => 'Method Failure',
                      450 => 'Blocked by Windows Parental Controls',
                      498 => 'Invalid Token',
                      509 => 'Bandwidth Limit Exceeded',
                      530 => 'Site is frozen',
                      598 => '(Informal convention) Network read timeout error',
                      599 => '(Informal convention) Network connect timeout error',
                      // Internet Information Services
                      440 => 'Login Time-out',
                      449 => 'Retry With',
                      // nginx
                      444 => 'No Response',
                      495 => 'SSL Certificate Error',
                      496 => 'SSL Certificate Required',
                      497 => 'HTTP Request Sent to HTTPS Port',
                      499 => 'Client Closed Request',
                      // Cloudflare
                      520 => 'Unknown Error',
                      521 => 'Web Server Is Down',
                      522 => 'Connection Timed Out',
                      523 => 'Origin Is Unreachable',
                      524 => 'A Timeout Occurred',
                      525 => 'SSL Handshake Failed',
                      526 => 'Invalid SSL Certificate',
                      527 => 'Railgun Error'];
    /** @var int Status code */
    protected $statusCode;
    /** @var string Reason phrase */
    protected $reasonPhrase;

    /**
     * Response constructor.
     *
     * @param \Psr\Http\Message\StreamInterface|string|null $body         Body
     * @param int                                           $statusCode   Status code
     * @param array                                         $headers      Headers
     * @param string                                        $reasonPhrase Reason phrase
     */
    public function __construct($body = null, int $statusCode = 200, array $headers = [], string $reasonPhrase = '')
    {
        if ($body instanceof StreamInterface) {
            $this->body = $body;
        } elseif (!is_null($body)) {
            $this->body = new Stream;
            $this->body->write($body);
        } elseif (is_null($body)) {
            $this->body = new Stream;
        }

        $this->statusCode = $statusCode;
        $this->reasonPhrase = $reasonPhrase;

        $this->headers = [];
        foreach ($headers as $name => $value) {
            $name = mb_convert_case($name, MB_CASE_TITLE);
            $this->headers[$name] = (array) $value;
        }
    }

    /**
     * Gets the response status code.
     *
     * The status code is a 3-digit integer result code of the server's attempt
     * to understand and satisfy the request.
     *
     * @return int Status code.
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * Return an instance with the specified status code and, optionally, reason phrase.
     *
     * If no reason phrase is specified, implementations MAY choose to default
     * to the RFC 7231 or IANA recommended reason phrase for the response's
     * status code.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * updated status and reason phrase.
     *
     * @link http://tools.ietf.org/html/rfc7231#section-6
     * @link http://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
     *
     * @param int    $code         The 3-digit integer result code to set.
     * @param string $reasonPhrase The reason phrase to use with the
     *                             provided status code; if none is provided, implementations MAY
     *                             use the defaults as suggested in the HTTP specification.
     *
     * @return static
     * @throws \InvalidArgumentException For invalid status code arguments.
     */
    public function withStatus($code, $reasonPhrase = '')
    {
        $clone = clone $this;
        $clone->statusCode = $code;
        $clone->reasonPhrase = $reasonPhrase;

        return $clone;
    }

    /**
     * Gets the response reason phrase associated with the status code.
     *
     * Because a reason phrase is not a required element in a response
     * status line, the reason phrase value MAY be null. Implementations MAY
     * choose to return the default RFC 7231 recommended reason phrase (or those
     * listed in the IANA HTTP Status Code Registry) for the response's
     * status code.
     *
     * @link http://tools.ietf.org/html/rfc7231#section-6
     * @link http://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
     * @return string Reason phrase; must return an empty string if none present.
     */
    public function getReasonPhrase()
    {
        return $this->reasonPhrase ?? self::REASONS[$this->statusCode] ?? '';
    }
}