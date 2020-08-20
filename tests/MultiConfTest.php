<?php

use PHPUnit\Framework\TestCase;

class MultiConfTest extends TestCase
{
    /** @var EveInUa\MultiConf\Config */
    private $multiConf;

    protected function setUp()
    {
        $libraryDirParts = explode('/', __DIR__);
        array_pop($libraryDirParts);
        $libraryDirPath = implode('/', $libraryDirParts);

        if (!defined('CONFIG_ROOT')) {
            define('CONFIG_ROOT', $libraryDirPath);
        }
        if (!defined('ENV_ROOT')) {
            define('ENV_ROOT', $libraryDirPath);
        }

        $this->multiConf = new EveInUa\MultiConf\Config();
    }

    protected function tearDown()
    {
        unset($this->multiConf);
    }

    public function testEnv()
    {
        $ENV = $this->multiConf->env();
        $DB_HOST = $this->multiConf->env('DB_HOST');
        $DB_NAME = $this->multiConf->env('DB_NAME');
        $this->assertSame('DEV', $ENV);
        $this->assertSame('localhost', $DB_HOST);
        $this->assertSame('project', $DB_NAME);
    }

    public function testConfig()
    {

        $configAsFile = include CONFIG_ROOT . '/config/example.php';
        $configDefaultAsFile = include CONFIG_ROOT . '/config/example.default.php';

        $this->assertSame(
            $configAsFile[0],
            $this->multiConf->config('example.0')
        );
        $this->assertSame(
            $configAsFile['foo'],
            $this->multiConf->config('example.foo')
        );
        $this->assertSame(
            $configAsFile['zoo'][0],
            $this->multiConf->config('example.zoo.0')
        );
        $this->assertSame(
            $configDefaultAsFile['zoo']['buzz'],
            $this->multiConf->config('example.zoo.buzz')
        );
        $this->assertSame(
            $configDefaultAsFile['def'],
            $this->multiConf->config('example.def')
        );
    }

    public function testEnvException()
    {
        $this->expectException(\Exception::class);
        $this->multiConf->env('failed');
    }

    public function testConfigException()
    {
        $this->expectException(\Exception::class);
        $this->multiConf->config('failed');
        $this->multiConf->config('example.failed');
    }

    public function testConfigDefaultOnly()
    {

        $configDefaultAsFile = include CONFIG_ROOT . '/config/example2.default.php';

        $this->assertSame(
            $configDefaultAsFile['foo'],
            $this->multiConf->config('example2.foo')
        );
        $this->assertSame(
            $configDefaultAsFile['bar'],
            $this->multiConf->config('example2.bar')
        );
    }

    public function testEnvJson()
    {
        $SOME_JSON = $this->multiConf->env('SOME_JSON');
        $this->assertSame(['foo', 'bar'], $SOME_JSON);
    }

    public function testEnvJsonException()
    {
        $SOME_JSON = $this->multiConf->env('SOME_JSON');
        $this->assertNotSame(['foo', 'baz'], $SOME_JSON);
    }
}
