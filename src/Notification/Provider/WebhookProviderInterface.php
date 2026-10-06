<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Notification\Provider;

use Kommandhub\SmsSW\Webhook\Event\WebhookEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * Implemented by a provider that can post delivery reports or replies back to
 * the shop, at /kmh-sms/webhook/{providerName}.
 *
 * Optional on purpose: a provider without callbacks simply does not implement
 * it, and its webhook URL answers 404. Both halves live with the provider
 * because the signature scheme and the payload shape are vendor knowledge.
 */
interface WebhookProviderInterface
{
    /**
     * Whether the request really came from this provider's account.
     *
     * The security boundary: anything parsed afterwards is trusted. Must return
     * false while the provider's webhook credential is not configured.
     */
    public function verifyWebhook(Request $request, ?string $salesChannelId = null): bool;

    /**
     * Translates an authenticated callback into a provider-neutral event.
     *
     * Null for a callback this plugin has no use for; it is still answered 200
     * so the provider does not retry it.
     */
    public function parseWebhook(Request $request, ?string $salesChannelId = null): ?WebhookEvent;
}
