Application
===========

It's a class whose control application and register controllers into router.

## Profile ##

Profile of application is available with `app` variable name into template engine.
It's a class instanced into App class.

Access to the variables :

* `app.config`: access to config object
* `app.flashBag`: list of flash messages
* `app.locale`: current locale of application (default from PHP value `\Locale::getDefault()`)
* `app.route`: current route object

## Events ##

Some events are available in events service :

* `_berlioz.core.app.handle.before`: before handle of application
* `_berlioz.core.app.handle.after`: after handle of application

## Example ##

```php
class App extends \Berlioz\Core\App
{
    /**
     * Register controllers in router.
     */
    public function register()
    {
        // Controllers
        $this->getRouter()->registerController('\Website\MyController');
        $this->getRouter()->registerController('\Website\MySecondController', '/path');

        // Exceptions controllers
        $this->getRouter()->addExceptionController('\Website\MyExceptionController');
        $this->getRouter()->addExceptionController('\Website\MySecondExceptionController', '/path');
    }

    /**
     * Handle application.
     */
    public function handle()
    {
        // Handle
        parent::handle();
    }
}
```