<?php
/*
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2026 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Berlioz\Package\Twig\Tests\Debug;

use Berlioz\Core\Core;
use Berlioz\Core\Tests\RestoresErrorHandler;
use Berlioz\Package\Twig\Debug\TwigSection;
use Berlioz\Package\Twig\Extension\DefaultExtension;
use Berlioz\Package\Twig\TestProject\FakeDefaultDirectories;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Profiler\Profile;

class TwigSectionTest extends TestCase
{
    use RestoresErrorHandler;

    public function testGetTemplateName_compilesWithoutDeprecation()
    {
        $section = new TwigSection(new Profile());

        $loader = new FilesystemLoader();
        $loader->addPath(__DIR__ . '/../../../../src/Package/Twig/resources', 'Berlioz-TwigPackage');
        $twig = new Environment($loader);
        $twig->addExtension(new DefaultExtension(new Core(new FakeDefaultDirectories(), false)));

        $deprecations = [];
        set_error_handler(static function (int $severity, string $message) use (&$deprecations): bool {
            if (E_USER_DEPRECATED !== $severity) {
                return false;
            }

            $deprecations[] = $message;

            return true;
        });

        try {
            $twig->compileSource($loader->getSourceContext($section->getTemplateName()));
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $deprecations);
    }
}
