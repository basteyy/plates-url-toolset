<?php
/**
 * @author xzit.online <hallo@xzit.email>
 * @website https://github.com/basteyy
 * @website https://xzit.online
 */

declare(strict_types=1);

namespace basteyy\PlatesUrlToolset\Tests;

use basteyy\PlatesUrlToolset\PlatesUrlToolset;
use InvalidArgumentException;
use League\Plates\Engine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlatesUrlToolsetTest extends TestCase
{
    private array $serverBackup;

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['HTTP_FRONT_END_HTTPS'], $_SERVER['SERVER_NAME']);
        $_SERVER['HTTP_HOST'] = 'example.org';
        $_SERVER['REQUEST_URI'] = '/foobar?a=1';
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
    }

    public function testBaseUrlWithSchemeIsUsedAsIs(): void
    {
        $toolset = new PlatesUrlToolset('http://example.com/');
        $_SERVER['HTTPS'] = 'on';

        self::assertSame('http://example.com/foo', $toolset->getAbsoluteUrl('/foo'));
    }

    public function testBaseUrlWithPathAndPort(): void
    {
        $toolset = new PlatesUrlToolset('https://example.com:8443/sub/');

        self::assertSame('https://example.com:8443/sub/foo', $toolset->getAbsoluteUrl('foo'));
    }

    public function testBaseUrlWithoutSchemeDetectsProtocol(): void
    {
        $toolset = new PlatesUrlToolset('example.com');

        self::assertSame('http://example.com/foo', $toolset->getAbsoluteUrl('/foo'));

        $_SERVER['HTTPS'] = 'on';
        self::assertSame('https://example.com/foo', $toolset->getAbsoluteUrl('/foo'));
    }

    #[DataProvider('invalidBaseUrls')]
    public function testInvalidBaseUrlThrows(string $baseUrl): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PlatesUrlToolset($baseUrl);
    }

    public static function invalidBaseUrls(): array
    {
        return [
            'javascript' => ['javascript://alert(1)'],
            'ftp' => ['ftp://example.org'],
            'no host' => ['https://'],
        ];
    }

    public function testAbsoluteUrlWithPlaceholders(): void
    {
        $toolset = new PlatesUrlToolset('https://example.org');

        self::assertSame('https://example.org/user/5/edit', $toolset->getAbsoluteUrl('/user/%d/%s', 5, 'edit'));
    }

    public function testPercentEncodedUrlIsNotPassedToSprintf(): void
    {
        $toolset = new PlatesUrlToolset('https://example.org', true, ['space' => '/a%20b']);

        self::assertSame('https://example.org/a%20b', $toolset->getNamedUrl('space'));
        self::assertSame('https://example.org/a%20b', $toolset->getAbsoluteUrl('/a%20b'));
        self::assertSame('<a href="/a%20b" title="x">x</a>', $toolset->getLink('/a%20b', 'x', 'x', null, false));
    }

    public function testInvalidHostHeaderIsIgnored(): void
    {
        $_SERVER['HTTP_HOST'] = 'evil.com/"><script>';
        $_SERVER['SERVER_NAME'] = 'example.org';

        $toolset = new PlatesUrlToolset();

        self::assertSame('example.org', $toolset->getCurrentFqdn());
        self::assertSame('http://example.org/foo', $toolset->getAbsoluteUrl('/foo'));
    }

    public function testMissingServerVariablesInCli(): void
    {
        unset($_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI']);

        $toolset = new PlatesUrlToolset();

        self::assertSame('localhost', $toolset->getCurrentFqdn());
        self::assertSame('/', $toolset->getCurrentUrl());
    }

    public function testProxyHeadersCanBeDisabled(): void
    {
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';

        self::assertSame('https://example.org/', (new PlatesUrlToolset())->getAbsoluteUrl('/'));
        self::assertSame('http://example.org/', (new PlatesUrlToolset(trustProxyHeaders: false))->getAbsoluteUrl('/'));
    }

    public function testNamedUrl(): void
    {
        $toolset = new PlatesUrlToolset('https://example.org', false, ['home' => '/']);
        $toolset->addNamedUrl('linkname', 'foobar');

        self::assertSame('foobar', $toolset->getNamedUrl('linkname'));
        self::assertSame('https://example.org/foobar', $toolset->getNamedUrl('linkname', true));
        self::assertSame('/', $toolset->getNamedUrl('home'));
        self::assertSame('unknown', $toolset->getNamedUrl('unknown'));
    }

    public function testDefaultAbsoluteUrlCanBeOverridden(): void
    {
        $toolset = new PlatesUrlToolset('https://example.org', true, ['home' => '/home']);

        self::assertSame('https://example.org/home', $toolset->getNamedUrl('home'));
        self::assertSame('/home', $toolset->getNamedUrl('home', false));
    }

    public function testLink(): void
    {
        $toolset = new PlatesUrlToolset('https://example.org');

        self::assertSame(
            '<a href="/foo" title="Yes, click me" class="btn big">Click it!!</a>',
            $toolset->getLink('/foo', 'Click it!!', 'Yes, click me', 'btn big', false)
        );
        self::assertSame(
            '<a href="https://example.org/foo" title="https://example.org/foo">https://example.org/foo</a>',
            $toolset->getLink('/foo', getAbsoluteUrl: true)
        );
    }

    public function testLinkEscapesAttributesAndValue(): void
    {
        $toolset = new PlatesUrlToolset();

        self::assertSame(
            '<a href="/a?x=&quot;&gt;" title="&lt;b&gt;" class="&quot; onclick=&quot;x">&lt;script&gt;</a>',
            $toolset->getLink('/a?x=">', '<script>', '<b>', '" onclick="x')
        );
    }

    public function testLinkValueCanContainHtml(): void
    {
        $toolset = new PlatesUrlToolset();

        self::assertSame(
            '<a href="/" title="Home"><i class="bi bi-house"></i> Home</a>',
            $toolset->getLink('/', '<i class="bi bi-house"></i> Home', 'Home', escapeValue: false)
        );
    }

    #[DataProvider('unsafeUrls')]
    public function testLinkBlocksUnsafeSchemes(string $url): void
    {
        $link = (new PlatesUrlToolset())->getLink($url, 'x', 'x');

        self::assertSame('<a href="#" title="x">x</a>', $link);
    }

    public static function unsafeUrls(): array
    {
        return [
            ['javascript:alert(1)'],
            ['JaVaScRiPt:alert(1)'],
            [" java\tscript:alert(1)"],
            ['data:text/html;base64,PHNjcmlwdD4='],
            ['vbscript:msgbox(1)'],
        ];
    }

    public function testNamedLink(): void
    {
        $toolset = new PlatesUrlToolset(namedLinks: ['linkname' => '/foobar-123456789']);

        self::assertSame(
            '<a href="/foobar-123456789" title="Yes, click me" class="btn big">Click it!!</a>',
            $toolset->getNamedLink('linkname', 'Click it!!', 'Yes, click me', 'btn big', false)
        );
        self::assertSame('&lt;unknown&gt;', $toolset->getNamedLink('<unknown>'));
    }

    public function testCurrentUrl(): void
    {
        $toolset = new PlatesUrlToolset('https://example.org');

        self::assertSame('/foobar?a=1', $toolset->getCurrentUrl());
        self::assertSame('https://example.org/foobar?a=1', $toolset->getCurrentUrl(true));
        self::assertSame('/foobar', $toolset->getCurrentUrlWithoutQuery());
        self::assertSame('https://example.org/foobar', $toolset->getCurrentUrlWithoutQuery(true));
    }

    public function testCurrentUrlEncodesUnsafeCharacters(): void
    {
        $_SERVER['REQUEST_URI'] = '/foo?x="><script>alert(\'1\')</script>';

        self::assertSame(
            '/foo?x=%22%3E%3Cscript%3Ealert(%271%27)%3C/script%3E',
            (new PlatesUrlToolset())->getCurrentUrl()
        );
    }

    public function testCurrentUrlWithoutQueryWhenUriStartsWithQuery(): void
    {
        $_SERVER['REQUEST_URI'] = '?a=b';

        self::assertSame('', (new PlatesUrlToolset())->getCurrentUrlWithoutQuery());
    }

    public function testDebugUrl(): void
    {
        $toolset = new PlatesUrlToolset('https://example.org');

        self::assertMatchesRegularExpression('~^/foo\.css\?request_time=\d+$~', $toolset->getDebugUrl('/foo.css'));
        self::assertSame('https://example.org/foobar/file/bar.jpg?blabla=aaaa', $toolset->getDebugUrl('/foobar/file/bar.jpg', true, 'blabla', 'aaaa'));
        self::assertSame('/a.css?v=1&t=2#top', $toolset->getDebugUrl('/a.css?v=1#top', false, 't', '2'));
        self::assertSame('/a.css?a%20b=%22%3E', $toolset->getDebugUrl('/a.css', false, 'a b', '">'));
    }

    public function testRegisteredFunctionsWorkInsideTemplates(): void
    {
        $dir = sys_get_temp_dir() . '/plates-url-toolset-' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir . '/page.php', '<?php $this->addNamedUrl("home", "/home") ?><?= $this->getNamedLink("home", "Home") ?>|<?= $this->getCurrentFqdn() ?>');

        try {
            $engine = new Engine($dir);
            $engine->loadExtension(new PlatesUrlToolset('https://example.org'));

            self::assertSame('<a href="/home" title="/home">Home</a>|example.org', $engine->render('page'));
        } finally {
            unlink($dir . '/page.php');
            rmdir($dir);
        }
    }
}
