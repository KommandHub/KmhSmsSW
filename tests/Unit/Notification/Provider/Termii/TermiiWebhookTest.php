<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Tests\Unit\Notification\Provider\Termii;

use Kommandhub\SmsSW\Notification\Provider\Termii\TermiiProvider;
use Kommandhub\SmsSW\Tests\Unit\Notification\Provider\ProviderTestCase;
use Kommandhub\SmsSW\Webhook\Enum\DeliveryStatus;
use Kommandhub\SmsSW\Webhook\Event\DeliveryReportEvent;
use Kommandhub\SmsSW\Webhook\Event\InboundEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpFoundation\Request;

class TermiiWebhookTest extends ProviderTestCase
{
    private const SECRET = 'termii-secret';

    public function testAValidSignaturePasses(): void
    {
        $body = '{"type":"outbound"}';

        $this->assertTrue($this->provider()->verifyWebhook($this->signed($body, hash_hmac('sha512', $body, self::SECRET))));
    }

    public function testATamperedBodyFails(): void
    {
        $signature = hash_hmac('sha512', '{"type":"outbound"}', self::SECRET);

        $this->assertFalse($this->provider()->verifyWebhook($this->signed('{"type":"inbound"}', $signature)));
    }

    public function testAMissingSignatureFails(): void
    {
        $this->assertFalse($this->provider()->verifyWebhook(new Request(content: '{}')));
    }

    public function testAnUnconfiguredSecretFailsEvenWithAMatchingSignature(): void
    {
        $body = '{}';
        $provider = $this->provider([]);

        $this->assertFalse($provider->verifyWebhook($this->signed($body, hash_hmac('sha512', $body, ''))));
    }

    /**
     * @return iterable<string, array{string, DeliveryStatus}>
     */
    public static function statuses(): iterable
    {
        yield 'delivered' => ['DELIVERED', DeliveryStatus::Delivered];
        yield 'failed' => ['Message Failed', DeliveryStatus::Failed];
        yield 'undelivered is not delivered' => ['Undelivered', DeliveryStatus::Failed];
        yield 'rejected' => ['Rejected', DeliveryStatus::Failed];
        yield 'expired' => ['Expired', DeliveryStatus::Failed];
        yield 'sent is pending' => ['Message Sent', DeliveryStatus::Pending];
    }

    #[DataProvider('statuses')]
    public function testOutboundBecomesADeliveryReport(string $termiiStatus, DeliveryStatus $expected): void
    {
        $event = $this->provider()->parseWebhook(
            new Request(content: json_encode(['type' => 'outbound', 'message_id' => 'msg-1', 'status' => $termiiStatus], \JSON_THROW_ON_ERROR)),
            'sc-1',
        );

        $this->assertInstanceOf(DeliveryReportEvent::class, $event);
        $this->assertSame('termii', $event->getProviderName());
        $this->assertSame('msg-1', $event->getMessageId());
        $this->assertSame($expected, $event->getStatus());
        $this->assertSame($termiiStatus, $event->getProviderStatus());
        $this->assertSame('sc-1', $event->getSalesChannelId());
    }

    public function testDndIsAFailedDelivery(): void
    {
        $event = $this->provider()->parseWebhook(new Request(content: '{"type":"dnd","message_id":"msg-2"}'));

        $this->assertInstanceOf(DeliveryReportEvent::class, $event);
        $this->assertSame(DeliveryStatus::Failed, $event->getStatus());
        $this->assertSame('dnd', $event->getProviderStatus());
    }

    public function testInboundIsAnInboundEvent(): void
    {
        $this->assertInstanceOf(InboundEvent::class, $this->provider()->parseWebhook(new Request(content: '{"type":"inbound"}')));
    }

    public function testUnknownTypesAndNonJsonAreIgnored(): void
    {
        $this->assertNull($this->provider()->parseWebhook(new Request(content: '{"type":"weird"}')));
        $this->assertNull($this->provider()->parseWebhook(new Request(content: 'not json')));
    }

    /**
     * @param array<string, string> $settings
     */
    private function provider(array $settings = ['termiiWebhookSecret' => self::SECRET]): TermiiProvider
    {
        return new TermiiProvider(new MockHttpClient(), $this->config($settings), new NullLogger());
    }

    private function signed(string $body, string $signature): Request
    {
        $request = new Request(content: $body);
        $request->headers->set('X-Termii-Signature', $signature);

        return $request;
    }
}
