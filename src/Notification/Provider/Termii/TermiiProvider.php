<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Notification\Provider\Termii;

use Kommandhub\SmsSW\Exception\SmsException;
use Kommandhub\SmsSW\Exception\PermanentProviderException;
use Kommandhub\SmsSW\Notification\Provider\AbstractHttpNotificationProvider;
use Kommandhub\SmsSW\Notification\Provider\Struct\CredentialCheck;
use Kommandhub\SmsSW\Notification\Provider\Struct\MessageRequest;
use Kommandhub\SmsSW\Notification\Provider\Struct\MessageResult;
use Kommandhub\SmsSW\Notification\Provider\WebhookProviderInterface;
use Kommandhub\SmsSW\Webhook\Enum\DeliveryStatus;
use Kommandhub\SmsSW\Webhook\Event\DeliveryReportEvent;
use Kommandhub\SmsSW\Webhook\Event\InboundEvent;
use Kommandhub\SmsSW\Webhook\Event\WebhookEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * Termii — West Africa, Nigeria-first, direct carrier routing.
 *
 * Settings (see config.xml):
 * - `termiiApiKey`   — API key from the Termii dashboard
 * - `termiiSenderId` — approved alphanumeric sender
 * - `termiiRoute`    — carrier route for SMS: "generic" or "dnd"
 * - `termiiBaseUrl`  — optional override; Termii has region-specific hosts
 *
 * Termii calls its carrier route a "channel", which is not what this plugin
 * means by the word — hence the setting name `termiiRoute`. Values are
 * "generic" and "dnd"; the latter reaches Nigerian numbers on the
 * do-not-disturb list at a different price.
 *
 * Two Termii quirks, both contained in this class: the API key travels in the
 * JSON body rather than a header, and a refusal is reported as HTTP 200 with an
 * error body and no message id.
 */
class TermiiProvider extends AbstractHttpNotificationProvider implements WebhookProviderInterface
{
    private const DEFAULT_BASE_URL = 'https://v3.api.termii.com';

    /**
     * Nigeria, Ghana, Côte d'Ivoire, Senegal, Kenya — the markets Termii routes
     * directly. Anything else still works, it is simply not preferred.
     */
    private const COUNTRY_CODES = ['234', '233', '225', '221', '254'];

    private const ROUTE_DEFAULT = 'generic';

    public function getName(): string
    {
        return 'termii';
    }

    public function getLabel(): string
    {
        return 'Termii';
    }

    protected function getCountryCodes(): array
    {
        return self::COUNTRY_CODES;
    }

    public function isConfigured(?string $salesChannelId = null): bool
    {
        return $this->setting('apiKey', $salesChannelId) !== ''
            && $this->setting('senderId', $salesChannelId) !== '';
    }

    public function send(MessageRequest $request): MessageResult
    {
        $salesChannelId = $request->getSalesChannelId();

        $decoded = $this->requestJson('POST', $this->baseUrl($salesChannelId) . '/api/sms/send', [
            'json' => [
                'to' => $request->getRecipient(),
                'from' => $this->requireSetting('senderId', $salesChannelId),
                'sms' => $request->getBody(),
                'type' => 'plain',
                'channel' => $this->route($request->getSalesChannelId()),
                'api_key' => $this->requireSetting('apiKey', $salesChannelId),
            ],
        ], $salesChannelId);

        $messageId = $decoded['message_id'] ?? null;

        // Termii answers 200 even when it refuses, so the status code alone
        // does not tell us whether anything was sent — the message id does.
        if (!\is_string($messageId) || $messageId === '') {
            throw new PermanentProviderException(sprintf('Termii refused the message: %s', $this->describe($decoded)));
        }

        return new MessageResult($this->getName(), $messageId, $decoded);
    }

