<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Tests\Unit\Notification\Service;

use Kommandhub\SmsSW\Notification\Service\SampleVariableFactory;
use PHPUnit\Framework\TestCase;

class SampleVariableFactoryTest extends TestCase
{
    public function testOrderCarriesTheBuyerLikeALiveOrderEvent(): void
    {
        $variables = (new SampleVariableFactory())->build();

        static::assertSame('Jane', $variables['order']['orderCustomer']['firstName']);
        static::assertSame($variables['customer'], $variables['order']['orderCustomer']);
    }
}
