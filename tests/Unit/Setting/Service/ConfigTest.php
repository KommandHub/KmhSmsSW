<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Tests\Unit\Setting\Service;

use Kommandhub\SmsSW\Setting\Service\Config;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ConfigTest extends TestCase
{
    private SystemConfigService&MockObject $systemConfigService;
    private Config $config;

    protected function setUp(): void
    {
        $this->systemConfigService = $this->createMock(SystemConfigService::class);
        $this->config = new Config($this->systemConfigService);
    }

    public function testGet(): void
    {
        $this->systemConfigService->expects($this->once())
            ->method('get')
            ->with('KmhSmsSW.config.someKey', 'sales-channel-id')
            ->willReturn('someValue');

        $result = $this->config->get('someKey', null, 'sales-channel-id');
        $this->assertSame('someValue', $result);
    }

    public function testGetWithDefault(): void
    {
        $this->systemConfigService->expects($this->once())
            ->method('get')
            ->with('KmhSmsSW.config.missingKey', null)
            ->willReturn(null);

        $result = $this->config->get('missingKey', 'defaultValue');
        $this->assertSame('defaultValue', $result);
    }

    public function testGetString(): void
    {
        $this->systemConfigService->expects($this->once())
            ->method('get')
            ->with('KmhSmsSW.config.stringKey', null)
            ->willReturn('stringValue');

        $result = $this->config->getString('stringKey');
        $this->assertSame('stringValue', $result);
    }

    public function testGetStringWithNonString(): void
    {
        $this->systemConfigService->expects($this->once())
            ->method('get')
            ->with('KmhSmsSW.config.nonStringKey', null)
            ->willReturn(123);

        $result = $this->config->getString('nonStringKey');
        $this->assertSame('', $result);
    }

    public function testGetBool(): void
    {
        $this->systemConfigService->expects($this->once())
            ->method('getBool')
            ->with('KmhSmsSW.config.boolKey', 'sales-channel-id')
            ->willReturn(true);

        $result = $this->config->getBool('boolKey', 'sales-channel-id');
        $this->assertTrue($result);
    }

    public function testGetArray(): void
    {
        $expectedArray = ['val1', 'val2'];
        $this->systemConfigService->expects($this->once())
            ->method('get')
            ->with('KmhSmsSW.config.arrayKey', null)
            ->willReturn($expectedArray);

        $result = $this->config->getArray('arrayKey');
        $this->assertSame($expectedArray, $result);
    }

    public function testGetArrayWithNonArray(): void
    {
        $this->systemConfigService->expects($this->once())
            ->method('get')
            ->with('KmhSmsSW.config.nonArrayKey', null)
            ->willReturn('not-an-array');

        $result = $this->config->getArray('nonArrayKey');
        $this->assertSame([], $result);
    }
}
