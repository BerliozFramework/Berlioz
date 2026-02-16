<?php

namespace Berlioz\Package\Hector\Tests;

use Berlioz\Core\Core;
use Berlioz\Core\Debug\Snapshot\Section;
use Berlioz\Core\Tests\RestoresErrorHandler;
use Berlioz\Package\Hector\BerliozPackage;
use Berlioz\Package\Hector\Debug\HectorSection;
use Berlioz\Package\Hector\TestProject\TestEnvDirectories;
use Hector\Connection\Connection;
use Hector\Orm\Orm;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class BerliozPackageTest extends TestCase
{
    use RestoresErrorHandler;

    protected function setUp(): void
    {
        FakeOrm::reset();
        parent::setUp();
    }

    public function testConfig()
    {
        $configFromPackage = BerliozPackage::config()->getArrayCopy();

        $this->assertArrayHasKey('hector', $configFromPackage);
        $this->assertArrayHasKey('dsn', $configFromPackage['hector']);
    }

    public function testRegister()
    {
        $core = new Core(new TestEnvDirectories(), cache: false);
        $core->getContainer()->autoWiring(false);

        $this->assertFalse($core->getContainer()->has(Connection::class));
        $this->assertFalse($core->getContainer()->has(Orm::class));
        $this->assertFalse($core->getContainer()->has('dbConnection'));
        $this->assertFalse($core->getContainer()->has('orm'));

        BerliozPackage::register($core->getContainer());

        $this->assertTrue($core->getContainer()->has(Connection::class));
        $this->assertTrue($core->getContainer()->has(Orm::class));
        $this->assertTrue($core->getContainer()->has('dbConnection'));
        $this->assertTrue($core->getContainer()->has('orm'));
    }

    public function testBoot()
    {
        $core = new Core(new TestEnvDirectories(), cache: false);
        $debugHandler = $core->getDebug();
        BerliozPackage::register($core->getContainer());
        BerliozPackage::boot($core);

        $this->assertSame(Orm::get(), $core->getContainer()->get(Orm::class));

        $reflection = new ReflectionProperty($debugHandler, 'sections');
        $reflection->setAccessible(true);
        $sections = $reflection->getValue($debugHandler);
        $sections = array_filter($sections, fn(Section $section) => $section instanceof HectorSection);

        $this->assertCount(0, $sections);
    }

    public function testBoot_withDebug()
    {
        $core = new Core(new TestEnvDirectories(), cache: false);
        $debugHandler = $core->getDebug();
        $debugHandler->setEnabled(true);
        BerliozPackage::register($core->getContainer());
        BerliozPackage::boot($core);

        $this->assertSame(Orm::get(), $core->getContainer()->get(Orm::class));

        $reflection = new ReflectionProperty($debugHandler, 'sections');
        $reflection->setAccessible(true);
        $sections = $reflection->getValue($debugHandler);
        $sections = array_filter($sections, fn(Section $section) => $section instanceof HectorSection);

        $this->assertCount(1, $sections);
    }
}
