Config
======

A config class that you can extends if you want is available. This class read JSON format, and you can search values with path like : `myconfig.value.value2`.

You can't edit config values with this class, read only.

**To prevent loops in your code, in your configuration class, do not interact with application or services before instanced App class.**

## Extends ##

You can extends `\Berlioz\Core\Config` class to add some usual functions.
Berlioz framework accept your own configuration class, for that, implements `\Berlioz\Core\ConfigInterface` interface.

## Example ###

### In PHP file ###

```php
$config = new \Berlioz\Core\Config('/root-path', '/relative-path-of-config-file.json');
$value = $config->get('myconfig.value.value2');
```

### Config file ###

```json
{
  "var": {
    "var1": "value1",
    "var2": "value2",
    "var3": "value3"
  }
}
```

### Extends config file ###

```json
{
  "@extends": "config-to-extends.json",
  "var": {
    "var1": "value1",
    "var2": "value2",
    "var3": "value3"
  }
}
```