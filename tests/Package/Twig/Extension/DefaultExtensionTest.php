<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2021 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\Package\Twig\Tests\Extension;

use Berlioz\Core\Core;
use Berlioz\Core\Tests\RestoresErrorHandler;
use Berlioz\Package\Twig\Extension\DefaultExtension;
use Berlioz\Package\Twig\TestProject\FakeDefaultDirectories;
use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class DefaultExtensionTest extends TestCase
{
    use RestoresErrorHandler;

    private function renderTemplate(string $template, array $context = [], string|false $autoescape = 'html'): string
    {
        $twig = new Environment(new ArrayLoader(['test' => $template]), ['autoescape' => $autoescape]);
        $twig->addExtension(new DefaultExtension(new Core(new FakeDefaultDirectories(), false)));

        return $twig->render('test', $context);
    }

    public static function provideNl2p(): array
    {
        return [
            'plain text' => [
                "First line\nSecond line\n\nNew paragraph",
                "<p>First line<br />\nSecond line</p>\n<p>New paragraph</p>",
            ],
            'html tags' => [
                "<script>alert(1)</script>\n\n<b onclick=\"x()\">bold</b>",
                "<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>\n" .
                "<p>&lt;b onclick=&quot;x()&quot;&gt;bold&lt;/b&gt;</p>",
            ],
            'punctuation' => [
                "Fish & chips, \"quoted\" and 'single'\n1 < 2 > 0",
                "<p>Fish &amp; chips, &quot;quoted&quot; and &#039;single&#039;<br />\n1 &lt; 2 &gt; 0</p>",
            ],
        ];
    }

    #[DataProvider('provideNl2p')]
    public function testFilterNl2p(string $text, string $expected)
    {
        $this->assertSame($expected, $this->renderTemplate('{{ text|nl2p }}', ['text' => $text]));
    }

    public function testFilterNl2p_withSafeInput()
    {
        $this->assertSame(
            "<p><b>bold</b> &amp;<br />\nline</p>",
            $this->renderTemplate('{{ text|raw|nl2p }}', ['text' => "<b>bold</b> &amp;\nline"])
        );
    }

    public function testFilterNl2p_withoutAutoescape()
    {
        $this->assertSame(
            "<p>&lt;b&gt;bold&lt;/b&gt;<br />\nline</p>",
            $this->renderTemplate('{{ text|nl2p }}', ['text' => "<b>bold</b>\nline"], false)
        );
    }

    public function testFilterDateFormat()
    {
        $core = new Core(new FakeDefaultDirectories(), false);
        $extension = new DefaultExtension($core);

        $this->assertEquals('14/09/2021', $extension->filterDateFormat('2021-09-14 23:00:00'));
        $this->assertEquals(
            '14/09/2021 23:00',
            $extension->filterDateFormat('2021-09-14 23:00:00', 'dd/MM/yyyy HH:mm')
        );
    }

    public function testFilterDateFormat_withDateTimeInterface()
    {
        $core = new Core(new FakeDefaultDirectories(), false);
        $extension = new DefaultExtension($core);

        $this->assertEquals(
            '14/09/2021 23:00',
            $extension->filterDateFormat(new DateTime('2021-09-14 23:00:00'), 'dd/MM/yyyy HH:mm')
        );
        $this->assertEquals(
            '14/09/2021 23:00',
            $extension->filterDateFormat(new DateTimeImmutable('2021-09-14 23:00:00'), 'dd/MM/yyyy HH:mm')
        );
    }

    public function testTestInstanceOf()
    {
        $core = new Core(new FakeDefaultDirectories(), false);
        $extension = new DefaultExtension($core);

        $this->assertTrue($extension->testInstanceOf($core, Core::class));
        $this->assertFalse($extension->testInstanceOf($core, FakeDefaultDirectories::class));
    }
}
