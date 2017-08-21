Templating
==========

**Berlioz** integrate a templating service but subtract management to another lib.
We use **Twig** from **SensioLab**.

Check official documentation on [Twig website](https://twig.sensiolabs.org).

## Declaration of paths ##

You can declare paths in Twig system with method `registerPath(string $path, string $namespace = null)` in templating service.

Example in PHP:

```php
$app->getService('templating')->registerPath('/path-of/my-template', 'myBeautifulTemplate');
```

Example in Twig:

```twig
{{ include('@myBeautifulTemplate/path/template.twig') }}
```

## Render in controllers ##

To do rendering of a template:

```php
class MyController extends \Berlioz\Core\Controller\Controller
{
    /**
     * @route( "/my-route/{parameter}" )
     */
    public function myMethod(\Berlioz\Core\Http\ServerRequest $request, \Berlioz\Core\Http\Response $response)
    {
        return $this->render('@myBeautifulTemplate/path/template.twig',
                             ['myVar1' => 'Value1',
                              'myVar2' => 'Value2']);
    }
}
```

You can also do rendering of a specific block in a template:

```php
class MyController extends \Berlioz\Core\Controller\Controller
{
    /**
     * @route( "/my-route/{parameter}" )
     */
    public function myMethod(\Berlioz\Core\Http\ServerRequest $request, \Berlioz\Core\Http\Response $response)
    {
        $myContent =
            $this->getApp()
                 ->getService('templating')
                 ->renderBlock('@myBeautifulTemplate/path/template.twig',
                               'MyBlock',
                               ['myVar1' => 'Value1',
                                'myVar2' => 'Value2']);

        // Do anything else...
    }
}
```