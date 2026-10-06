<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Webhook\Event;

use Kommandhub\SmsSW\Webhook\Enum\DeliveryStatus;

/**
 * What happened to a message after the provider accepted it.
 */
class DeliveryReportEvent extends WebhookEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        string $providerName,
        private readonly ?string $messageId,
        private readonly DeliveryStatus $status,
        private readonly ?string $providerStatus,
        array $payload,
        ?string $salesChannelId = null,
    ) {
        parent::__construct($providerName, $payload, $salesChannelId);
    }

    /**
     * Correlates back to the id the provider returned when the message was
     * sent, which the send log line records.
     */
    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    public function getStatus(): DeliveryStatus
    {
        return $this->status;
    }

    /**
     * The provider's own word for the status, e.g. "undelivered" — kept for
     * support, never for branching.
     */
    public function getProviderStatus(): ?string
    {
        return $this->providerStatus;
    }
}
