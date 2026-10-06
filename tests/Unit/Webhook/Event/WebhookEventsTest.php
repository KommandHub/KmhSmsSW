<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Tests\Unit\Webhook\Event;

use Kommandhub\SmsSW\Webhook\Enum\DeliveryStatus;
use Kommandhub\SmsSW\Webhook\Event\DeliveryReportEvent;
use Kommandhub\SmsSW\Webhook\Event\InboundEvent;
use Kommandhub\SmsSW\Webhook\Event\WebhookEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WebhookEvent::class)]
#[CoversClass(DeliveryReportEvent::class)]
#[CoversClass(InboundEvent::class)]
class WebhookEventsTest extends TestCase
{
    public function testDeliveryReportEvent(): void
    {
        $event = new DeliveryReportEvent('termii', 'msg-1', DeliveryStatus::Failed, 'Rejected', ['raw' => 1], 'sc-1');

        $this->assertSame('termii', $event->getProviderName());
        $this->assertSame('msg-1', $event->getMessageId());
        $this->assertSame(DeliveryStatus::Failed, $event->getStatus());
        $this->assertSame('Rejected', $event->getProviderStatus());
        $this->assertSame(['raw' => 1], $event->getPayload());
        $this->assertSame('sc-1', $event->getSalesChannelId());
    }

    public function testInboundEvent(): void
    {
        $event = new InboundEvent('twilio', '+2348030000000', 'STOP', []);

        $this->assertSame('twilio', $event->getProviderName());
        $this->assertSame('+2348030000000', $event->getFrom());
        $this->assertSame('STOP', $event->getText());
        $this->assertNull($event->getSalesChannelId());
    }
}
