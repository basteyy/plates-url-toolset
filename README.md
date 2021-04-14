# Plates Url Toolset

This plugin provides a few url function for using inside [plates](https://platesphp.com/) templates.

## Setup

First download the library via composer:

```bash
composer require basteyy/plates-url-toolset
```

## Usage

### Load Extension

Make the url tools available by loading the extension to your plates scope:

```php
/** @var \League\Plates\Engine $engine */
$engine->loadExtension(new \basteyy\PlatesUrlToolset\PlatesUrlToolset());
```

### Load Options

```php
/** @var \League\Plates\Engine $engine */
$engine->loadExtension(new \basteyy\PlatesUrlToolset\PlatesUrlToolset(
    null, // The base url for all the urls. Should be something like https://example.com
    true, // Change the default value for using absolute urls
    []// Register named urls for use it later 
));
```

### Usages inside the templates

#### Get the current url

```php
echo $this->getCurrentUrl(); // Current url 
```

Result:
```html
http://example.com/foobar
```

#### Create a html link

```php
echo $this->getLink('/foo', 'Click it!!', 'Yes, click me', 'btn big', false);
```

Result:
```html
<a href="/foo" title="Yes, click me" class="btn big">Click it!!</a>
```

#### Get a named url

```php
// Register the link in the constructer or via 
$this->addNamedUrl('linkname', 'foobar');

echo $this->getNamedUrl('linkname');
echo $this->getNamedUrl('linkname', true);
```

Result:
```html
/foobar
https://example.com/foobar
```

#### Get a named html link

```php
// Register the link in the constructer or via 
$this->addNamedUrl('linkname', 'foobar-123456789');

echo $this->getNamedLink('linkname', 'Click it!!', 'Yes, click me', 'btn big', false);
```

Result:
```html
<a href="/foobar-123456789" title="Yes, click me" class="btn big">Click it!!</a>
```


#### Get a url with a debugging timestamp appended

```php
// Register the link in the constructer or via 
echo $this->getDebugUrl('/foobar/file/something.css');

echo $this->getDebugUrl('/foobar/file/bar.jpg', true, 'blabla', 'aaaa');
```

Result:
```html
/foobar/file/something.css?request_time=1234567
(current timestamp)

https://example.com/foobar/file/bar.jpg?blabla=aaaa
```

## License

The MIT License (MIT). Please see [License File](https://github.com/basteyy/plates-url-toolset/blob/master/LICENSE) for more information.
