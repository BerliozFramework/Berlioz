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

namespace Berlioz\Core\Entity;


use Berlioz\Core\Http\ServerRequest;
use Berlioz\Core\OptionList;

/**
 * Pagination class to manage pagination.
 *
 * Can be used with Collection objects or arrays.
 *
 * @package Berlioz\Core
 */
class Pagination
{
    /** @var int Number of Pagination instance */
    private static $iPagination = 0;
    /** @var \Berlioz\Core\Http\ServerRequest Server request */
    private $serverRequest;
    /** @var int Current page */
    private $page;
    /** @var int Number of pages */
    private $nb_pages;
    /** @var int Number of item per page */
    private $nb_per_page;
    /** @var \Berlioz\Core\Entity\Collection|int $mixed Collection with nbTotal property completed or integer */
    private $mixed;
    /** @var OptionList Options of Pagination */
    protected $options;

    /**
     * Pagination constructor.
     *
     * @param \Berlioz\Core\Http\ServerRequest $serverRequest Server request
     * @param OptionList                       $options       {
     *
     * @var string                             $method        HTTP method for parameter transmission between
     *      pages
     * @var string                             $param         Name of GET or POST parameter
     * @var int                                $nb_per_page   Number of elements per page
     * }
     */
    public function __construct(ServerRequest $serverRequest, OptionList $options = null)
    {
        $this->serverRequest = $serverRequest;

        if (is_null($options)) {
            $this->options = new OptionList;
        } else {
            $this->options = $options;
        }

        self::$iPagination++;
    }

    /**
     * Pagination destructor.
     */
    public function __destruct()
    {
    }

    /**
     * Get the current page of pagination navigation.
     *
     * @return int|null Current page
     */
    public function getCurrentPage(): ?int
    {
        return $this->page;
    }

    /**
     * Get the previous page of pagination navigation.
     *
     * @return int|null Previous page
     */
    public function getPreviousPage(): ?int
    {
        return max($this->page - 1, 1);
    }

    /**
     * Get the next page of pagination navigation.
     *
     * @return int|null Next page
     */
    public function getNextPage(): ?int
    {
        return min($this->page + 1, $this->nb_pages);
    }

    /**
     * Get the number of pages in pagination navigation.
     *
     * @return int|null Number of pages
     */
    public function getNbPages(): ?int
    {
        return $this->nb_pages;
    }

    /**
     * Get the number of elements per page.
     *
     * @return int|null
     */
    public function getNbPerPage(): int
    {
        return $this->nb_per_page;
    }

    /**
     * Prepare the OptionList for Collection.
     *
     * This method complete OptionList for Collection with some
     * _b_options for SQL query like limitStart, limitEnd and limitNb.
     * This _b_options must be implemented in DB queries in Collection
     * get methods.
     *
     * @param  OptionList $objOptions OptionList object with _b_options for Collection object
     *
     * @return OptionList OptionList given in param completed
     */
    public function prepare(OptionList $objOptions = null): OptionList
    {
        if (is_null($objOptions)) {
            $objOptions = new OptionList;
        }

        // Page
        if ('post' == $this->options->get('method')) {
            $requestBody = $this->serverRequest->getParsedBody();

            if (is_array($requestBody) && isset($requestBody[$this->getParam()])) {
                $this->page = intval($requestBody[$this->getParam()]);
            } else {
                $this->page = 1;
            }
        } else {
            $queryParams = $this->serverRequest->getQueryParams();

            if (is_array($queryParams) && isset($queryParams[$this->getParam()])) {
                $this->page = intval($queryParams[$this->getParam()]);
            } else {
                $this->page = 1;
            }
        }

        // Nb per page
        $this->nb_per_page = $this->options->is_int('nb_per_page') ? $this->options->get('nb_per_page') : 20;

        // Obj _b_options
        $objOptions->set('limitStart', $this->nb_per_page * ($this->page - 1));
        $objOptions->set('limitEnd', $objOptions->get('limitStart') + $this->nb_per_page);
        $objOptions->set('limitNb', $this->nb_per_page);

        return $objOptions;
    }

    /**
     * Called to complete the Pagination object with the number of elements.
     *
     * Need to call this method after to get elements with Collection.
     * If no Collection method used, need to pass an integer with value of number
     * of elements.
     *
     * @param  \Berlioz\Core\Entity\Collection|\Countable|int $mixed Collection with nbTotal property completed or
     *                                                               integer
     *
     * @return static
     */
    public function handle($mixed): Pagination
    {
        if ($mixed instanceof Collection || is_int($mixed)) {
            $this->mixed = $mixed;
            $this->nb_pages = ceil(max($mixed->nbTotal, 1) / max($this->nb_per_page, 1));
        } else {
            if ($mixed instanceof \Countable) {
                $this->mixed = $mixed;
                $this->nb_pages = ceil(max(count($mixed), 1) / max($this->nb_per_page, 1));
            } else {
                if (is_int($mixed)) {
                    $this->mixed = $mixed;
                    $this->nb_pages = ceil(max($mixed, 1) / max($this->nb_per_page, 1));
                } else {
                    trigger_error('Parameter of Pagination::handle must be an Collection object or integer', E_USER_WARNING);
                }
            }
        }

        return $this;
    }

    /**
     * If Pagination can be showed.
     *
     * @return bool
     */
    public function canBeShowed(): bool
    {
        return ($this->mixed instanceof Collection || $this->mixed instanceof \Countable || is_int($this->mixed));
    }

    /**
     * Get the GET or POST parameter name.
     *
     * @return string
     */
    public function getParam(): string
    {
        if ($this->options->is_null('param')) {
            $this->options->set('param', 'page' . (1 == self::$iPagination ? '' : self::$iPagination));
        }

        return $this->options->get('param');
    }

    /**
     * Get query string part of URL for navigation between pages.
     *
     * @param array $moreQuery Additional query string parameters
     *
     * @return string Query string part of URL
     */
    public function getHttpQueryString(array $moreQuery = null): string
    {
        $queries = [];

        if (!(is_null($this->page) || 1 == $this->page)) {
            $queries[$this->getParam()] = $this->page;
        }

        if (!empty($moreQuery)) {
            $queries = array_merge($queries, $moreQuery);
        }

        return !empty($queries) ? '?' . http_build_query($queries) : '';
    }
}
