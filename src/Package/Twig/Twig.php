<?php
/**
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2020 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\Package\Twig;

use Berlioz\Config\Exception\ConfigException;
use Berlioz\Core\Core;
use Berlioz\Core\Exception\BerliozException;
use Berlioz\Package\Twig\Exception\TwigException;
use Berlioz\ServiceContainer\Exception\ContainerException;
use Exception;
use Throwable;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Error\LoaderError;
use Twig\Extension\DebugExtension;
use Twig\Extension\ExtensionInterface;
use Twig\Extension\ProfilerExtension;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\Profiler\Profile;
use Twig\RuntimeLoader\ContainerRuntimeLoader;

class Twig
{
    private ChainLoader $loader;
    private Environment $twig;
    private ?Profile $profile = null;

    /**
     * Twig constructor.
     *
     * @param Core $core Berlioz Core
     * @param array $paths Twig paths
     * @param array $options Twig options
     * @param string[] $extensions Twig extensions classes
     * @param array $globals Globals variables
     *
     * @throws ContainerException
     * @throws LoaderError
     * @throws TwigException
     * @throws ConfigException
     */
    public function __construct(
        protected Core $core,
        array $paths = [],
        array $options = [],
        array $extensions = [],
        array $globals = []
    ) {
        // Twig
        $this->loader = new ChainLoader();
        $this->loader->addLoader($fileLoader = new FilesystemLoader([], $this->core->getDirectories()->getAppDir()));
        $this->twig = new Environment($this->loader, $options);

        // Add runtime loader with container
        $this->twig->addRuntimeLoader(new ContainerRuntimeLoader($core->getContainer()));

        // Debug mode only if not a production environment
        if (Core::ENV_PROD !== $this->core->getEnv() && $this->core->getDebug()->isEnabled()) {
            $this->addExtension(new DebugExtension());
        }

        if ($this->core->getDebug()->isEnabled()) {
            $this->profile = new Profile();
            $this->twig->addExtension(new ProfilerExtension($this->profile));
        }

        // Paths
        foreach ($paths as $namespace => $path) {
            $fileLoader->addPath($path, $namespace);
        }

        // Add extensions
        $this->addExtension(...$extensions);

        // Add globals
        $this->addGlobals($globals);
    }

    /**
     * __debugInfo() PHP magic method.
     *
     * @return array
     */
    public function __debugInfo()
    {
        return ['loader' => '*TWIG LOADER*', 'twig' => '*TWIG*'];
    }

    /**
     * Get Twig loader.
     *
     * @return ChainLoader
     */
    public function getLoader(): ChainLoader
    {
        return $this->loader;
    }

    /**
     * Get Twig environment.
     *
     * @return Environment
     */
    public function getEnvironment(): Environment
    {
        return $this->twig;
    }

    /**
     * Get profile.
     *
     * @return Profile|null
     */
    public function getProfile(): ?Profile
    {
        return $this->profile;
    }

    /**
     * Add extension.
     *
     * @param ExtensionInterface|string ...$extension
     *
     * @throws ContainerException
     * @throws TwigException
     */
    public function addExtension(ExtensionInterface|string ...$extension): void
    {
        foreach ($extension as $anExtension) {
            if (is_string($anExtension)) {
                $anExtension = $this->newExtensionFromString($anExtension);
            }

            if (false === ($anExtension instanceof ExtensionInterface)) {
                throw TwigException::invalidExtension($anExtension);
            }

            $this->twig->addExtension($anExtension);
        }
    }

    /**
     * New extension from string.
     *
     * @param string $extension
     *
     * @return ExtensionInterface
     * @throws ContainerException
     */
    protected function newExtensionFromString(string $extension): ExtensionInterface
    {
        if (str_starts_with($extension, '@')) {
            return $this->core->getContainer()->get(substr($extension, 1));
        }

        return
            $this->core->getContainer()->call(
                $extension,
                [
                    'templating' => $this,
                    'twigLoader' => $this->loader,
                    'twig' => $this->twig,
                ]
            );
    }

    /**
     * Add globals.
     *
     * @param array $globals
     */
    public function addGlobals(array $globals): void
    {
        array_walk($globals, fn($value, $name) => $this->addGlobal($name, $value));
    }

    /**
     * Add global.
     *
     * @param string $name
     * @param mixed $value
     */
    public function addGlobal(string $name, mixed $value): void
    {
        if (is_string($value) && str_starts_with($value, '@')) {
            if ($this->core->getContainer()->has(substr($value, 1))) {
                $this->getEnvironment()->addGlobal($name, $this->core->getContainer()->get(substr($value, 1)));
                return;
            }
        }

        $this->getEnvironment()->addGlobal($name, $value);
    }

    /////////////////
    /// RENDERING ///
    /////////////////

    /**
     * Render template.
     *
     * @param string $name Template name
     * @param array $variables
     *
     * @return string
     * @throws BerliozException
     * @throws Error
     */
    public function render(string $name, array $variables = []): string
    {
        $twigActivity = $this->core->getDebug()->newActivity('Twig rendering');
        $twigActivity
            ->start()
            ->setDescription(sprintf('Rendering of template "%s"', $name));

        // Twig rendering
        try {
            return $this->getEnvironment()->render($name, $variables);
        } catch (Error $e) {
            throw $e;
        } catch (Exception $e) {
            throw new Error('An error occurred during rendering', -1, null, $e);
        } catch (Throwable $e) {
            throw new BerliozException('An error occurred during rendering', 0, $e);
        } finally {
            $twigActivity->end();
        }
    }

    /**
     * Has block in template?
     *
     * @param string $name Template name
     * @param string $blockName Block name
     *
     * @return bool
     * @throws Error
     */
    public function hasBlock(string $name, string $blockName): bool
    {
        return $this->getEnvironment()->load($name)->hasBlock($blockName);
    }

    /**
     * Render block of template.
     *
     * @param string $name Template name
     * @param string $blockName Block name
     * @param array $variables
     *
     * @return string
     * @throws BerliozException
     * @throws Error
     */
    public function renderBlock(string $name, string $blockName, array $variables = []): string
    {
        $twigActivity = $this->core->getDebug()->newActivity('Twig block rendering');
        $twigActivity
            ->start()
            ->setDescription(sprintf('Rendering of block "%s" in template "%s"', $blockName, $name));

        // Twig rendering
        try {
            $template = $this->getEnvironment()->load($name);

            return $template->renderBlock($blockName, $variables);
        } catch (Error $e) {
            throw $e;
        } catch (Exception $e) {
            throw new Error('An error occurred during rendering', -1, null, $e);
        } catch (Throwable $e) {
            throw new BerliozException('An error occurred during rendering', 0, $e);
        } finally {
            $twigActivity->end();
        }
    }
}