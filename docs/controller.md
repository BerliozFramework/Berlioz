Controller
==========

It's a class whose control interactions between models, services and templating.
The controller is instanced and the matched method is called, by router service when the application start.

## Class ##

* **\Berlioz\Core\Controller\ControllerInterface**: basic interface used by others controllers and necessary for system.
* **\Berlioz\Core\Controller\Controller**: main controller.
* **\Berlioz\Core\Controller\ExceptionControllerInterface**: basic interface used by exception controllers and necessary for system.
* **\Berlioz\Core\Controller\ExceptionController**: exception controller.
* **\Berlioz\Core\Controller\RestController**: main controller for *REST* applications, for example, implements some useful methods for response.

## Magic methods ##

In order of calling:
1. **_b_authentication()**: called to process the authentication validation, this method must be return a *boolean* or *\Psr\Http\Message\ResponseInterface* object.
2. **_b_init()**: called to init your controllers, like instance some objects...

## Example ###

```php
class MyController extends \Berlioz\Core\Controller\Controller
{
    /**
     * @route( "/my-route/{parameter}" )
     */
    public function myMethod(\Berlioz\Core\Http\ServerRequest $request, \Berlioz\Core\Http\Response $response)
    {
    }
}
```

## Parameters ##

2 parameters are needed for controllers.

* `\Berlioz\Core\Http\ServerRequest $request`: the server request.
* `\Berlioz\Core\Http\Response $response`: the response that you need to complete and returns in controller method.

You can access to the route parameters with method : `$request->getAttributes()` or `$request->getAttribute('param1')`.