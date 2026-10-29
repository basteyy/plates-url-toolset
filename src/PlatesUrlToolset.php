<?php
/**
 * @author xzit.online <hallo@xzit.email>
 * @website https://github.com/basteyy
 * @website https://xzit.online
 */

declare(strict_types=1);

namespace basteyy\PlatesUrlToolset;

use InvalidArgumentException;
use League\Plates\Engine;
use League\Plates\Extension\ExtensionInterface;
use League\Plates\Template\Template;

class PlatesUrlToolset implements ExtensionInterface
{
    /**
     * Pattern for a valid host (with optional port). Used to reject manipulated Host headers.
     */
    private const HOST_PATTERN = '/^(?:[a-z0-9_\-]+(?:\.[a-z0-9_\-]+)*\.?|\[[0-9a-f:.]+\])(?::\d{1,5})?$/i';

    /**
     * URL schemes which are never rendered into a href attribute
     */
    private const UNSAFE_SCHEMES = ['javascript', 'vbscript', 'data'];

    /**
     * @var string|null Protocol of the url. Null means auto-detection per request.
     */
    private ?string $protocol = null;

    /**
     * @var string|null Base url (host, optional port and path, without protocol). Null means auto-detection per request.
     */
    private ?string $baseUrl = null;

    /**
     * @var array<string, string> The array for named urls
     */
    private array $namedUrl;

    /**
     * @var bool Change the behavior of urls when no concrete definition is parsed from the template
     */
    private bool $defaultAbsoluteUrl;

    /**
     * @var bool Respect X-Forwarded-Proto and Front-End-Https headers when detecting https
     */
    private bool $trustProxyHeaders;

    /**
     * @var Template|null The template (set by Plates on each function call)
     */
    public ?Template $template = null;

    /**
     * PlatesUrlToolset constructor.
     * @param string|null $baseUrl Base url like "https://example.org" or "example.org". Null detects the host from the request.
     * @param bool $defaultAbsoluteUrl Return absolute urls when no concrete definition is passed
     * @param array<string, string> $namedLinks Named urls
     * @param bool $trustProxyHeaders Respect proxy headers when detecting https
     * @throws InvalidArgumentException When the base url is invalid
     */
    public function __construct(
        ?string $baseUrl = null,
        bool $defaultAbsoluteUrl = false,
        array $namedLinks = [],
        bool $trustProxyHeaders = true,
    ) {
        $this->trustProxyHeaders = $trustProxyHeaders;

        if (null !== $baseUrl && '' !== trim($baseUrl)) {
            $this->parseBaseUrl(trim($baseUrl));
        }

        $this->namedUrl = $namedLinks;
        $this->defaultAbsoluteUrl = $defaultAbsoluteUrl;
    }

    /**
     * Split the base url into protocol and host/path
     * @param string $baseUrl
     * @throws InvalidArgumentException
     */
    private function parseBaseUrl(string $baseUrl): void
    {
        $hasScheme = (bool)preg_match('~^[a-z][a-z0-9+.\-]*://~i', $baseUrl);
        $urlParts = parse_url($hasScheme ? $baseUrl : '//' . $baseUrl);

        if (false === $urlParts || empty($urlParts['host'])) {
            throw new InvalidArgumentException(sprintf('Invalid base url "%s".', $baseUrl));
        }

        if (isset($urlParts['scheme'])) {
            $scheme = strtolower($urlParts['scheme']);

            if ('http' !== $scheme && 'https' !== $scheme) {
                throw new InvalidArgumentException(sprintf('Unsupported protocol "%s" in base url. Use http or https.', $scheme));
            }

            $this->protocol = $scheme;
        }

        $host = $urlParts['host'] . (isset($urlParts['port']) ? ':' . $urlParts['port'] : '');

        if (!preg_match(self::HOST_PATTERN, $host)) {
            throw new InvalidArgumentException(sprintf('Invalid host "%s" in base url.', $host));
        }

        $this->baseUrl = $host . rtrim($urlParts['path'] ?? '', '/');
    }

