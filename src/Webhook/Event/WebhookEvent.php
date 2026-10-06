<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Webhook\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for every authenticated provider callback.
 *
 * Provider-neutral: a provider translates its own callback into one of the
 * subclasses, so subscribers never learn which vendor sent it beyond the name.
 */
abstract class WebhookEvent extends Event
{
    /**
     * @param array<string, mixed> $payload the callback as the provider sent it
     */
    public function __construct(
        private readonly string $providerName,
        private readonly array $payload,
        private readonly ?string $salesChannelId = null,
    ) {
    }

    public function getProviderName(): string
    {
        return $this->providerName;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }
}
