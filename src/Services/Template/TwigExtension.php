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

class TwigExtension extends \Twig_Extension
{
    /** @var \Berlioz\Core\Services\Template\TemplateInterface Template engine */
    private $templateEngine;

    /**
     * TwigExtension constructor
     *
     * @param \Berlioz\Core\Services\Template\TemplateInterface $templateEngine Template engine
     */
    public function __construct(TemplateInterface $templateEngine)
    {
        $this->templateEngine = $templateEngine;
        $this->getTemplateEngine()->registerPath(__DIR__ . '/../..', 'Berlioz-Core');
    }

    /**
     * Get application
     *
     * @return \Berlioz\Core\App
     */
    public function getApp(): App
    {
        return $this->getTemplateEngine()->getApp();
    }

    /**
     * Get template engine
     *
     * @return \Berlioz\Core\Services\Template\TemplateInterface
     */
    public function getTemplateEngine(): TemplateInterface
    {
        return $this->templateEngine;
    }

    /**
     * Returns a list of filters to add to the existing list.
     *
     * @return \Twig_Filter[]
     */
    public function getFilters()
    {
        $filters = [];

        $filters[] = new \Twig_Filter('date_format', [$this, 'filterDateFormat']);
        $filters[] = new \Twig_Filter('truncate', 'b_truncate');
        $filters[] = new \Twig_Filter('nl2p', 'b_nl2p', ['is_safe' => ['html']]);
        $filters[] = new \Twig_Filter('human_file_size', 'b_human_file_size');
        $filters[] = new \Twig_Filter('json_decode', 'json_decode');

        return $filters;
    }

    /**
     * Filter to format date
     *
     * @param \DateTime|int $datetime DateTime object or timestamp
     * @param string        $pattern  Pattern of date result waiting
     * @param string        $locale   Locale for pattern translation
     *
     * @return string
     */
    public function filterDateFormat($datetime, string $pattern = 'dd/MM/yyyy', string $locale = null): string
    {
        if (empty($locale)) {
            $locale = $this->getApp()->getProfile()->getLocale();
        }

        return b_date_format($datetime, $pattern, $locale);
    }

    /**
     * Returns a list of tests to add to the existing list.
     *
     * @return \Twig_Filter[]
     */
    public function getTests()
    {
        $tests = [];

        $tests[] = new \Twig_Test('instance of', [$this, 'testInstanceOf']);

        return $tests;
    }

    /**
     * Test instance of.
     *
     * @param mixed  $object     The tested object
     * @param string $class_name The class name
     *
     * @return bool
     */
    public function testInstanceOf($object, $class_name): bool
    {
        return is_a($object, $class_name, true);
    }

    /**
     * Returns a list of functions to add to the existing list.
     *
     * @return \Twig_Function[]
     */
    public function getFunctions()
    {
        $functions = [];

        // Routing
        $functions[] = new \Twig_Function('path', [$this, 'functionPath']);

        return $functions;
    }

    /**
     * Function path to generate path
     *
     * @param string $name
     * @param array  $parameters
     *
     * @return string
     */
    public function functionPath(string $name, array $parameters = []): string
    {
        return $this->getApp()->getService('routing')->generate($name, $parameters);
    }
}