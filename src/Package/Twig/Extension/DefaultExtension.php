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

namespace Berlioz\Package\Twig\Extension;

use Berlioz\Core\Core;
use DateTime;
use DateTimeInterface;
use IntlDateFormatter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigTest;

class DefaultExtension extends AbstractExtension
{
    /**
     * TwigExtension constructor.
     *
     * @param Core $core
     */
    public function __construct(protected Core $core)
    {
    }

    /**
     * Returns a list of filters to add to the existing list.
     *
     * @return TwigFilter[]
     */
    public function getFilters(): array
    {
        $filters = [];
        $filters[] = new TwigFilter('date_format', [$this, 'filterDateFormat']);
        $filters[] = new TwigFilter('truncate', 'b_str_truncate');
        $filters[] = new TwigFilter('nl2p', 'b_nl2p', ['is_safe' => ['html']]);
        $filters[] = new TwigFilter('human_file_size', 'b_human_file_size');
        $filters[] = new TwigFilter('json_decode', 'json_decode');
        $filters[] = new TwigFilter('basename', 'basename');

        return $filters;
    }

    /**
     * Returns a list of tests to add to the existing list.
     *
     * @return TwigTest[]
     */
    public function getTests(): array
    {
        return [
            new TwigTest('instance of', [$this, 'testInstanceOf'])
        ];
    }

    /**
     * Filter to format date.
     *
     * @param DateTime|string|int $datetime DateTime object or timestamp
     * @param string $pattern Pattern of date result waiting
     * @param string|null $locale Locale for pattern translation
     *
     * @return string
     */
    public function filterDateFormat(
        DateTime|string|int $datetime,
        string $pattern = 'dd/MM/yyyy',
        ?string $locale = null
    ): string {
        $fmt = new IntlDateFormatter(
            $locale ?? $this->core->getLocale(),
            IntlDateFormatter::FULL,
            IntlDateFormatter::FULL
        );
        $fmt->setPattern($pattern);

        if ($datetime instanceof DateTimeInterface) {
            $fmt->setTimeZone($datetime->getTimezone());

            return $fmt->format($datetime);
        }

        if (is_numeric($datetime)) {
            return $fmt->format((int)$datetime);
        }

        if (is_string($datetime)) {
            $result = $fmt->format(strtotime($datetime));

            if ($result) {
                return $result;
            }
        }

        return '';
    }

    /**
     * Test instance of.
     *
     * @param object|string $object The tested object
     * @param string $class_name The class name
     *
     * @return bool
     */
    public function testInstanceOf(object|string $object, string $class_name): bool
    {
        return is_a($object, $class_name, true);
    }
}
