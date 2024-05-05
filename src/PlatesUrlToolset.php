<?php
/**
 * @author xzit.online <hallo@xzit.email>
 * @website https://github.com/basteyy
 * @website https://xzit.online
 */

declare(strict_types=1);

namespace basteyy\PlatesUrlToolset;

use JetBrains\PhpStorm\Pure;
use League\Plates\Engine;
use League\Plates\Extension\ExtensionInterface;
use League\Plates\Template\Template;

class PlatesUrlToolset implements ExtensionInterface
{
    /**
     * @var string Protocol of the url
     */
    private string $protocol;

    /**
     * @var string Base url
     */
    private string $baseUrl;

    /**
     * @var array|string[] The array for named urls
     */
    private array $namedUrl;

    /**
     * @var bool Change the behavior of urls when no concrete definition is parsed from the template
     */
    private bool $defaultAbsoluteUrl;

    /**
     * @var Template The template
     */
    public Template $template;

    /**
     * PlatesUrlToolset constructor.
     * @param string|null $baseUrl
     * @param bool $defaultAbsoluteUrl
     * @param array $namedLinks
     */
    public function __construct(
        string $baseUrl = null,
        bool $defaultAbsoluteUrl = false,
        array $namedLinks = [])
    {
        if (isset($baseUrl)) {
            $urlParts = parse_url($baseUrl);
            $this->protocol = $urlParts['scheme'] ?? $this->isHttps() ? 'https' : 'http';
            $baseUrl = rtrim($baseUrl, '/');
        } else {
            $this->protocol = $this->isHttps() ? 'https' : 'http';
        }

        $this->baseUrl = $baseUrl ?? $_SERVER['HTTP_HOST'];
        $this->namedUrl = $namedLinks ?? ['home' => '/'];
        $this->defaultAbsoluteUrl = $defaultAbsoluteUrl;
    }

    /**
     * Check if the current connection is secured via https
     * @return bool
     */
    protected function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
            return true;
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        } elseif (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off') {
            return true;
        }

        return false;
    }

    /**
     * Register the functions
     * @param Engine $engine
     */
    public function register(Engine $engine): void
    {
        $engine->registerFunction('getLink', [$this, 'getLink']);
        $engine->registerFunction('getAbsoluteUrl', [$this, 'getAbsoluteUrl']);
        $engine->registerFunction('getDebugUrl', [$this, 'getDebugUrl']);
        $engine->registerFunction('getCurrentUrl', [$this, 'getCurrentUrl']);
        $engine->registerFunction('getCurrentUrlWithoutQuery', [$this, 'getCurrentUrlWithoutQuery']);
        $engine->registerFunction('getNamedUrl', [$this, 'getNamedUrl']);
        $engine->registerFunction('getNamedLink', [$this, 'getNamedLink']);
        $engine->registerFunction('addNamedUrl', [$this, 'addNamedUrl']);
    }

    /**
     * Add a new named url
     * @param string $urlName
     * @param string $url
     */
    public function addNamedUrl(string $urlName, string $url): void
    {
        $this->namedUrl[$urlName] = $url;
    }

    /**
     * Return a named url
     * @param string $linkName
     * @param bool|null $getAbsoluteUrl
     * @return string
     */
    public function getNamedUrl(string $linkName, bool $getAbsoluteUrl = null): string
    {
        if (isset($this->namedUrl[$linkName])) {
            return $this->getUrl($this->namedUrl[$linkName], (null === $getAbsoluteUrl && $this->defaultAbsoluteUrl));
        }

        return $linkName;
    }

    /**
     * Process an url
     * @param string $url
     * @param bool $getAbsoluteUrl
     * @param mixed ...$args
     * @return string
     */
    protected function getUrl(string $url, bool $getAbsoluteUrl, ...$args): string
    {
        if ($getAbsoluteUrl) {
            $url = $this->getAbsoluteUrl($url, $args ?? null);
        }

        return count($args) == 0 ? $url : sprintf($url, ...$args);
    }

    /**
     * Returns a absolute url
     * @param string $url
     * @param mixed ...$args
     * @return string
     */
    #[Pure] public function getAbsoluteUrl(string $url, ...$args): string
    {
        if(count($args) == 0 ) {
            return $this->protocol . '://' . $this->baseUrl . '/' . ltrim($url, '/');
        }

        return $this->protocol . '://' . $this->baseUrl . '/' . ltrim(sprintf($url, ...$args), '/');
    }

    /**
     * Return a named link
     * @param string $linkName
     * @param string|null $value
     * @param string|null $title
     * @param string|null $classlist
     * @param bool|null $getAbsoluteUrl
     * @return string
     */
    public function getNamedLink(string $linkName, string $value = null, string $title = null, string $classlist = null, bool $getAbsoluteUrl = null): string
    {
        if (isset($this->namedUrl[$linkName])) {
            return $this->getLink(
                $this->namedUrl[$linkName],
                $value,
                $title,
                $classlist,
                $getAbsoluteUrl
            );
        }

        return $linkName;
    }

    /**
     * Returns a link in html markup
     * @param string $url
     * @param string|null $value
     * @param string|null $title
     * @param string|null $classlist
     * @param bool $getAbsoluteUrl
     * @return string
     */
    #[Pure] public function getLink(string $url, string $value = null, string $title = null, string $classlist = null, bool $getAbsoluteUrl = null): string
    {
        $url = $this->getUrl($url, (null === $getAbsoluteUrl && $this->defaultAbsoluteUrl));

        return sprintf('<a href="%1$s" title="%3$s"%4$s>%2$s</a>',
            $url,
            $value ?? $url,
            $title ?? $url,
            isset($classlist) ? ' class="' . $classlist . '"' : ''
        );
    }

    /**
     * This function returns the current url. You can append a string with the second parameter
     * @param bool $getAbsoluteUrl
     * @param string $append
     * @return string
     */
    public function getCurrentUrl(bool $getAbsoluteUrl = null, string $append = ''): string
    {
        if (null === $getAbsoluteUrl && $this->defaultAbsoluteUrl) {
            $getAbsoluteUrl = true;
        }

        $url = $_SERVER['REQUEST_URI'] . str_replace('//', '/', $append);

        if ($getAbsoluteUrl) {
            $url = $this->getAbsoluteUrl($url);
        }

        return $url;
    }

    /**
     * This function returns the current url without the query. You can append a string with the second parameter
     * @param bool $getAbsoluteUrl
     * @param string $append
     * @return string
     */
    public function getCurrentUrlWithoutQuery(bool $getAbsoluteUrl = null, string $append = ''): string
    {
        return strtok($this->getCurrentUrl($getAbsoluteUrl, $append), '?');
    }

    /**
     * Use that function for creating debugging urls which show the time con request as a query parameter
     * @param string $url
     * @param bool $getAbsoluteUrl
     * @param string $queryParameter
     * @param string|null $queryParameterValue
     * @return string
     */
    public function getDebugUrl(string $url, bool $getAbsoluteUrl = null, string $queryParameter = 'request_time', string $queryParameterValue = null): string
    {
        if (null === $getAbsoluteUrl && $this->defaultAbsoluteUrl) {
            $getAbsoluteUrl = true;
        }

        if ($getAbsoluteUrl) {
            $url = $this->getAbsoluteUrl($url);
        }

        return $url . '?' . $queryParameter . '=' . ($queryParameterValue ?? time());
    }
}
