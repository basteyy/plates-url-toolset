# Plates URL Toolset

[![Tests](https://github.com/basteyy/plates-url-toolset/actions/workflows/tests.yml/badge.svg)](https://github.com/basteyy/plates-url-toolset/actions/workflows/tests.yml)
![PHP](https://img.shields.io/badge/php-8.2%20%7C%208.3%20%7C%208.4%20%7C%208.5-777bb4)
![License](https://img.shields.io/badge/license-MIT-green)

An extension for the [Plates](https://platesphp.com/) template engine that adds URL helpers to your templates:
absolute URLs, safely escaped HTML links, named URLs (simple routing aliases), the current URL and cache-busting
URLs for assets.

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Setup](#setup)
  - [Constructor options](#constructor-options)
  - [Base URL and host detection](#base-url-and-host-detection)
- [Template functions](#template-functions)
  - [getAbsoluteUrl](#getabsoluteurl)
  - [getLink](#getlink)
  - [addNamedUrl / getNamedUrl](#addnamedurl--getnamedurl)
  - [getNamedLink](#getnamedlink)
  - [getCurrentUrl / getCurrentUrlWithoutQuery](#getcurrenturl--getcurrenturlwithoutquery)
  - [getDebugUrl](#getdebugurl)
  - [getCurrentFqdn](#getcurrentfqdn)
- [Security](#security)
- [Upgrading from 0.0.x](#upgrading-from-00x)
- [Development](#development)
- [License](#license)

## Requirements

| Version | PHP                       | Plates |
|---------|---------------------------|--------|
| 1.x     | 8.2, 8.3, 8.4, 8.5        | ^3.6   |
| 0.0.x   | 8.0 – 8.3 (unmaintained)  | ^3.5   |

## Installation

```bash
composer require basteyy/plates-url-toolset
```

## Setup

Load the extension into your Plates engine:

```php
use basteyy\PlatesUrlToolset\PlatesUrlToolset;
use League\Plates\Engine;

$engine = new Engine(__DIR__ . '/templates');
$engine->loadExtension(new PlatesUrlToolset('https://example.org'));
```

### Constructor options

```php
new PlatesUrlToolset(
    baseUrl: 'https://example.org',   // string|null – see below
    defaultAbsoluteUrl: false,        // bool – return absolute URLs by default
    namedLinks: [                     // array<string, string> – named URLs
        'home'    => '/',
        'profile' => '/user/%d',
    ],
    trustProxyHeaders: true,          // bool – respect X-Forwarded-Proto / Front-End-Https
);
```

| Option               | Default | Description |
|----------------------|---------|-------------|
| `baseUrl`            | `null`  | Base for absolute URLs, e.g. `https://example.org`, `example.org` or `https://example.org:8443/app`. `null` detects the host from the request. |
| `defaultAbsoluteUrl` | `false` | When a function is called without an explicit `$getAbsoluteUrl` argument, this value decides whether an absolute URL is returned. |
| `namedLinks`         | `[]`    | Named URLs which can be used via `getNamedUrl()` and `getNamedLink()`. |
| `trustProxyHeaders`  | `true`  | Detect https via the `X-Forwarded-Proto` and `Front-End-Https` headers. Disable it if your application is **not** behind a reverse proxy. |

The constructor throws an `InvalidArgumentException` when the base URL has no valid host or uses a protocol other
than `http` / `https`.

### Base URL and host detection

| `baseUrl`                         | Protocol                  | Host                       |
|-----------------------------------|---------------------------|----------------------------|
| `https://example.org`             | `https`                   | `example.org`              |
| `example.org`                     | detected per request      | `example.org`              |
| `https://example.org:8443/app/`   | `https`                   | `example.org:8443/app`     |
| `null`                            | detected per request      | detected per request       |

When the host is detected, the `Host` header is validated. Invalid values are ignored and the extension falls back
to `SERVER_NAME` and finally to `localhost`. **For production, always configure `baseUrl`** – see [Security](#security).

## Template functions

All examples assume `https://example.org` as the base URL and `/foobar?page=2` as the current request.

Every function that accepts `$getAbsoluteUrl` follows the same rule: `true` returns an absolute URL, `false` a
relative one and `null` (the default) uses the `defaultAbsoluteUrl` option.

### getAbsoluteUrl

```php
getAbsoluteUrl(string $url, mixed ...$args): string
```

Prefixes the URL with protocol and base URL. Additional arguments are inserted via `sprintf()`.

```php
<?= $this->e($this->getAbsoluteUrl('/contact')) ?>
<?= $this->e($this->getAbsoluteUrl('/user/%d/%s', 5, 'edit')) ?>
```

```text
https://example.org/contact
https://example.org/user/5/edit
```

The URL is always placed on the configured host – `getAbsoluteUrl('https://evil.com')` results in
`https://example.org/https://evil.com`, never in a link to another domain.

### getLink

```php
getLink(
    string $url,
    ?string $value = null,       // link text, defaults to the URL
    ?string $title = null,       // title attribute, defaults to the URL
    ?string $classlist = null,   // class attribute
    ?bool $getAbsoluteUrl = null,
    bool $escapeValue = true,    // false allows HTML inside the link text
): string
```

Returns an HTML `<a>` tag. All attributes and the link text are HTML-escaped.

```php
<?= $this->getLink('/foo', 'Click it!!', 'Yes, click me', 'btn big', false) ?>
<?= $this->getLink('/foo', getAbsoluteUrl: true) ?>
<?= $this->getLink('/', '<i class="bi bi-house"></i> Home', 'Home', escapeValue: false) ?>
```

```html
<a href="/foo" title="Yes, click me" class="btn big">Click it!!</a>
<a href="https://example.org/foo" title="https://example.org/foo">https://example.org/foo</a>
<a href="/" title="Home"><i class="bi bi-house"></i> Home</a>
```

URLs using the `javascript:`, `vbscript:` or `data:` scheme are replaced with `#`.

> Only use `escapeValue: false` with trusted, static markup – never with user input.

### addNamedUrl / getNamedUrl

```php
addNamedUrl(string $urlName, string $url): void
getNamedUrl(string $linkName, ?bool $getAbsoluteUrl = null): string
```

Named URLs keep paths in one place. Register them in the constructor, on the extension object or inside a template.
If a name is unknown, the name itself is returned.

```php
$toolset = new PlatesUrlToolset('https://example.org', false, ['home' => '/']);
$toolset->addNamedUrl('imprint', '/legal/imprint');
```

```php
<?= $this->e($this->getNamedUrl('imprint')) ?>
<?= $this->e($this->getNamedUrl('imprint', true)) ?>
```

```text
/legal/imprint
https://example.org/legal/imprint
```

### getNamedLink

```php
getNamedLink(
    string $linkName,
    ?string $value = null,
    ?string $title = null,
    ?string $classlist = null,
    ?bool $getAbsoluteUrl = null,
    bool $escapeValue = true,
): string
```

Same as [`getLink`](#getlink), but uses a named URL. Unknown names are returned as escaped text.

```php
<?= $this->getNamedLink('imprint', 'Imprint', 'Legal notice', 'nav-link') ?>
```

```html
<a href="/legal/imprint" title="Legal notice" class="nav-link">Imprint</a>
```

### getCurrentUrl / getCurrentUrlWithoutQuery

```php
getCurrentUrl(?bool $getAbsoluteUrl = null, string $append = ''): string
getCurrentUrlWithoutQuery(?bool $getAbsoluteUrl = null, string $append = ''): string
```

Returns the URL of the current request (`REQUEST_URI`), optionally with `$append` appended. Characters which are not
allowed in URLs (quotes, `<`, `>`, spaces, …) are percent-encoded. Without a request (CLI), `/` is used.

```php
<?= $this->e($this->getCurrentUrl()) ?>
<?= $this->e($this->getCurrentUrl(true)) ?>
<?= $this->e($this->getCurrentUrlWithoutQuery()) ?>
```

```text
/foobar?page=2
https://example.org/foobar?page=2
/foobar
```

### getDebugUrl

```php
getDebugUrl(
    string $url,
    ?bool $getAbsoluteUrl = null,
    string $queryParameter = 'request_time',
    ?string $queryParameterValue = null,   // defaults to the current timestamp
): string
```

Appends a query parameter – useful for cache busting of assets during development. Existing query strings and
fragments are respected; name and value are URL-encoded.

```php
<link rel="stylesheet" href="<?= $this->e($this->getDebugUrl('/css/app.css')) ?>">
<?= $this->e($this->getDebugUrl('/img/bar.jpg', true, 'v', '42')) ?>
<?= $this->e($this->getDebugUrl('/app.js?lang=de', false, 'v', '42')) ?>
```

```text
/css/app.css?request_time=1761649200
https://example.org/img/bar.jpg?v=42
/app.js?lang=de&v=42
```

### getCurrentFqdn

```php
getCurrentFqdn(): string
```

Returns the host (with port, if any) of the current request. Invalid `Host` headers are ignored, see
[Base URL and host detection](#base-url-and-host-detection).

```php
<?= $this->e($this->getCurrentFqdn()) ?>
```

```text
example.org
```

## Security

- **Configure `baseUrl` in production.** Without it, absolute URLs are built from the client-controlled `Host`
  header. The header is validated, but a valid foreign host (e.g. `Host: attacker.com`) is still accepted unless
  your web server rejects unknown hosts. This matters for links in e-mails (password reset, …) and for cached pages.
- **Reverse proxies:** `trustProxyHeaders` is enabled by default. If your application is reachable directly,
  disable it so clients can't influence the detected protocol via `X-Forwarded-Proto`.
- **Escaping:** `getLink()` and `getNamedLink()` return HTML and escape everything for you. All other functions return
  plain URL strings – escape them with Plates' `$this->e()` when you print them into HTML.
- **sprintf placeholders:** only pass trusted strings as the `$url` argument of `getAbsoluteUrl()`; pass dynamic
  values via `...$args` instead of concatenating them into the format string.

## Upgrading from 0.0.x

- PHP 8.2 or newer is required, `league/plates` is now a regular dependency (`^3.6`).
- `getLink()` / `getNamedLink()` now escape the link text. Pass `escapeValue: false` if you use HTML (e.g. icons)
  inside the link.
- A base URL with protocol (`https://example.org`) now works as expected. Previously it produced
  `https://https://example.org/…`.
- Passing `true` as `$getAbsoluteUrl` to `getNamedUrl()` / `getLink()` / `getNamedLink()` now really returns an
  absolute URL.
- `getCurrentUrl()` percent-encodes characters that are not allowed in URLs.
- `getDebugUrl()` uses `&` when the URL already contains a query string.

## Development

```bash
composer install
composer test
```

The test suite runs on PHP 8.2, 8.3, 8.4 and 8.5 via GitHub Actions. A runnable example lives in `example/`
(the DDEV configuration serves it as document root).

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Contributing

Feel free to contribute to this project. Just create a pull request with your changes.

## Support

[![ko-fi](https://ko-fi.com/img/githubbutton_sm.svg)](https://ko-fi.com/S6S6NIYIK)
