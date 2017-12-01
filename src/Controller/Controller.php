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

namespace Berlioz\Core\Controller;


use Berlioz\Core\App;
use Berlioz\Core\App\AppAwareTrait;
use Berlioz\Core\Http\Response;
use Berlioz\Core\Http\ServerRequest;
use Berlioz\Core\Http\Stream;
use Berlioz\Core\Services\Routing\RouteInterface;
use Berlioz\Core\Services\Routing\RouterInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Controller class, it's the parent controller class.
 *
 * It's needed to inherit all controller to it.
 *
 * @package Berlioz\Core\Controller
 * @see     \Berlioz\Core\Controller\ControllerInterface
 */
abstract class Controller implements ControllerInterface
{
    use AppAwareTrait;

    /**
     * @inheritdoc
     */
    public function __construct(App $app)
    {
        $this->setApp($app);
    }

    /**
     * Controller destructor.
     */
    public function __destruct()
    {
    }

    /**
     * __sleep() magic method.
     *
     * @return mixed[]
     */
    public function __sleep(): array
    {
        return [];
    }

    /**
     * __wakeup() magic method.
     */
    public function __wakeup(): void
    {
    }

    /**
     * Get router.
     *
     * @return \Berlioz\Core\Services\Routing\RouterInterface|null
     * @throws \Berlioz\Core\Exception\RuntimeException if application not accessible
     */
    final public function getRouter(): ?RouterInterface
    {
        if ($this->hasApp()) {
            return $this->getApp()->getService('routing');
        } else {
            return null;
        }
    }

    /**
     * @inheritdoc
     */
    public function _b_authentication(ServerRequest $request)
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function _b_init(ServerRequest $request): void
    {
    }

    /**
     * Get the Route object of the current path.
     *
     * @return \Berlioz\Core\Services\Routing\RouteInterface|null
     * @throws \Berlioz\Core\Exception\RuntimeException if application not accessible
     */
    public function getRoute(): ?RouteInterface
    {
        if (!is_null($this->getApp())) {
            return $this->getApp()->getService('routing')->getCurrentRoute();
        }

        return null;
    }

    /**
     * Redirection to a specific URL.
     *
     * @param string $url              URL of redirection
     * @param int    $httpResponseCode HTTP Redirection code (301, 302...)
     *
     * @return void
     */
    protected function redirect(string $url, int $httpResponseCode = 302): void
    {
        header('Location: ' . $url, true, $httpResponseCode);
        exit;
    }

    /**
     * Reload current page.
     *
     * @param string[] $get   Additional parameters for GET query string
     * @param bool     $merge Merge parameters
     *
     * @return void
     * @uses Controller::redirect()
     */
    protected function reload(array $get = [], bool $merge = false): void
    {
        $path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

        // Query
        {
            $query = [];

            if ($merge) {
                parse_str(parse_url($_SERVER["REQUEST_URI"], PHP_URL_QUERY), $query);
            }

            $query = array_merge($query, $get);
            $querystring = http_build_query($query);
        }

        $this->redirect($path . (!empty($querystring) ? '?' . $querystring : ''));
    }

    /**
     * Add new message in flash bag.
     *
     * @param string $type    Type of message
     * @param string $message Message
     *
     * @return void
     * @throws \Berlioz\Core\Exception\RuntimeException if application not accessible
     * @see \Berlioz\Core\Services\FlashBag FlashBag class whose manage all flash messages
     */
    protected function addFlash(string $type, string $message): void
    {
        $this->getApp()->getService('flashbag')->add($type, $message);
    }

    /**
     * Do render of templates.
     *
     * @param string  $name      Filename of template
     * @param mixed[] $variables Variables for template
     *
     * @return string Output content
     * @throws \Berlioz\Core\Exception\RuntimeException if application not accessible
     * @see \Berlioz\Core\Services\Template\TemplateInterface
     */
    protected function render(string $name, array $variables = []): string
    {
        $templateEngine = $this->getApp()->getService('templating');

        return $templateEngine->render($name, $variables);
    }

    /**
     * Do render of templates in Response object.
     *
     * @param string                                   $name      Filename of template
     * @param mixed[]                                  $variables Variables for template
     * @param \Psr\Http\Message\ResponseInterface|null $response  Response object to complete
     *
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Berlioz\Core\Exception\RuntimeException if application not accessible
     */
    protected function renderResponse(string $name, array $variables = [], ResponseInterface $response = null): ResponseInterface
    {
        // Create new Response object if not given in parameter
        if (is_null($response)) {
            $response = new Response;
        }

        // Remove all headers
        header_remove();

        // Rendering
        $rendering = $this->render($name, $variables);

        // Get all headers defined in template engine and add to response
        foreach (headers_list() as $header) {
            $header = explode(':', $header, 2);
            $response = $response->withAddedHeader($header[0], $header[1] ?? '');
        }

        // Get body of response
        if ($response->getBody()->isWritable()) {
            $response->getBody()->write($rendering);
        } else {
            $body = new Stream;
            $body->write($rendering);

            $response = $response->withBody($body);
        }

        return $response;
    }
}
