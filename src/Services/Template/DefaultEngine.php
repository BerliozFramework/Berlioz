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

namespace Berlioz\Core\Services\Template;


use Berlioz\Core\App;
use Berlioz\Core\App\AppAwareTrait;
use Berlioz\Core\ConfigInterface;

class DefaultEngine implements TemplateInterface
{
    use AppAwareTrait;
    /** @var \Twig_Loader_Filesystem Twig loader filesystem */
    private $twigLoader;
    /** @var \Twig_Environment Twig environment */
    private $twig;

    /**
     * @inheritdoc
     */
    public function __construct(App $app, array $extensions = [])
    {
        $this->setApp($app);

        // Init Twig
        $this->getTwig()->addExtension(new TwigExtension($this));
        $this->getTwig()->addGlobal('app', $app->getProfile());
        if ($this->getApp()->getConfig()->hasCacheEnabled()) {
            $this->getTwig()->setCache($this->getApp()->getConfig()->getDirectory(ConfigInterface::DIR_VAR_CACHE) . '/Twig');
        }
        if ($this->getApp()->getConfig()->hasDebugEnabled()) {
            $this->getTwig()->enableDebug();
            $this->getTwig()->addExtension(new \Twig_Extension_Debug());
        }

        // Extensions
        foreach ($extensions as $extension) {
            if (is_string($extension) && class_exists($extension)) {
                $this->getTwig()->addExtension(new $extension);
            } else {
                throw new \InvalidArgumentException(sprintf('Extension "%s" does\'nt exists', $extension));
            }
        }
    }

    /**
     * Get Twig.
     *
     * @return \Twig_Environment
     */
    public function getTwig(): \Twig_Environment
    {
        if (is_null($this->twig)) {
            // Init Twig
            $this->twigLoader = new \Twig_Loader_Filesystem();
            $this->twig = new \Twig_Environment($this->twigLoader);
        }

        return $this->twig;
    }

    /**
     * @inheritdoc
     */
    public function registerPath(string $path, string $namespace = null): void
    {
        if (is_null($namespace)) {
            $namespace = \Twig_Loader_Filesystem::MAIN_NAMESPACE;
        }

        $this->twigLoader->addPath($path, $namespace);
    }

    /**
     * @inheritdoc
     */
    public function render(string $name, array $variables = []): string
    {
        // Debug
        $this->getApp()->getService('logging')->debug(sprintf('%s / Rendering of template "%s"', __METHOD__, $name));

        $str = $this->twig->render($name, $variables);

        // Debug
        $this->getApp()->getService('logging')->debug(sprintf('%s / Rendering of template "%s" done', __METHOD__, $name));

        return $str;
    }

    /**
     * @inheritdoc
     */
    public function hasBlock(string $tplName, string $blockName): bool
    {
        $template = $this->twig->load($tplName);

        return $template->hasBlock($blockName);
    }

    /**
     * @inheritdoc
     */
    public function renderBlock(string $tplName, string $blockName, array $variables = []): string
    {
        // Debug
        $this->getApp()->getService('logging')->debug(sprintf('%s / Rendering of block "%s" in template "%s"', __METHOD__, $blockName, $tplName));

        $template = $this->twig->load($tplName);
        $str = $template->renderBlock($blockName, $variables);

        // Debug
        $this->getApp()->getService('logging')->debug(sprintf('%s / Rendering of block "%s" in template "%s" done', __METHOD__, $blockName, $tplName));

        return $str;
    }
}