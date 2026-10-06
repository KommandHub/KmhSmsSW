<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Webhook\Event;

/**
 * A shopper replied to one of the plugin's messages.
 *
 * Nothing consumes this in v1 — it exists so an inbound reply is a typed event
 * a merchant's own subscriber can listen on rather than an ignored callback.
 * Sender and text are null when the provider's callback does not name them;
 * the payload still carries everything it sent.
 */
class InboundEvent extends WebhookEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        string $providerName,
        private readonly ?string $from,
        private readonly ?string $text,
        array $payload,
        ?string $salesChannelId = null,
    ) {
        parent::__construct($providerName, $payload, $salesChannelId);
    }

    public function getFrom(): ?string
    {
        return $this->from;
    }

    public function getText(): ?string
    {
        return $this->text;
    }
}
