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


use Berlioz\Core\Exception\BerliozException;

class Route implements RouteInterface
{
    const REGEX_PARAMETER = '/{(?<name>[\w_]+)}/';
    /** @var string Route */
    private $route;
    /** @var string[][] Route options */
    private $route_options;
    /** @var string Route regex */
    private $route_regex;
    /** @var string[] Invoke */
    private $invoke;
    /** @var \Berlioz\Core\Services\Routing\Parameter[] Parameters */
    private $parameters;
    /** @var string Summary */
    private $summary;
    /** @var string Description */
    private $description;
    /** @var mixed[] Parameters values */
    private $parameters_values;

    /**
     * Route constructor.
     */
    public function __construct()
    {
        $this->route_options = [];
        $this->invoke = [];
        $this->parameters = [];
        $this->parameters_values = [];
    }

    /**
     * Create Route from \ReflectionMethod object.
     *
     * @param \ReflectionMethod $reflectionMethod Reflection of method
     * @param string            $basePath         Base of path for all routes in method
     *
     * @return Route[]
     * @throws \Berlioz\Core\Exception\BerliozException
     */
    public static function createFromReflection(\ReflectionMethod $reflectionMethod, string $basePath = '')
    {
        $routes = [];

        if (is_a($reflectionMethod->class, '\Berlioz\Core\Controller\Controller', true)) {
            try {
                if ($reflectionMethod->isPublic()) {
                    if ($methodDoc = $reflectionMethod->getDocComment()) {
                        $docBlock = Router::getDocBlockFactory()->create($methodDoc);

                        if ($docBlock->hasTag('route')) {
                            /** @var \phpDocumentor\Reflection\DocBlock\Tags\Generic $tag */
                            foreach ($docBlock->getTagsByName('route') as $tag) {
                                $route = new Route;
                                $route->setRouteDeclaration($tag->getDescription()->render(), $basePath);
                                $route->setSummary($docBlock->getSummary());
                                $route->setDescription($docBlock->getDescription()->render());
                                $route->setInvoke($reflectionMethod->class, $reflectionMethod->getName());
                                $route->getRouteRegex();

                                $routes[] = $route;
                            }
                        }
                    }
                } else {
                    /** @var \ReflectionMethod $reflectionMethod */
                    throw new BerliozException('Must be public');
                }
            } catch (BerliozException $e) {
                /** @var \ReflectionMethod $reflectionMethod */
                throw new BerliozException(sprintf('Method "%s::%s" route error: %s', $reflectionMethod->class, $reflectionMethod->getName(), $e->getMessage()));
            }
        } else {
            throw new BerliozException(sprintf('Class "%s" must be a sub class of "\Berlioz\Core\Controller\Controller"', $reflectionMethod->class));
        }

        return $routes;
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        if (!empty($this->route_options['name']) && is_string($this->route_options['name'])) {
            return $this->route_options['name'];
        } else {
            return implode('::', $this->invoke);
        }
    }

    /**
     * @inheritdoc
     */
    public function setName(string $name)
    {
        $this->route_options['name'] = $name;
    }

    /**
     * @inheritdoc
     */
    public function getMethods(): array
    {
        $defaultMethods = [Router::HTTP_METHOD_GET,
                           Router::HTTP_METHOD_HEAD,
                           Router::HTTP_METHOD_POST,
                           Router::HTTP_METHOD_OPTIONS,
                           Router::HTTP_METHOD_CONNECT,
                           Router::HTTP_METHOD_TRACE,
                           Router::HTTP_METHOD_PUT,
                           Router::HTTP_METHOD_DELETE];
        if (isset($this->route_options['method']) && is_string($this->route_options['method'])) {
            $methods = explode(',', mb_strtoupper($this->route_options['method'] ?? ''));
        } else {
            $methods = [];
        }
        $methods = array_intersect($methods, $defaultMethods);

        if (empty($methods)) {
            return $defaultMethods;
        } else {
            return $methods;
        }
    }

    /**
     * @inheritdoc
     */
    public function getRoute(): ?string
    {
        return $this->route;
    }

    /**
     * @inheritdoc
     */
    public function setRoute(string $route): RouteInterface
    {
        $this->route = $route;

        // Reinitialization of route regex
        $this->route_regex = null;

        return $this;
    }

