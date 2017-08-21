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
use Berlioz\Core\App\AppAwareInterface;

interface TemplateInterface extends AppAwareInterface
{
    /**
     * TemplateInterface constructor
     *
     * @param \Berlioz\Core\App $app Application
     */
    public function __construct(App $app);

    /**
     * Register a new path for template engine
     *
     * @param string      $path      Path
     * @param string|null $namespace Namespace
     *
     * @return void
     */
    public function registerPath(string $path, string $namespace = null): void;

    /**
     * Render a template
     *
     * @param string $name      Template filename
     * @param array  $variables Variables
     *
     * @return string
     */
    public function render(string $name, array $variables = []): string;

    /**
     * Has block in template ?
     *
     * @param string $tplName   Template filename
     * @param string $blockName Block name
     *
     * @return bool
     */
    public function hasBlock(string $tplName, string $blockName): bool;

    /**
     * Render a block in template
     *
     * @param string $tplName   Template filename
     * @param string $blockName Block name
     * @param array  $variables Variables
     *
     * @return string
     */
    public function renderBlock(string $tplName, string $blockName, array $variables = []): string;
}