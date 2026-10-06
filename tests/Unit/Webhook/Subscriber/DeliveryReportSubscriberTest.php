<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Tests\Unit\Webhook\Subscriber;

use Kommandhub\SmsSW\Webhook\Enum\DeliveryStatus;
use Kommandhub\SmsSW\Webhook\Event\DeliveryReportEvent;
use Kommandhub\SmsSW\Webhook\Event\WebhookEvent;
use Kommandhub\SmsSW\Webhook\Subscriber\DeliveryReportSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(DeliveryReportSubscriber::class)]
#[UsesClass(DeliveryReportEvent::class)]
#[UsesClass(WebhookEvent::class)]
class DeliveryReportSubscriberTest extends TestCase
{
    public function testItListensForDeliveryReports(): void
    {
        $this->assertSame(['onDeliveryReport'], array_values(DeliveryReportSubscriber::getSubscribedEvents()));
        $this->assertArrayHasKey(DeliveryReportEvent::class, DeliveryReportSubscriber::getSubscribedEvents());
    }

    public function testItLogsTheNeutralAndTheProviderStatus(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('Notification delivery report received', [
            'provider' => 'twilio',
            'messageId' => 'SM1',
            'status' => 'failed',
            'providerStatus' => 'undelivered',
            'salesChannelId' => 'sc-1',
        ]);

        (new DeliveryReportSubscriber($logger))->onDeliveryReport(
            new DeliveryReportEvent('twilio', 'SM1', DeliveryStatus::Failed, 'undelivered', [], 'sc-1'),
        );
    }
}
