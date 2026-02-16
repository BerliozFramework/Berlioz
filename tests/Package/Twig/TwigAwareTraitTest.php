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

namespace Berlioz\Package\Twig\Tests;

use Berlioz\Core\Core;
use Berlioz\Core\Tests\RestoresErrorHandler;
use Berlioz\Package\Twig\Exception\TwigException;
use Berlioz\Package\Twig\TestProject\FakeDefaultDirectories;
use Berlioz\Package\Twig\Twig;
use Berlioz\Package\Twig\TwigAwareTrait;
use PHPUnit\Framework\TestCase;

class TwigAwareTraitTest extends TestCase
{
    use RestoresErrorHandler;

    private function createTwigAwareObject(): object
    {
        return new class {
            use TwigAwareTrait;
        };
    }

    public function test()
    {
        $object = $this->createTwigAwareObject();

        $this->assertNull($object->getTwig());

        $twig = new Twig(new Core(new FakeDefaultDirectories(), false));
        $object->setTwig($twig);

        $this->assertSame($object->getTwig(), $twig);
    }

    public function testRender()
    {
        $object = $this->createTwigAwareObject();

        $twig = new Twig(
            new Core(new FakeDefaultDirectories(), false),
            [
                'foo' => realpath(__DIR__ . '/tests_env/resources/templates/foo'),
                'bar' => realpath(__DIR__ . '/tests_env/resources/templates/bar'),
            ],
        );
        $object->setTwig($twig);

        $this->assertSame(
            $twig->render('@bar/bar.html.twig'),
            $object->render('@bar/bar.html.twig')
        );
    }

    public function testRender_notInit()
    {
        $this->expectException(TwigException::class);

        $object = $this->createTwigAwareObject();
        $object->render('@bar/bar.html.twig');
    }

    public function testRenderBlock()
    {
        $object = $this->createTwigAwareObject();

        $twig = new Twig(
            new Core(new FakeDefaultDirectories(), false),
            [
                'foo' => realpath(__DIR__ . '/tests_env/resources/templates/foo'),
                'bar' => realpath(__DIR__ . '/tests_env/resources/templates/bar'),
            ],
        );
        $object->setTwig($twig);

        $this->assertSame(
            $twig->renderBlock('@bar/bar.html.twig', 'bar'),
            $object->renderBlock('@bar/bar.html.twig', 'bar')
        );
    }

    public function testRenderBlock_notInit()
    {
        $this->expectException(TwigException::class);

        $object = $this->createTwigAwareObject();
        $object->renderBlock('@bar/bar.html.twig', 'bar');
    }
}
