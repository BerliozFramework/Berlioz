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

declare(strict_types=1);

namespace Berlioz\FlashBag;

use Countable;
use Exception;

/**
 * FlashBag class manage flash messages to showed to the user.
 *
 * When a message is retrieved from stack, he is deleted from stack and can't be reused.
 *
 * @package Berlioz\FlashBag
 * @see Countable
 */
class FlashBag implements Countable
{
    public const TYPE_INFO = 'info';
    public const TYPE_SUCCESS = 'success';
    public const TYPE_WARNING = 'warning';
    public const TYPE_ERROR = 'error';
    protected const SESSION_KEY = '_BERLIOZ_FLASH_BAG';
    /** @var array[string[]] List of messages */
    private $messages;

    /**
     * FlashBag constructor.
     *
     * Only one instance of FlashBag can be instanced.
     * A fatal error occur if a new FlashBag class is instanced.
     *
     * @throws Exception if sessions are disabled
     */
    public function __construct()
    {
        if (session_status() == PHP_SESSION_DISABLED) {
            throw new Exception('To use FlashBag class, you must be active sessions');
        }

        // Start session if doesn't exists
        if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }

        $this->messages = [];
        if (isset($_SESSION[self::SESSION_KEY]) && is_array($_SESSION[self::SESSION_KEY])) {
            $this->messages = $_SESSION[self::SESSION_KEY];
        }
    }

    /**
     * Get the number of messages in flash bag.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->messages, COUNT_RECURSIVE) - count($this->messages);
    }

    /**
     * Get all messages, all mixed types.
     *
     * @return string[] List of messages
     */
    public function all(): array
    {
        $messages = $this->messages;

        // Clear messages
        $this->clear();

        return $messages;
    }

    /**
     * Get all messages for given type and clear flash bag of them.
     *
     * @param string $type Type of message
     *
     * @return string[] List of messages
     */
    public function get(string $type)
    {
        if (!isset($this->messages[$type])) {
            return [];
        }

        $messages = $this->messages[$type];

        // Clear messages
        $this->clear($type);

        return $messages;
    }

    /**
     * Add new message in flash bag.
     *
     * @param string $type Type of message
     * @param string $message Message
     * @param array $args
     *
     * @return static
     */
    public function add(string $type, string $message, ...$args): FlashBag
    {
        $this->messages[$type][] = sprintf($message, ...$args);

        // Save into session
        $this->saveToSession();

        return $this;
    }

    /**
     * Clear messages in flash bag.
     *
     * @param string|null $type Type of message
     *
     * @return static
     */
    public function clear(?string $type = null): FlashBag
    {
        if (null === $type) {
            $this->messages = [];
        }
        if (null !== $type && array_key_exists($type, $this->messages)) {
            unset($this->messages[$type]);
        }

        // Save into session
        $this->saveToSession();

        return $this;
    }

    /**
     * Save flash bag in PHP session.
     */
    protected function saveToSession()
    {
        // Save into sessions
        $_SESSION[self::SESSION_KEY] = $this->messages;
    }
}
