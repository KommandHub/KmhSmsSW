<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Webhook\Controller;

use Kommandhub\SmsSW\Notification\Provider\NotificationProviderRegistry;
use Kommandhub\SmsSW\Notification\Provider\WebhookProviderInterface;
use Psr\Log\LoggerInterface;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public endpoint providers post delivery reports and replies to.
 *
 * Knows no vendor: the provider named in the path verifies and parses its own
 * callback. Deliberately generous with 200s once a request is authentic —
 * answering 4xx/5xx for an event we simply ignore makes the provider retry it
 * indefinitely.
 *
 * `csrf_protected: false` is required — the caller is a server, not a browser
 * session. `auth_required: false` likewise: authenticity is the provider's
 * signature (or token) check.
 */
#[Route(defaults: ['_routeScope' => ['storefront'], 'csrf_protected' => false, 'auth_required' => false])]
class WebhookController extends StorefrontController
{
    public function __construct(
        private readonly NotificationProviderRegistry $registry,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(
        path: '/kmh-sms/webhook/{providerName}',
        name: 'kmh_sms_webhook',
        requirements: ['providerName' => '[A-Za-z]+'],
        methods: ['POST'],
    )]
    public function handle(string $providerName, Request $request): Response
    {
        $salesChannelId = $request->attributes->getString('sw-sales-channel-id') ?: null;

        $provider = $this->registry->has($providerName) ? $this->registry->get($providerName) : null;

        if (!$provider instanceof WebhookProviderInterface) {
            throw new NotFoundHttpException(sprintf('No webhook for provider "%s".', $providerName));
        }

        if (!$provider->verifyWebhook($request, $salesChannelId)) {
            throw new AccessDeniedHttpException('Webhook could not be verified.');
        }

        $event = $provider->parseWebhook($request, $salesChannelId);

        if ($event === null) {
            $this->logger->info('Ignoring unhandled SMS webhook', [
                'provider' => $providerName,
                'salesChannelId' => $salesChannelId,
            ]);

            return new JsonResponse(['status' => 'ignored'], Response::HTTP_OK);
        }

        $this->eventDispatcher->dispatch($event);

        return new JsonResponse(['status' => 'ok'], Response::HTTP_OK);
    }
}
