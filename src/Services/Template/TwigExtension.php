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
use Berlioz\Core\Exception\RuntimeException;

class TwigExtension extends \Twig_Extension
{
    const H2PUSH_CACHE_COOKIE = 'h2pushes';
    /** @var \Berlioz\Core\Services\Template\TemplateInterface Template engine */
    private $templateEngine;
    /** @var array Cache for HTTP2 push */
    private $h2pushCache = [];

    /**
     * TwigExtension constructor.
     *
     * @param \Berlioz\Core\Services\Template\TemplateInterface $templateEngine Template engine
     */
    public function __construct(TemplateInterface $templateEngine)
    {
        $this->templateEngine = $templateEngine;
        $this->getTemplateEngine()->registerPath(__DIR__ . '/../..', 'Berlioz-Core');

        // Get cache from cookies
        if (isset($_COOKIE[self::H2PUSH_CACHE_COOKIE]) && is_array($_COOKIE[self::H2PUSH_CACHE_COOKIE])) {
            $this->h2pushCache = array_keys($_COOKIE[self::H2PUSH_CACHE_COOKIE]);
        }
    }

    /**
     * Get application.
     *
     * @return \Berlioz\Core\App
     * @throws \Berlioz\Core\Exception\RuntimeException if template engine is not initialized with application
     */
    public function getApp(): App
    {
        if (!is_null($this->getTemplateEngine()->getApp())) {
            return $this->getTemplateEngine()->getApp();
        } else {
            throw new RuntimeException('Template engine is not initialized with application');
        }
    }

    /**
     * Get template engine.
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
     * Filter to format date.
     *
     * @param \DateTime|int $datetime DateTime object or timestamp
     * @param string        $pattern  Pattern of date result waiting
     * @param string        $locale   Locale for pattern translation
     *
     * @return string
     * @throws \Berlioz\Core\Exception\RuntimeException if application not accessible
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
     * @return \Twig_Test[]
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
    public function testInstanceOf($object, string $class_name): bool
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

        $functions[] = new \Twig_Function('path', [$this, 'functionPath']);
        $functions[] = new \Twig_Function('preload', [$this, 'functionPreload']);

        return $functions;
    }

    /**
     * Function path to generate path.
     *
     * @param string $name
     * @param array  $parameters
     *
     * @return string
     * @throws \Berlioz\Core\Exception\RuntimeException if application not accessible
     */
    public function functionPath(string $name, array $parameters = []): string
    {
        return $this->getApp()->getService('routing')->generate($name, $parameters);
    }

    /**
     * Function preload to pre loading of request for HTTP 2 protocol.
     *
     * @param string $link
     * @param array  $parameters
     *
     * @return string Link
     */
    public function functionPreload(string $link, array $parameters = []): string
    {
        $push = !(!empty($parameters['nopush']) && $parameters['nopush'] == true);

        if (!$push || !in_array(md5($link), $this->h2pushCache)) {
            $header = sprintf('Link: <%s>; rel=preload', $link);

            // as
            if (!empty($parameters['as'])) {
                $header = sprintf('%s; as=%s', $header, $parameters['as']);
            }
            // type
            if (!empty($parameters['type'])) {
                $header = sprintf('%s; type=%s', $header, $parameters['as']);
            }
            // crossorigin
            if (!empty($parameters['crossorigin']) && $parameters['crossorigin'] == true) {
                $header .= '; crossorigin';
            }
            // nopush
            if (!$push) {
                $header .= '; nopush';
            }

            header($header, false);

            // Cache
            if ($push) {
                $this->h2pushCache[] = md5($link);
                setcookie(sprintf('%s[%s]', self::H2PUSH_CACHE_COOKIE, md5($link)), 1, 0, '/', '', false, true);
            }
        }

        return $link;
    }
}