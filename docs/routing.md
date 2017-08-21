Routing
=======

The router service is here to find the good controller method in terms of the URI of request.
The service contains a set of routes (RouteSet and Route classes), and when the service find the good controller, he found before that a Route object whose give it the controller/method to invoke.

## Extends ##

Berlioz framework accept your own router service or custom set of routes..., for that, implements `RouterInterface`, `RouteSetInterface` and `RouteInterface` interfaces that's find in `\Berlioz\Core\Services\Routing` namespace.

For example, you can search and get routes from database in replacing `RouteSet` class.

## Declaration of routes ##

By default, the router service checks annotations in controllers to find routes.

Example of declaration of route:

```php
/**
 * @route("/path-example", name="RouteName")
 */
``` 

Like you see, it's possible to give name to a route.

## Parameters ##

It's possible to add parameters in routes.

```php
/**
 * @route("/path-example/{param}", name="RouteName")
 */
``` 

To know how get value of parameters in your methods, check [Controllers](./controller.md) documentation.

### Requirements for parameters ###

It's possible to restrict parameter pattern.
For that, add `requirements` option to route declaration and in value the list of parameter with regex in JSON format.

Example:

```php
/**
 * @route("/path-example/{param}", requirements={"param": "[0-9]+"})
 */
```

In this example, the parameter named `param` has constraint to respect regex `[0-9]+`.