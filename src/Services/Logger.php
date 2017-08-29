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

namespace Berlioz\Core\Services;


use Berlioz\Core\App;
use Berlioz\Core\App\AppAwareTrait;
use Berlioz\Core\ConfigInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

class Logger extends AbstractLogger implements LoggerInterface
{
    use AppAwareTrait;
    /** @var resource File pointer */
    private $fp;
    /** @var array Logs */
    private $logs;

    /**
     * Logger constructor.
     *
     * @param \Berlioz\Core\App $app Application
     */
    public function __construct(App $app)
    {
        $this->setApp($app);
        $this->logs = [];
    }

    /**
     * Logger destructor.
     */
    public function __destruct()
    {
        $this->writeLogs();

        // Close resource
        if (is_resource($this->fp)) {
            @fclose($this->fp);
        }
    }

    /**
     * @inheritdoc
     */
    public function count(): int
    {
        return count($this->logs);
    }

    /**
     * @inheritdoc
     */
    public function getLogs(string $level = null): array
    {
        if (!is_null($level)) {
            return
                array_filter(
                    $this->logs,
                    function ($log) use ($level) {
                        return $log['level'] == $level;
                    });
        } else {
            return $this->logs;
        }
    }

    /**
     * @inheritdoc
     */
    public function getFirstTime()
    {
        $firstTime = false;

        if ($firstLog = reset($this->logs)) {
            $firstTime = $firstLog['time'];
        }

        return $firstTime;
    }

    /**
     * @inheritdoc
     */
    public function log($level, $message, array $context = [])
    {
        if ($this->needToLog($level)) {
            // Insert context into message
            foreach ($context as $key => $value) {
                $message = str_replace('{' . $key . '}', $value, $message);
            }

            // Save logs
            $this->logs[] = ['time'    => microtime(true),
                             'level'   => $level,
                             'message' => $message,
                             'written' => false];

            $this->writeLogs();
        }
    }

    /**
     * Write log on file.
     */
    private function writeLogs()
    {
        // Write logs
        $fileName = $this->getApp()->getConfig()->getDirectory(ConfigInterface::DIR_VAR_LOGS) . '/Berlioz.log';

        if (is_resource($this->fp) || is_resource($this->fp = @fopen($fileName, 'a'))) {
            if (count($this->logs) > 0) {
                foreach ($this->logs as $key => $log) {
                    if (!$log['written']) {
                        $line = sprintf("%-26s %-11s %s\n",
                                        \DateTime::createFromFormat('U.u', number_format($log['time'], 6, '.', ''))
                                                 ->format('Y-m-d H:i:s.u'),
                                        '[' . $log['level'] . ']',
                                        $log['message']);

                        if (@fwrite($this->fp, $line) !== false) {
                            $this->logs[$key]['written'] = true;
                        }
                    }
                }
            }
            unset($log);
        }
    }

    /**
     * Need to log ?
     *
     * @param string $level
     *
     * @return bool
     */
    private function needToLog(string $level)
    {
        $logLevels = [LogLevel::EMERGENCY => 0,
                      LogLevel::ALERT     => 1,
                      LogLevel::CRITICAL  => 2,
                      LogLevel::ERROR     => 3,
                      LogLevel::WARNING   => 4,
                      LogLevel::NOTICE    => 5,
                      LogLevel::INFO      => 6,
                      LogLevel::DEBUG     => 7];

        if (isset($logLevels[$this->getApp()->getConfig()->getLogLevel()])) {
            return isset($logLevels[$level]) && $logLevels[$level] <= $logLevels[$this->getApp()->getConfig()->getLogLevel()];
        } else {
            return false;
        }
    }
}