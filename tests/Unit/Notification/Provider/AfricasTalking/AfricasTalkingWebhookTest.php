<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Tests\Unit\Notification\Provider\AfricasTalking;

use Kommandhub\SmsSW\Notification\Provider\AfricasTalking\AfricasTalkingProvider;
use Kommandhub\SmsSW\Tests\Unit\Notification\Provider\ProviderTestCase;
use Kommandhub\SmsSW\Webhook\Enum\DeliveryStatus;
use Kommandhub\SmsSW\Webhook\Event\DeliveryReportEvent;
use Kommandhub\SmsSW\Webhook\Event\InboundEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpFoundation\Request;

class AfricasTalkingWebhookTest extends ProviderTestCase
{
    public function testTheConfiguredTokenPasses(): void
    {
        $this->assertTrue($this->provider()->verifyWebhook(Request::create('/?token=at-token', 'POST')));
    }

    public function testAWrongOrMissingTokenFails(): void
    {
        $this->assertFalse($this->provider()->verifyWebhook(Request::create('/?token=nope', 'POST')));
        $this->assertFalse($this->provider()->verifyWebhook(Request::create('/', 'POST')));
    }

    /**
     * An empty setting must never match an empty query parameter.
     */
    public function testAnUnconfiguredTokenFails(): void
    {
        $this->assertFalse($this->provider([])->verifyWebhook(Request::create('/?token=', 'POST')));
    }

    /**
     * @return iterable<string, array{string, DeliveryStatus}>
     */
    public static function statuses(): iterable
    {
        yield 'success' => ['Success', DeliveryStatus::Delivered];
        yield 'failed' => ['Failed', DeliveryStatus::Failed];
        yield 'rejected' => ['Rejected', DeliveryStatus::Failed];
        yield 'absent' => ['AbsentSubscriber', DeliveryStatus::Failed];
        yield 'buffered' => ['Buffered', DeliveryStatus::Pending];
    }

    #[DataProvider('statuses')]
    public function testDeliveryReport(string $atStatus, DeliveryStatus $expected): void
    {
        $event = $this->provider()->parseWebhook(Request::create('/', 'POST', ['id' => 'ATXid_1', 'status' => $atStatus, 'phoneNumber' => '+254711000000']));

        $this->assertInstanceOf(DeliveryReportEvent::class, $event);
        $this->assertSame('africasTalking', $event->getProviderName());
        $this->assertSame('ATXid_1', $event->getMessageId());
        $this->assertSame($expected, $event->getStatus());
        $this->assertSame($atStatus, $event->getProviderStatus());
    }

    public function testIncomingMessage(): void
    {
        $event = $this->provider()->parseWebhook(Request::create('/', 'POST', ['from' => '+254711000000', 'text' => 'Thanks']));

        $this->assertInstanceOf(InboundEvent::class, $event);
        $this->assertSame('+254711000000', $event->getFrom());
        $this->assertSame('Thanks', $event->getText());
    }

    public function testAnythingElseIsIgnored(): void
    {
        $this->assertNull($this->provider()->parseWebhook(Request::create('/', 'POST', ['foo' => 'bar'])));
    }

    /**
     * @param array<string, string> $settings
     */
    private function provider(array $settings = ['africasTalkingWebhookToken' => 'at-token']): AfricasTalkingProvider
    {
        return new AfricasTalkingProvider(new MockHttpClient(), $this->config($settings), new NullLogger());
    }
}
