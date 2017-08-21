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

namespace Berlioz\Core\Services\Routing;


interface RouteInterface
{
    /**
     * Get name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Set name.
     *
     * @param string $name
     */
    public function setName(string $name);

    /**
     * Get methods.
     *
     * @return string[]
     */
    public function getMethods(): array;

    /**
     * Get route.
     *
     * @return string
     */
    public function getRoute(): string;

    /**
     * Set route.
     *
     * @param string $route
     *
     * @return static
     */
    public function setRoute(string $route): RouteInterface;

    /**
     * Get number of parameters.
     *
     * @return int
     */
    public function getNumberOfParameters(): int;

    /**
     * Get route regex.
     *
     * @return string
     */
    public function getRouteRegex(): string;

    /**
     * Get invoke.
     *
     * @return string[]
     */
    public function getInvoke(): array;

    /**
     * Set invoke.
     *
     * @param string $controllerClass Controller class
     * @param string $methodName      Method name
     *
     * @return static
     * @throws \Berlioz\Core\Exception\BerliozException If controller class given is not valid
     */
    public function setInvoke(string $controllerClass, string $methodName): RouteInterface;

    /**
     * Get parameter.
     *
     * @param string $name Parameter name
     *
     * @return \Berlioz\Core\Services\Routing\Parameter|null
     */
    public function getParameter(string $name);

    /**
     * Add parameter.
     *
     * @param \Berlioz\Core\Services\Routing\Parameter $parameter
     *
     * @return static
     */
    public function addParameter(Parameter $parameter): RouteInterface;

    /**
     * Get summary.
     *
     * @return mixed
     */
    public function getSummary();

    /**
     * Set summary.
     *
     * @param string $summary
     *
     * @return static
     */
    public function setSummary(string $summary): RouteInterface;

    /**
     * Get description.
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * Set description.
     *
     * @param string $description
     *
     * @return static
     */
    public function setDescription(string $description): RouteInterface;

    /**
     * No authentication requirement ?
     *
     * @return bool
     */
    public function noAuthentication(): bool;

    /**
     * Test route with path.
     *
     * @param string $test Path to test
     *
     * @return bool
     */
    public function test(string $test): bool;

    /**
     * Extract parameters from path.
     *
     * @param string $path Path
     *
     * @return array
     */
    public function extractParameters(string $path): array;

    /**
     * Generate route with parameters.
     *
     * @param array $parameters Parameters
     *
     * @return string|false
     */
    public function generate(array $parameters);

    /**
     * With parameters values.
     *
     * Return clone of self with values.
     *
     * @param array $values Parameters values
     *
     * @return static
     */
    public function withParametersValues(array $values): RouteInterface;

    /**
     * Get parameters values.
     *
     * @return array
     */
    public function getParametersValues(): array;
}