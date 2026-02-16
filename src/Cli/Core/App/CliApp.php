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

namespace Berlioz\Cli\Core\App;

use Berlioz\Cli\Core\Command\CommandHandler;
use Berlioz\Cli\Core\Exception\CliException;
use Berlioz\Core\App\AbstractApp;
use Berlioz\Core\Core;
use Berlioz\ServiceContainer\Inflector\Inflector;

/**
 * Class CliApp.
 */
class CliApp extends AbstractApp
{
    /**
     * HttpApp constructor.
     *
     * @param Core|null $core
     */
    public function __construct(?Core $core = null)
    {
        parent::__construct($core);

        $this->getCore()->getContainer()->addInflector(
            new Inflector(
                CliAppAwareInterface::class,
                'setApp',
                ['app' => $this]
            )
        );
    }

    /**
     * @inheritDoc
     */
    protected function boot(): void
    {
    }

    /**
     * Handle.
     *
     * @param array|null $argv
     *
     * @return int
     * @throws CliException
     */
    public function handle(?array $argv = null): int
    {
        /** @var CommandHandler $commandHandler */
        $commandHandler = $this->get(CommandHandler::class);

        return $commandHandler->handle($argv);
    }
}