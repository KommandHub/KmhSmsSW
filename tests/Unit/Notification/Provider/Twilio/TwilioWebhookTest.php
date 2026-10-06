<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Tests\Unit\Notification\Provider\Twilio;

use Kommandhub\SmsSW\Notification\Provider\Twilio\TwilioProvider;
use Kommandhub\SmsSW\Tests\Unit\Notification\Provider\ProviderTestCase;
use Kommandhub\SmsSW\Webhook\Enum\DeliveryStatus;
use Kommandhub\SmsSW\Webhook\Event\DeliveryReportEvent;
use Kommandhub\SmsSW\Webhook\Event\InboundEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpFoundation\Request;

class TwilioWebhookTest extends ProviderTestCase
{
    /**
     * The worked example from Twilio's webhook-security documentation, so the
     * algorithm is checked against Twilio's own answer, not against itself.
     */
    private const DOCS_URL = 'https://mycompany.com/myapp.php?foo=1&bar=2';

    private const DOCS_PARAMS = [
        'CallSid' => 'CA1234567890ABCDE',
        'Caller' => '+12349013030',
        'Digits' => '1234',
        'From' => '+12349013030',
        'To' => '+18005551212',
    ];

    private const DOCS_SIGNATURE = '0/KCTR6DLpKmkAf8muzZqo1nDgQ=';

    public function testTwilioDocumentedSignatureVerifies(): void
    {
        $this->assertTrue($this->provider()->verifyWebhook($this->docsRequest(self::DOCS_SIGNATURE)));
    }

    public function testAChangedParameterFails(): void
    {
        $request = $this->docsRequest(self::DOCS_SIGNATURE);
        $request->request->set('Digits', '9999');

        $this->assertFalse($this->provider()->verifyWebhook($request));
    }

    public function testMissingSignatureOrTokenFails(): void
    {
        $this->assertFalse($this->provider()->verifyWebhook($this->docsRequest('')));
        $this->assertFalse($this->provider([])->verifyWebhook($this->docsRequest(self::DOCS_SIGNATURE)));
    }

    /**
     * @return iterable<string, array{string, DeliveryStatus}>
     */
    public static function statuses(): iterable
    {
        yield 'delivered' => ['delivered', DeliveryStatus::Delivered];
        yield 'undelivered' => ['undelivered', DeliveryStatus::Failed];
        yield 'failed' => ['failed', DeliveryStatus::Failed];
        yield 'sent' => ['sent', DeliveryStatus::Pending];
    }

    #[DataProvider('statuses')]
    public function testStatusCallbackBecomesADeliveryReport(string $twilioStatus, DeliveryStatus $expected): void
    {
        $event = $this->provider()->parseWebhook(
            Request::create('/', 'POST', ['MessageSid' => 'SM1', 'MessageStatus' => $twilioStatus]),
            'sc-1',
        );

        $this->assertInstanceOf(DeliveryReportEvent::class, $event);
        $this->assertSame('twilio', $event->getProviderName());
        $this->assertSame('SM1', $event->getMessageId());
        $this->assertSame($expected, $event->getStatus());
        $this->assertSame($twilioStatus, $event->getProviderStatus());
    }

    public function testIncomingMessageBecomesAnInboundEvent(): void
    {
        $event = $this->provider()->parseWebhook(Request::create('/', 'POST', ['From' => '+2348030000000', 'Body' => 'STOP']));

        $this->assertInstanceOf(InboundEvent::class, $event);
        $this->assertSame('+2348030000000', $event->getFrom());
        $this->assertSame('STOP', $event->getText());
    }

    public function testAnythingElseIsIgnored(): void
    {
        $this->assertNull($this->provider()->parseWebhook(Request::create('/', 'POST', ['CallSid' => 'CA1'])));
    }

    /**
     * @param array<string, string> $settings
     */
    private function provider(array $settings = ['twilioAuthToken' => '12345']): TwilioProvider
    {
        return new TwilioProvider(new MockHttpClient(), $this->config($settings), new NullLogger());
    }

    private function docsRequest(string $signature): Request
    {
        $request = Request::create(self::DOCS_URL, 'POST', self::DOCS_PARAMS);
        $request->headers->set('X-Twilio-Signature', $signature);

        return $request;
    }
}