    public function verifyCredentials(?string $salesChannelId = null): CredentialCheck
    {
        if ($this->setting('apiKey', $salesChannelId) === '') {
            return CredentialCheck::invalid('No Termii API key configured.');
        }

        try {
            $decoded = $this->requestJson('GET', $this->baseUrl($salesChannelId) . '/api/get-balance', [
                'query' => ['api_key' => $this->setting('apiKey', $salesChannelId)],
            ], $salesChannelId);
        } catch (SmsException $exception) {
            return CredentialCheck::invalid('Termii rejected these credentials.', $exception->getMessage());
        }

        if (!isset($decoded['balance'])) {
            return CredentialCheck::invalid('Termii did not return a balance.', $this->describe($decoded));
        }

        $balance = $decoded['balance'];

        if ($this->setting('senderId', $salesChannelId) === '') {
            return CredentialCheck::invalid('API key works, but no sender ID is configured.');
        }

        return CredentialCheck::valid(sprintf('Termii credentials accepted. Balance: %s', \is_scalar($balance) ? (string)$balance : 'unknown'));
    }

    /**
     * The carrier route Termii should use.
     *
     * A stale "whatsapp" from when this plugin still offered that channel would
     * route every SMS over WhatsApp, so it is ignored rather than trusted.
     * The config carry-over migration drops it too; this is the second guard.
     */
    /**
     * Termii signs the raw body with HMAC-SHA512 using the account's secret
     * key and sends the hex digest in `X-Termii-Signature`.
     */
    public function verifyWebhook(Request $request, ?string $salesChannelId = null): bool
    {
        $secret = $this->setting('webhookSecret', $salesChannelId);
        $signature = (string)$request->headers->get('x-termii-signature', '');

        if ($secret === '' || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $request->getContent(), $secret), $signature);
    }

    /**
     * Termii discriminates its callbacks with `type`: "outbound" is a delivery
     * report, "dnd" means the number is on Nigeria's do-not-disturb list and the
     * standard route could not reach it, "inbound" is a reply.
     */
    public function parseWebhook(Request $request, ?string $salesChannelId = null): ?WebhookEvent
    {
        $payload = json_decode($request->getContent(), true);

        if (!\is_array($payload)) {
            return null;
        }

        $status = self::stringField($payload, 'status');

        return match ($payload['type'] ?? null) {
            'outbound' => new DeliveryReportEvent($this->getName(), self::stringField($payload, 'message_id'), self::deliveryStatus($status), $status, $payload, $salesChannelId),
            'dnd' => new DeliveryReportEvent($this->getName(), self::stringField($payload, 'message_id'), DeliveryStatus::Failed, $status ?? 'dnd', $payload, $salesChannelId),
            // ponytail: Termii's inbound field names are not documented
            // reliably; sender and text stay null until verified against a live
            // callback. The payload carries everything.
            'inbound' => new InboundEvent($this->getName(), null, null, $payload, $salesChannelId),
            default => null,
        };
    }

    /**
     * Termii's statuses are free-text ("DELIVERED", "Message Failed",
     * "Rejected", "Expired", "DND Active on Phone Number", …), so match on the
     * meaningful word. Failure words first: "undelivered" contains "deliver".
     */
    private static function deliveryStatus(?string $status): DeliveryStatus
    {
        $status = strtolower((string)$status);

        foreach (['undeliver', 'fail', 'reject', 'expire', 'dnd'] as $word) {
            if (str_contains($status, $word)) {
                return DeliveryStatus::Failed;
            }
        }

        return str_contains($status, 'deliver') ? DeliveryStatus::Delivered : DeliveryStatus::Pending;
    }

    private function route(?string $salesChannelId): string
    {
        $configured = $this->setting('route', $salesChannelId);

        return $configured === '' || $configured === 'whatsapp' ? self::ROUTE_DEFAULT : $configured;
    }

    private function baseUrl(?string $salesChannelId): string
    {
        return rtrim($this->setting('baseUrl', $salesChannelId) ?: self::DEFAULT_BASE_URL, '/');
    }

    /**
     * @throws PermanentProviderException
     */
    private function requireSetting(string $key, ?string $salesChannelId): string
    {
        $value = $this->setting($key, $salesChannelId);

        if ($value === '') {
            throw new PermanentProviderException(sprintf('Termii is missing the "%s" setting.', $key));
        }

        return $value;
    }
}
