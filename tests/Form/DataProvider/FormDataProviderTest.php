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

use Berlioz\Form\DataProvider\FormDataProvider;
use Berlioz\Form\Form;
use Berlioz\Http\Message\ServerRequest;
use Berlioz\Http\Message\Stream\MemoryStream;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FormDataProviderTest extends TestCase
{
    public static function provideBodyMethods(): array
    {
        return [
            'DELETE' => ['DELETE'],
            'PATCH' => ['PATCH'],
            'POST' => ['POST'],
            'PUT' => ['PUT'],
            'QUERY' => ['QUERY'],
        ];
    }

    #[DataProvider('provideBodyMethods')]
    public function testHandle_bodyMethods(string $method): void
    {
        $body = new MemoryStream();
        $body->write('foo%5Bname%5D=bar');
        $request = new ServerRequest(
            $method,
            '/?foo%5Bname%5D=query',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            body: $body,
        );

        $this->assertSame(['name' => 'bar'], (new FormDataProvider())->handle($request, new Form('foo')));
    }

    public function testHandle_getMethod(): void
    {
        $request = new ServerRequest('GET', '/?foo%5Bname%5D=query');

        $this->assertSame(['name' => 'query'], (new FormDataProvider())->handle($request, new Form('foo')));
    }
}
