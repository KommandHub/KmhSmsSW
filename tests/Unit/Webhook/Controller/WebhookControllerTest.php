<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Tests\Unit\Webhook\Controller;

use Kommandhub\SmsSW\Notification\Provider\NotificationProviderInterface;
use Kommandhub\SmsSW\Notification\Provider\NotificationProviderRegistry;
use Kommandhub\SmsSW\Notification\Provider\WebhookProviderInterface;
use Kommandhub\SmsSW\Webhook\Controller\WebhookController;
use Kommandhub\SmsSW\Webhook\Enum\DeliveryStatus;
use Kommandhub\SmsSW\Webhook\Event\DeliveryReportEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[CoversClass(WebhookController::class)]
#[UsesClass(NotificationProviderRegistry::class)]
#[UsesClass(DeliveryReportEvent::class)]
class WebhookControllerTest extends TestCase
{
    public function testAVerifiedCallbackIsDispatched(): void
    {
        $event = new DeliveryReportEvent('hooked', 'msg-1', DeliveryStatus::Delivered, 'DELIVERED', [], 'sc-1');
        $dispatcher = new EventDispatcher();
        $received = [];
        $dispatcher->addListener(DeliveryReportEvent::class, static function (DeliveryReportEvent $e) use (&$received): void {
            $received[] = $e;
        });

        $provider = $this->webhookProvider(verified: true, event: $event);
        $response = $this->controller($provider, $dispatcher)->handle('hooked', $this->request());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"status":"ok"}', $response->getContent());
        $this->assertSame([$event], $received);
    }

    public function testTheSalesChannelReachesTheProvider(): void
    {
        $provider = $this->webhookProvider(verified: true, event: null);
        $provider->expects($this->once())->method('verifyWebhook')->with($this->anything(), 'sc-1')->willReturn(true);

        $this->controller($provider)->handle('hooked', $this->request());
    }

    public function testAnUnverifiedCallbackIsRejectedBeforeParsing(): void
    {
        $provider = $this->webhookProvider(verified: false, event: null);
        $provider->expects($this->never())->method('parseWebhook');

        $this->expectException(AccessDeniedHttpException::class);

        $this->controller($provider)->handle('hooked', $this->request());
    }

    public function testAnIgnoredCallbackIsStillAnswered200(): void
    {
        $response = $this->controller($this->webhookProvider(verified: true, event: null))->handle('hooked', $this->request());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"status":"ignored"}', $response->getContent());
    }

    public function testAProviderWithoutWebhooksIs404(): void
    {
        $plain = $this->createMock(NotificationProviderInterface::class);
        $plain->method('getName')->willReturn('plain');

        $this->expectException(NotFoundHttpException::class);

        (new WebhookController(new NotificationProviderRegistry([$plain]), new EventDispatcher(), new NullLogger()))
            ->handle('plain', $this->request());
    }

    public function testAnUnknownProviderIs404(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller($this->webhookProvider(verified: true, event: null))->handle('nobody', $this->request());
    }

    private function webhookProvider(bool $verified, ?DeliveryReportEvent $event): NotificationProviderInterface&WebhookProviderInterface&\PHPUnit\Framework\MockObject\MockObject
    {
        $provider = $this->createMockForIntersectionOfInterfaces([NotificationProviderInterface::class, WebhookProviderInterface::class]);
        $provider->method('getName')->willReturn('hooked');
        $provider->method('verifyWebhook')->willReturn($verified);
        $provider->method('parseWebhook')->willReturn($event);

        return $provider;
    }

    private function controller(NotificationProviderInterface $provider, ?EventDispatcher $dispatcher = null): WebhookController
    {
        return new WebhookController(new NotificationProviderRegistry([$provider]), $dispatcher ?? new EventDispatcher(), new NullLogger());
    }

    private function request(): Request
    {
        return new Request(attributes: ['sw-sales-channel-id' => 'sc-1'], content: '{}');
    }
}