    /**
     * Set route definition (from doc block tag).
     *
     * @param string $doc      Description of @route tag
     * @param string $basePath Base of path for the route
     *
     * @return Route
     * @throws \Berlioz\Core\Exception\BerliozException If a parameter is declared more than one time
     */
    public function setRouteDeclaration(string $doc, string $basePath = ''): Route
    {
        $regex_define = <<<'EOD'
(?(DEFINE)
    (?<d_quotes> \'(?>[^'\\]++|\\.)*\' | "(?>[^"\\]++|\\.)*" )
    (?<d_json_element> (?: \g<d_quotes> | [\w_]+ ) \s* : \s* \g<d_quotes> )
    (?<d_json> { \s* \g<d_json_element> (?: \s* , \s* \g<d_json_element> )* \s* } )
    (?<d_bool> true | false )
    (?<d_option> [\w_]+\s*=\s*(?: \g<d_json> | \g<d_quotes> | \g<d_bool> ) )
)
EOD;

        $matches = [];
        if (preg_match('~' . $regex_define . '^ \( \s* (?<route> \g<d_quotes> ) (?<options> (?: \s* , \s* \g<d_option> )+ )? \s* \) $ ~x', $doc, $matches) == 1) {
            // Treatment for base of path
            if (!empty($basePath)) {
                // Remove slash at the end of base path
                if (substr($basePath, -1) == '/') {
                    $basePath = substr($basePath, 0, -1);
                }
            }

            // Route
            $this->setRoute($basePath . substr($matches['route'], 1, -1));

            // Route parameters
            $this->parameters = [];
            $matchesParams = [];
            if (preg_match_all(self::REGEX_PARAMETER, $this->route, $matchesParams) > 0) {
                foreach ($matchesParams['name'] as $match) {
                    if (!isset($this->parameters[$match])) {
                        $parameter = new Parameter;
                        $parameter->setName($match);

                        $this->addParameter($parameter);
                    } else {
                        throw new BerliozException(sprintf('Parameter "%s" is declared more than one time', $match));
                    }
                }
            }

            // Options
            $this->route_options = [];
            if (!empty($matches['options'])) {
                $matchesOptions = [];
                if (preg_match_all('~' . $regex_define . '\s* , \s* (?<name> [\w_]+) \s* = \s* (?: (?<json> \g<d_json> ) | (?<bool> \g<d_bool> ) | (?<string> \g<d_quotes> ) ) \s* ~x', $matches['options'], $matchesOptions, PREG_SET_ORDER)) {
                    foreach ($matchesOptions as $matchOption) {
                        $optionName = $matchOption['name'];

                        if (!empty($matchOption['json'])) {
                            $optionValue = json_decode(addcslashes($matchOption['json'], '\\'), true);

                            if ($optionValue !== false) {
                                if (!isset($this->route_options[$optionName])) {
                                    $this->route_options[$optionName] = $optionValue;
                                } else {
                                    $this->route_options[$optionName] = array_merge($this->route_options[$optionName], $optionValue);
                                }
                            } else {
                                throw new BerliozException('Parse error of @route tag');
                            }
                        } else {
                            if (!empty($matchOption['bool'])) {
                                $this->route_options[$optionName] = $matchOption['bool'] == true;
                            } else {
                                if (!empty($matchOption['string'])) {
                                    $this->route_options[$optionName] = substr($matchOption['string'], 1, -1);
                                }
                            }
                        }
                    }
                }

                // Options: default values
                if (isset($this->route_options['defaults'])) {
                    foreach ($this->route_options['defaults'] as $key => $value) {
                        if (!is_null($this->getParameter($key))) {
                            $this->getParameter($key)->setHasDefaultValue(true);
                            $this->getParameter($key)->setDefaultValue($value);
                        }
                    }
                    unset($this->route_options['defaults']);
                }

                // Options: route requirements
                if (isset($this->route_options['requirements'])) {
                    foreach ($this->route_options['requirements'] as $key => $value) {
                        if (!is_null($this->getParameter($key))) {
                            if (preg_match('/^' . $value . '$/', null) !== false) {
                                $this->getParameter($key)->setRegexValidation($value);
                            } else {
                                throw new BerliozException(sprintf('Parameter "%s", requirement is not valid regex', $key));
                            }
                        }
                    }
                    unset($this->route_options['requirements']);
                }
            }
        } else {
            throw new BerliozException('Parse error of @route tag');
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getNumberOfParameters(): int
    {
        return count($this->parameters);
    }

    /**
     * @inheritdoc
     */
    public function getRouteRegex(): string
    {
        if (is_null($this->route_regex)) {
            $route = $this;
            $this->route_regex =
                '~^' .
                preg_replace_callback(
                    self::REGEX_PARAMETER,
                    function ($match) use ($route) {
                        if (!is_null($parameter = $route->getParameter($match['name']))) {
                            return '(?<' . $match['name'] . '>' . $parameter->getRegexValidation() . ')';
                        } else {
                            throw new BerliozException(sprintf('Parameter "%s" not found in route', $match['name']));
                        }
                    },
                    $this->route) .
                '$~i';
        }

        return $this->route_regex;
    }

    /**
     * @inheritdoc
     */
    public function getInvoke(): array
    {
        return $this->invoke;
    }

    /**
     * @inheritdoc
     */
    public function setInvoke(string $controllerClass, string $methodName): RouteInterface
    {
        if (class_exists($controllerClass)) {
            if (is_subclass_of($controllerClass, '\Berlioz\Core\Controller\Controller')) {
                if (method_exists($controllerClass, $methodName)) {
                    $this->invoke = [$controllerClass, $methodName];
                } else {
                    throw new BerliozException(sprintf('Method "%s::%s" does not exists', $controllerClass, $methodName));
                }
            } else {
                throw new BerliozException(sprintf('Class "%s" must be a sub class of "\Berlioz\Core\Controller\Controller"', $controllerClass));
            }
        } else {
            throw new BerliozException(sprintf('Class "%s" does not exists', $controllerClass));
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getParameter(string $name): ?Parameter
    {
        if (isset($this->parameters[$name])) {
            return $this->parameters[$name];
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function addParameter(Parameter $parameter): RouteInterface
    {
        $this->parameters[$parameter->getName()] = $parameter;

        // Reinitialization of route regex
        $this->route_regex = null;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getSummary(): ?string
    {
        return $this->summary;
    }

    /**
     * @inheritdoc
     */
    public function setSummary(string $summary): RouteInterface
    {
        $this->summary = $summary;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @inheritdoc
     */
    public function setDescription(string $description): RouteInterface
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function noAuthentication(): bool
    {
        return !(!isset($this->route_options['authentication']) || $this->route_options['authentication'] === false);
    }

    /**
     * @inheritdoc
     */
    public function test(string $test): bool
    {
        return preg_match($this->getRouteRegex(), $test) == 1;
    }

    /**
     * @inheritdoc
     */
    public function extractParameters(string $path): array
    {
        $parameters = [];

        $matches = [];
        if (preg_match($this->getRouteRegex(), $path, $matches) == 1) {
            $parameters = array_filter($matches, 'is_string', \ARRAY_FILTER_USE_KEY);
        }

        foreach ($this->parameters as $parameter) {
            if (!isset($parameters[$parameter->getName()])) {
                if ($parameter->hasDefaultValue()) {
                    $parameters[$parameter->getName()] = $parameter->getDefaultValue();
                }
            }
        }

        return $parameters;
    }

    /**
     * @inheritdoc
     */
    public function generate(array $parameters)
    {
        $route = $this->getRoute();

        $parametersFound = [];
        foreach ($this->parameters as $parameter) {
            if (!empty($parameters[$parameter->getName()])) {
                $value = (string) $parameters[$parameter->getName()];
            } else {
                if ($parameter->hasDefaultValue()) {
                    $value = (string) $parameter->getDefaultValue();
                } else {
                    return false;
                }
            }

            $route = str_replace('{' . $parameter->getName() . '}', $value, $route);
            $parametersFound[] = $parameter->getName();
        }

        // Not found parameters
        $getParameters = [];
        foreach ($parameters as $parameterName => $parameterValue) {
            if (!in_array($parameterName, $parametersFound)) {
                $getParameters[$parameterName] = $parameterValue;
            }
        }

        // Construct query string
        if (!empty($getParameters)) {
            array_walk_recursive(
                $getParameters,
                function (&$value) {
                    $value = (string) $value;
                });
            $httpBuildQuery = http_build_query($getParameters);

            if (!empty($httpBuildQuery)) {
                $route .= '?' . $httpBuildQuery;
            }
        }

        return $route;
    }

    /**
     * @inheritdoc
     */
    public function withParametersValues(array $values): RouteInterface
    {
        $clone = clone $this;
        $clone->parameters_values = $values;

        return $clone;
    }

    /**
     * @inheritdoc
     */
    public function getParametersValues(): array
    {
        return $this->parameters_values;
    }
}