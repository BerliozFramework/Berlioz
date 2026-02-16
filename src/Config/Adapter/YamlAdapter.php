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

namespace Berlioz\Config\Adapter;

use Berlioz\Config\Exception\ConfigException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Class YamlAdapter.
 */
class YamlAdapter extends AbstractFileAdapter
{
    public const PARSER_AUTO = 3;
    public const PARSER_EXTENSION = 1;
    public const PARSER_SYMFONY = 2;

    public function __construct(
        string $str,
        bool $strIsUrl = false,
        int $priority = 0,
        protected array $yamlExt = [],
        protected int $forceParser = YamlAdapter::PARSER_AUTO,
    ) {
        $default = ['pos' => 0, 'ndocs' => null, 'callbacks' => []];
        $this->yamlExt = array_replace($default, $this->yamlExt);
        $this->yamlExt = array_intersect_key($this->yamlExt, $default);

        parent::__construct($str, $strIsUrl, $priority);
    }

    /**
     * @inheritDoc
     */
    protected function load(string $str, bool $strIsUrl = false): array
    {
        if (($this->forceParser & static::PARSER_EXTENSION) === static::PARSER_EXTENSION) {
            if ($this->forceParser === static::PARSER_EXTENSION || true === extension_loaded('yaml')) {
                return $this->loadWithExtension($str, $strIsUrl);
            }
        }

        if (($this->forceParser & static::PARSER_SYMFONY) === static::PARSER_SYMFONY) {
            return $this->loadWithSymfony($str, $strIsUrl);
        }

        throw new ConfigException('Needs extension "ext-yaml" or "symfony/yaml" library to use YAML adapter');
    }

    /**
     * Load with YAML extension.
     *
     * @param string $str
     * @param bool $strIsUrl
     *
     * @return array
     * @throws ConfigException
     */
    protected function loadWithExtension(string $str, bool $strIsUrl = false): array
    {
        if (false === extension_loaded('yaml')) {
            throw new ConfigException('Needs extension "ext-yaml" to use YAML adapter');
        }

        if (true === $strIsUrl) {
            return $this->assertResult(@yaml_parse_file($str, ...$this->yamlExt), 'Not a valid YAML file');
        }


        return $this->assertResult(@yaml_parse($str, ...$this->yamlExt));
    }

    /**
     * Load with Symfony library.
     *
     * @param string $str
     * @param bool $strIsUrl
     *
     * @return array
     * @throws ConfigException
     */
    protected function loadWithSymfony(string $str, bool $strIsUrl = false): array
    {
        if (false === class_exists(Yaml::class)) {
            throw new ConfigException('Needs library "symfony/yaml" to use YAML adapter');
        }

        try {
            if (true === $strIsUrl) {
                return $this->assertResult(Yaml::parseFile($str), 'Not a valid YAML file');
            }


            return $this->assertResult(Yaml::parse($str));
        } catch (ParseException $exception) {
            throw new ConfigException('Not a valid YAML contents', previous: $exception);
        }
    }

    /**
     * Assert result.
     *
     * @param mixed $result
     * @param string|null $message
     *
     * @return array
     * @throws ConfigException
     */
    private function assertResult(mixed $result, ?string $message = null): array
    {
        if (!is_array($result)) {
            throw new ConfigException($message ?? 'Not a valid YAML contents');
        }

        return $result;
    }
}