    /**
     * Check if the current connection is secured via https
     * @return bool
     */
    protected function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        if (!$this->trustProxyHeaders) {
            return false;
        }

        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }

        return !empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower((string)$_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off';
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
        $engine->registerFunction('getCurrentFqdn', [$this, 'getCurrentFqdn']);
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
    public function getNamedUrl(string $linkName, ?bool $getAbsoluteUrl = null): string
    {
        if (isset($this->namedUrl[$linkName])) {
            return $this->getUrl($this->namedUrl[$linkName], $getAbsoluteUrl);
        }

        return $linkName;
    }

    /**
     * Process an url
     * @param string $url
     * @param bool|null $getAbsoluteUrl Null uses the default behavior
     * @param mixed ...$args Values for sprintf placeholders inside the url
     * @return string
     */
    protected function getUrl(string $url, ?bool $getAbsoluteUrl, mixed ...$args): string
    {
        if (count($args) > 0) {
            $url = sprintf($url, ...$args);
        }

        return ($getAbsoluteUrl ?? $this->defaultAbsoluteUrl) ? $this->getAbsoluteUrl($url) : $url;
    }

    /**
     * Returns an absolute url
     * @param string $url
     * @param mixed ...$args Values for sprintf placeholders inside the url
     * @return string
     */
    public function getAbsoluteUrl(string $url, mixed ...$args): string
    {
        if (count($args) > 0) {
            $url = sprintf($url, ...$args);
        }

        return $this->getProtocol() . '://' . $this->getBaseUrl() . '/' . ltrim($url, '/');
    }

    /**
     * Return a named link
     * @param string $linkName
     * @param string|null $value
     * @param string|null $title
     * @param string|null $classlist
     * @param bool|null $getAbsoluteUrl
     * @param bool $escapeValue Set to false to allow html (e.g. icons) inside the link text
     * @return string
     */
    public function getNamedLink(
        string $linkName,
        ?string $value = null,
        ?string $title = null,
        ?string $classlist = null,
        ?bool $getAbsoluteUrl = null,
        bool $escapeValue = true,
    ): string {
        if (isset($this->namedUrl[$linkName])) {
            return $this->getLink(
                $this->namedUrl[$linkName],
                $value,
                $title,
                $classlist,
                $getAbsoluteUrl,
                $escapeValue
            );
        }

        return $this->escape($linkName);
    }

    /**
     * Returns a link in html markup. All attributes are escaped.
     * @param string $url
     * @param string|null $value
     * @param string|null $title
     * @param string|null $classlist
     * @param bool|null $getAbsoluteUrl
     * @param bool $escapeValue Set to false to allow html (e.g. icons) inside the link text
     * @return string
     */
    public function getLink(
        string $url,
        ?string $value = null,
        ?string $title = null,
        ?string $classlist = null,
        ?bool $getAbsoluteUrl = null,
        bool $escapeValue = true,
    ): string {
        $url = $this->getUrl($url, $getAbsoluteUrl);

        if ($this->hasUnsafeScheme($url)) {
            $url = '#';
        }

        return sprintf('<a href="%1$s" title="%3$s"%4$s>%2$s</a>',
            $this->escape($url),
            null === $value ? $this->escape($url) : ($escapeValue ? $this->escape($value) : $value),
            $this->escape($title ?? $url),
            null !== $classlist ? ' class="' . $this->escape($classlist) . '"' : ''
        );
    }

    /**
     * This function returns the current url. You can append a string with the second parameter
     * @param bool|null $getAbsoluteUrl
     * @param string $append
     * @return string
     */
    public function getCurrentUrl(?bool $getAbsoluteUrl = null, string $append = ''): string
    {
        $url = $this->getRequestUri() . str_replace('//', '/', $append);

        if ($getAbsoluteUrl ?? $this->defaultAbsoluteUrl) {
            $url = $this->getAbsoluteUrl($url);
        }

        return $url;
    }

    /**
     * This function returns the current url without the query. You can append a string with the second parameter
     * @param bool|null $getAbsoluteUrl
     * @param string $append
     * @return string
     */
    public function getCurrentUrlWithoutQuery(?bool $getAbsoluteUrl = null, string $append = ''): string
    {
        return explode('?', $this->getCurrentUrl($getAbsoluteUrl, $append), 2)[0];
    }

    /**
     * Use that function for creating debugging urls which show the time con request as a query parameter
     * @param string $url
     * @param bool|null $getAbsoluteUrl
     * @param string $queryParameter
     * @param string|null $queryParameterValue
     * @return string
     */
    public function getDebugUrl(string $url, ?bool $getAbsoluteUrl = null, string $queryParameter = 'request_time', ?string $queryParameterValue = null): string
    {
        if ($getAbsoluteUrl ?? $this->defaultAbsoluteUrl) {
            $url = $this->getAbsoluteUrl($url);
        }

        [$url, $fragment] = array_pad(explode('#', $url, 2), 2, null);

        return $url
            . (str_contains($url, '?') ? '&' : '?')
            . rawurlencode($queryParameter) . '=' . rawurlencode($queryParameterValue ?? (string)time())
            . (null !== $fragment ? '#' . $fragment : '');
    }

    /**
     * Get the current FQDN (host with optional port) of the request. Invalid Host headers are ignored.
     * @return string
     */
    public function getCurrentFqdn(): string
    {
        foreach (['HTTP_HOST', 'SERVER_NAME'] as $key) {
            $host = $_SERVER[$key] ?? null;

            if (is_string($host) && preg_match(self::HOST_PATTERN, $host)) {
                return strtolower($host);
            }
        }

        return 'localhost';
    }

    /**
     * Get the configured or detected protocol
     * @return string
     */
    private function getProtocol(): string
    {
        return $this->protocol ?? ($this->isHttps() ? 'https' : 'http');
    }

    /**
     * Get the configured or detected base url
     * @return string
     */
    private function getBaseUrl(): string
    {
        return $this->baseUrl ?? $this->getCurrentFqdn();
    }

    /**
     * Get the request uri. Characters which are not allowed in urls (like quotes or angle brackets) are percent-encoded.
     * @return string
     */
    private function getRequestUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        if (!is_string($uri) || '' === $uri) {
            return '/';
        }

        return (string)preg_replace_callback(
            '/[^A-Za-z0-9\-._~:\/?#\[\]@!$&()*+,;=%]/',
            static fn(array $match): string => rawurlencode($match[0]),
            $uri
        );
    }

    /**
     * Check if the url uses a scheme which can execute code inside the browser
     * @param string $url
     * @return bool
     */
    private function hasUnsafeScheme(string $url): bool
    {
        // Browsers ignore whitespace and control characters inside the scheme
        $normalized = (string)preg_replace('/[\x00-\x20\x7F]+/', '', $url);

        return (bool)preg_match('/^(?:' . implode('|', self::UNSAFE_SCHEMES) . '):/i', $normalized);
    }

    /**
     * Escape a value for the usage inside html
     * @param string $value
     * @return string
     */
    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
