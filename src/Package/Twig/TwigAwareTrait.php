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

namespace Berlioz\Package\Twig;

use Berlioz\Core\Exception\BerliozException;
use Berlioz\Package\Twig\Exception\TwigException;
use Twig\Error\Error;

/**
 * Describes a twig-aware instance.
 */
trait TwigAwareTrait
{
    protected Twig|null $twig = null;

    /**
     * Get twig.
     *
     * @return Twig|null
     */
    public function getTwig(): ?Twig
    {
        return $this->twig;
    }

    /**
     * Set twig.
     *
     * @param Twig $twig
     *
     * @return static
     */
    public function setTwig(Twig $twig): static
    {
        $this->twig = $twig;

        return $this;
    }

    /**
     * Render a template.
     *
     * @param string $name Filename of template
     * @param array $variables Variables for template
     *
     * @return string Output content
     * @throws BerliozException
     * @throws Error
     */
    public function render(string $name, array $variables = []): string
    {
        return $this->getTwig()?->render($name, $variables) ?? throw TwigException::notLoaded();
    }

    /**
     * Render a block in template.
     *
     * @param string $name Filename of template
     * @param string $blockName Block name
     * @param array $variables Variables
     *
     * @return string
     * @throws BerliozException
     * @throws Error
     */
    public function renderBlock(string $name, string $blockName, array $variables = []): string
    {
        return $this->getTwig()?->renderBlock($name, $blockName, $variables) ?? throw TwigException::notLoaded();
    }
}