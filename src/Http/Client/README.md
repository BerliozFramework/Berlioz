# Berlioz HTTP Client

[![Latest Version](https://img.shields.io/packagist/v/berlioz/http-client.svg?style=flat-square)](https://github.com/BerliozFramework/HttpClient/releases)
![Packagist Dependency Version](https://img.shields.io/packagist/dependency-v/BerliozFramework/HttpClient/php?version=3.x-dev&style=flat-square)
[![Software license](https://img.shields.io/github/license/BerliozFramework/HttpClient.svg?style=flat-square)](https://github.com/BerliozFramework/HttpClient/blob/3.x/LICENSE)

> **Note**
>
> This repository is a **read-only split** from
> the [main Berlioz Framework repository](https://github.com/BerliozFramework/Berlioz).
>
> For contributions, issues, or more information, please visit
> the [main Berlioz Framework repository](https://github.com/BerliozFramework/Berlioz).
>
> **Do not open issues or pull requests here.**

---

**Berlioz HTTP Client** is a PHP library to request HTTP server with continuous navigation, including cookies,
sessions... Implements PSR-18 (HTTP Client), PSR-7 (HTTP message interfaces) and PSR-17 (HTTP Factories) standards.

📖 **[Full documentation](https://getberlioz.com/docs/3.x/components/http-client)**

## Installation

You can install **Berlioz HTTP Client** with [Composer](https://getcomposer.org/), it's the recommended installation.

```shell
$ composer require berlioz/http-client
```

## Documentation

For usage and examples, visit the
[official documentation on **getberlioz.com**](https://getberlioz.com/docs/3.x/components/http-client).

## Redirect security

The client follows responses with a `Location` header for status codes 201, 301, 302, 303, 307 and 308 by default.
Set `followLocation` to `false` to return the response without following it.

### Origins and credentials

Redirect destinations are resolved against the current request URI before comparing origins. Two HTTP URIs have
the same origin when their scheme, case-insensitive host and effective port match. An omitted port is equivalent
to 80 for HTTP or 443 for HTTPS. A different subdomain, port or scheme is a different origin.

On the first cross-origin redirect, the client removes these headers from the options used by the redirect chain:

- `Authorization`
- `Proxy-Authorization`
- Manually supplied `Cookie`
- Application-specific headers listed in `redirectSensitiveHeaders`

Header names are compared case-insensitively. Removed credentials stay removed for the rest of that call, including
network retries and redirects back to the initial origin. Client defaults and caller-supplied options are preserved;
a subsequent independent call uses its normal credentials again.

Credentials supplied in a redirect's `Location` URI are removed. Credentials inherited from the current URI may be
retained for a same-origin relative redirect, but are removed after any origin change. Redirect URI fragments are
also removed.

### Application-specific secrets

Declare custom authentication headers explicitly; their names cannot be inferred from their values:

```php
use Berlioz\Http\Client\Client;

$client = new Client([
    'headers' => [
        'Authorization' => 'Bearer example-token',
        'X-Api-Key' => 'example-api-key',
    ],
    'redirectSensitiveHeaders' => ['X-Api-Key'],
]);

$response = $client->get('https://api.example.test/resource', options: [
    'headers' => ['X-Access-Token' => 'example-access-token'],
    'redirectSensitiveHeaders' => ['X-Access-Token'],
]);
```

In this example, all three authentication headers are removed if the request is redirected to another origin.
The additional names in array options are merged with the client's list, normalized and deduplicated; an empty
list does not clear the inherited list. The three mandatory headers are always filtered, even with no custom list.
As with other client options, passing an `Options` object supplies a complete options object rather than merging
it with client defaults. Include all applicable custom sensitive headers in that object.

Headers configured through options or default-header setters are reapplied on same-origin redirects. Headers set
only on the original PSR-7 request are not automatically copied when a redirect request is rebuilt.

### Referer and cookies

For each followed redirect, the client replaces any configured `Referer` with a value based on the previous URI:

| Redirect | Generated `Referer` |
| --- | --- |
| Same origin | Previous URI without user information or fragment; path and query are retained |
| Different origin | Previous origin only, without user information, path or query |
| HTTPS to HTTP | No header |

This follows `strict-origin-when-cross-origin` semantics. A suppressed default `Referer` is not reapplied on the
next iteration.

Managed cookies are selected again for each destination using the cookie manager's domain, path, expiration and
Secure rules. They are distinct from manually supplied `Cookie` headers. Setting `cookies` to `false` disables
automatic cookie sending and collection; a manually supplied header still follows the credential policy above.

### Request bodies

For 307 and 308, the client preserves the method, body and content type, including across origins. Other followed
statuses rebuild the request as GET without the original body. Content length is recalculated for each redirect.
The credential policy filters headers and URI credentials; it does not inspect or redact request bodies. To handle
body replay decisions yourself, disable automatic redirects with `followLocation: false` in an `Options` object,
or `'followLocation' => false` in array options.
