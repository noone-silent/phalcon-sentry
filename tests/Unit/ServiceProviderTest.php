<?php

declare(strict_types=1);

namespace Unit;

use Codeception\Test\Unit;
use Phalcon\Config\Adapter\Ini;
use Phalcon\Config\ConfigInterface;
use Phalcon\Di\FactoryDefault;
use Phalcon\Sentry\ServiceProvider;
use Throwable;

class ServiceProviderTest extends Unit
{
    /**
     * @covers \Phalcon\Sentry\ServiceProvider::__construct
     * @covers \Phalcon\Sentry\ServiceProvider::boot
     * @covers \Phalcon\Sentry\ServiceProvider::mergeConfig
     *
     * @throws Throwable
     * @return void
     */
    public function testConstructFromFile()
    {
        $di = new FactoryDefault();
        $sp = new ServiceProvider(
            dirname(__DIR__) . '/config/files/sentry.ini'
        );
        $sp->register($di);

        $config = $di->getShared('phalcon-sentry.config');

        // Assert we have a valid config
        $this->assertInstanceOf(ConfigInterface::class, $config);
        // Assert that we merged all values
        $this->assertEquals('test', $config->path('test.value'));
        // Assert that the default config got merged and is available
        $this->assertEquals(
            'sha384-EdTlDs1y0B2z6oDPxEhsi9MkH/ilAGCs4oLmreRceSbJ2TlSjo5020c315FWNIYJ',
            $config->path('sentry.browser.sha')
        );
    }

    /**
     * @covers \Phalcon\Sentry\ServiceProvider::__construct
     * @covers \Phalcon\Sentry\ServiceProvider::boot
     * @covers \Phalcon\Sentry\ServiceProvider::mergeConfig
     *
     * @throws Throwable
     * @return void
     */
    public function testConstructFromConfig()
    {
        $config = new Ini(dirname(__DIR__) . '/config/files/sentry.ini');
        $di     = new FactoryDefault();
        $sp     = new ServiceProvider($config);
        $sp->register($di);

        $config = $di->getShared('phalcon-sentry.config');

        // Assert we have a valid config
        $this->assertInstanceOf(ConfigInterface::class, $config);
        // Assert that we merged all values
        $this->assertEquals('test', $config->path('test.value'));
        // Assert that the default config got merged and is available
        $this->assertEquals(
            'sha384-EdTlDs1y0B2z6oDPxEhsi9MkH/ilAGCs4oLmreRceSbJ2TlSjo5020c315FWNIYJ',
            $config->path('sentry.browser.sha')
        );
    }
}
