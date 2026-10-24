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

namespace Berlioz\Form\Tests\DataProvider;

use Berlioz\Form\DataProvider\ApiDataProvider;
use Berlioz\Form\Form;
use Berlioz\Form\Group;
use Berlioz\Form\Type\Text;
use Berlioz\Http\Message\ServerRequest;
use Berlioz\Http\Message\Stream\MemoryStream;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

class ApiDataProviderTest extends TestCase
{
    private function getRequest(string $json, string $method = 'POST'): ServerRequest
    {
        $body = new MemoryStream();
        $body->write($json);

        return new ServerRequest($method, '/', ['Content-Type' => 'application/json'], body: $body);
    }

    /**
     * Create provider and return the triggered deprecation message, if any.
     */
    private function createProvider(?ApiDataProvider &$provider, mixed ...$args): ?string
    {
        $deprecation = null;
        set_error_handler(function (int $errno, string $errstr) use (&$deprecation): bool {
            if (E_USER_DEPRECATED !== $errno) {
                return false;
            }

            $deprecation = $errstr;

            return true;
        });

        try {
            $provider = new ApiDataProvider(...$args);
        } finally {
            restore_error_handler();
        }

        return $deprecation;
    }

    public static function provideDeprecatedArguments(): array
    {
        return [
            'default' => [[]],
            'null' => [['strictJson' => null]],
            'false' => [['strictJson' => false]],
            'with map' => [['mapToElement' => 'foo']],
        ];
    }

    #[DataProvider('provideDeprecatedArguments')]
    public function testConstruct_deprecated(array $args): void
    {
        $deprecation = $this->createProvider($provider, ...$args);

        $this->assertNotNull($deprecation);
        $this->assertStringContainsString('strictJson: true', $deprecation);
    }

    public function testConstruct_strictJson(): void
    {
        $this->assertNull($this->createProvider($provider, strictJson: true));
    }

    public function testHandle_legacyConvertsNestedObjectsToArrays(): void
    {
        $this->createProvider($provider);

        $data = $provider->handle(
            $this->getRequest('{"name": "foo", "attributes": {}, "list": [], "map": {"0": "a"}}'),
            new Form('foo'),
        );

        $this->assertSame(['name' => 'foo', 'attributes' => [], 'list' => [], 'map' => ['a']], $data);
    }

    public function testHandle_strictJsonPreservesJsonTypes(): void
    {
        $this->createProvider($provider, strictJson: true);

        $data = $provider->handle(
            $this->getRequest('{"name": "foo", "attributes": {}, "list": [], "map": {"0": "a"}}'),
            new Form('foo'),
        );

        $this->assertSame(['name', 'attributes', 'list', 'map'], array_keys($data));
        $this->assertSame('foo', $data['name']);
        $this->assertEquals(new stdClass(), $data['attributes']);
        $this->assertSame([], $data['list']);
        $this->assertEquals((object)['0' => 'a'], $data['map']);
    }

    public function testHandle_strictJsonWithMapToElement(): void
    {
        $this->createProvider($provider, mapToElement: 'payload', strictJson: true);

        $data = $provider->handle($this->getRequest('{"attributes": {}}'), new Form('foo'));

        $this->assertEquals(['payload' => ['attributes' => new stdClass()]], $data);
    }

    public static function provideNonSubmittableBodies(): array
    {
        return [
            'null' => [null],
            'scalar' => ['foo'],
        ];
    }

    #[DataProvider('provideNonSubmittableBodies')]
    public function testHandle_nonSubmittableBody(mixed $parsedBody): void
    {
        $this->createProvider($provider, strictJson: true);

        $this->assertSame(
            [],
            $provider->handle($this->getRequest('null')->withParsedBody($parsedBody), new Form('foo')),
        );
    }

    public function testHandle_ignoredMethod(): void
    {
        $this->createProvider($provider, strictJson: true);

        $this->assertFalse($provider->handle($this->getRequest('{}', 'GET'), new Form('foo')));
    }

    public function testFormHandle_strictJsonWithGroup(): void
    {
        $this->createProvider($provider, strictJson: true);

        $form = new Form('foo', options: ['dataProvider' => $provider]);
        $form
            ->add('name', Text::class)
            ->add('attributes', Text::class)
            ->add('address', (new Group())->add('city', Text::class));

        $form->handle($this->getRequest('{"name": "foo", "attributes": {}, "address": {"city": "Paris"}}'));

        $this->assertTrue($form->isSubmitted());
        $this->assertSame('foo', $form['name']->getValue());
        $this->assertEquals(new stdClass(), $form['attributes']->getValue());
        $this->assertSame('Paris', $form['address']['city']->getValue());
    }
}
