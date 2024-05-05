# Plates URL Toolset

This plugin provides a few URL functions for use within [Plates](https://platesphp.com/) templates.

## Setup

First, download the library via Composer:

```bash
composer require basteyy/plates-url-toolset
```
Of course, you can download the library manually and include it in your project. But I recommend using Composer.

## Usage

### Load Extension

To make the URL tools available, load the extension into your Plates scope:

```php
/** @var \League\Plates\Engine $engine */
$engine->loadExtension(new \basteyy\PlatesUrlToolset\PlatesUrlToolset());
```

### Load Options

Customize the extension by setting options when loading it:

```php
/** @var \League\Plates\Engine $engine */
$engine->loadExtension(
    new \basteyy\PlatesUrlToolset\PlatesUrlToolset(
        null,  // The base URL for all URLs. Should be something like https://example.org
        true,  // Change the default value for using absolute URLs
        []     // Register named URLs for later use
    ));
```

### Usages Inside the Templates

For all the examples below, I use `example.org` as the hypothetical website URL.

#### Get the Current URL

```php
echo $this->getCurrentUrl(); // Displays the current URL
```

Result:
```html
http://example.org/foobar
```

#### Create an HTML Link

```php
echo $this->getLink('/foo', 'Click it!!', 'Yes, click me', 'btn big', false);
```

Result:
```html
<a href="/foo" title="Yes, click me" class="btn big">Click it!!</a>
```

#### Get a Named URL

```php
// Register the link in the constructor or via
$this->addNamedUrl('linkname', 'foobar');

echo $this->getNamedUrl('linkname');
echo $this->getNamedUrl('linkname', true);
```

Result:
```html
/foobar
https://example.org/foobar
```

#### Get a Named HTML Link

```php
// Register the link in the constructor or via
$this->addNamedUrl('linkname', 'foobar-123456789');

echo $this->getNamedLink('linkname', 'Click it!!', 'Yes, click me', 'btn big', false);
```

Result:
```html
<a href="/foobar-123456789" title="Yes, click me" class="btn big">Click it!!</a>
```

#### Get a URL with a Debugging Timestamp Appended

```php
// Register the link in the constructor or via
echo $this->getDebugUrl('/foobar/file/something.css');

echo $this->getDebugUrl('/foobar/file/bar.jpg', true, 'blabla', 'aaaa');
```

Result:
```html
/foobar/file/something.css?request_time=1234567
(current timestamp)

https://example.org/foobar/file/bar.jpg?blabla=aaaa
```

## License

The MIT License (MIT). Please see [License File](https://github.com/basteyy/plates-url-toolset/blob/master/LICENSE) for more information.

## Contributing

Feel free to contribute to this project. Just create a pull request with your changes.

## Support

[![ko-fi](https://ko-fi.com/img/githubbutton_sm.svg)](https://ko-fi.com/S6S6NIYIK)
