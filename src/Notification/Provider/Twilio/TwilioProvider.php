<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Notification\Provider\Twilio;

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
 * Twilio — global fallback.
 *
 * Settings (see config.xml):
 * - `twilioAccountSid`          — account SID, "AC…"
 * - `twilioAuthToken`           — auth token
 * - `twilioFrom`                — sending number in E.164, e.g. "+15005550006"
 * - `twilioMessagingServiceSid` — optional "MG…"; takes precedence over `from`
 *                                 and lets Twilio pick the sender from a pool
 *
 * Declares no country codes on purpose: it is the route of last resort, so it
 * accepts anything and lets the regional providers win where they apply.
 *
 * Quirks contained here: HTTP basic auth, a form-encoded body with capitalised
 * field names, and an account SID embedded in the URL path.
 */
class TwilioProvider extends AbstractHttpNotificationProvider implements WebhookProviderInterface
{
    private const BASE_URL = 'https://api.twilio.com';

    private const API_VERSION = '2010-04-01';

    /**
     * Twilio statuses that mean the message was taken but not yet delivered.
     * Anything else in a 2xx is a message that will never leave.
     */
    private const ACCEPTED_STATUSES = ['queued', 'accepted', 'sending', 'sent', 'scheduled'];

    public function getName(): string
    {
        return 'twilio';
    }

    public function getLabel(): string
    {
        return 'Twilio';
    }

    public function isConfigured(?string $salesChannelId = null): bool
    {
        return $this->setting('accountSid', $salesChannelId) !== ''
            && $this->setting('authToken', $salesChannelId) !== ''
            && $this->sender($salesChannelId) !== [];
    }

    public function send(MessageRequest $request): MessageResult
    {
        $salesChannelId = $request->getSalesChannelId();
        $accountSid = $this->requireSetting('accountSid', $salesChannelId);
        $sender = $this->sender($salesChannelId);

        if ($sender === []) {
            throw new PermanentProviderException('Twilio needs either a sending number or a messaging service SID.');
        }

        $decoded = $this->requestJson(
            'POST',
            sprintf('%s/%s/Accounts/%s/Messages.json', self::BASE_URL, self::API_VERSION, urlencode($accountSid)),
            [
                'auth_basic' => [$accountSid, $this->requireSetting('authToken', $salesChannelId)],
                'body' => [
                    'To' => '+' . $request->getRecipient(),
                    'Body' => $request->getBody(),
                ] + $sender,
            ],
            $salesChannelId,
        );

        $status = $decoded['status'] ?? null;

        // Twilio can return 201 with a status of "failed" when the message was
        // created but immediately rejected downstream.
        if (\is_string($status) && !\in_array($status, self::ACCEPTED_STATUSES, true)) {
            throw new PermanentProviderException(sprintf(
                'Twilio created the message but its status is "%s": %s',
                $status,
                $this->describe($decoded),
            ));
        }

        $sid = $decoded['sid'] ?? null;

        return new MessageResult($this->getName(), \is_string($sid) ? $sid : null, $decoded);
    }

    public function verifyCredentials(?string $salesChannelId = null): CredentialCheck
    {
        $accountSid = $this->setting('accountSid', $salesChannelId);
        $authToken = $this->setting('authToken', $salesChannelId);

        if ($accountSid === '' || $authToken === '') {
            return CredentialCheck::invalid('Twilio needs both an account SID and an auth token.');
        }

        try {
            $decoded = $this->requestJson(
                'GET',
                sprintf('%s/%s/Accounts/%s.json', self::BASE_URL, self::API_VERSION, urlencode($accountSid)),
                ['auth_basic' => [$accountSid, $authToken]],
                $salesChannelId,
            );
        } catch (SmsException $exception) {
            return CredentialCheck::invalid('Twilio rejected these credentials.', $exception->getMessage());
        }

        $status = $decoded['status'] ?? null;

        if ($status === 'suspended' || $status === 'closed') {
            return CredentialCheck::invalid(sprintf('The Twilio account is %s.', (string)$status));
        }

        if ($this->sender($salesChannelId) === []) {
            return CredentialCheck::invalid('Credentials work, but no sending number or messaging service is set.');
        }

        return CredentialCheck::valid('Twilio credentials accepted.');
    }

    /**
     * Twilio signs every callback with the account's auth token: HMAC-SHA1
     * over the exact URL it called followed by each POST field name and value,
     * sorted by name, base64-encoded in `X-Twilio-Signature`.
     *
     * ponytail: the URL is rebuilt from the request, so behind a TLS-terminating
     * proxy Shopware's trusted-proxy settings must be right or every callback
     * fails verification.
     */
    public function verifyWebhook(Request $request, ?string $salesChannelId = null): bool
    {
        $authToken = $this->setting('authToken', $salesChannelId);
        $signature = (string)$request->headers->get('x-twilio-signature', '');

        if ($authToken === '' || $signature === '') {
            return false;
        }

        $params = $request->request->all();
        ksort($params, \SORT_STRING);

        $data = $request->getSchemeAndHttpHost() . $request->getRequestUri();

        foreach ($params as $name => $value) {
            $data .= $name . (\is_scalar($value) ? (string)$value : '');
        }

        return hash_equals(base64_encode(hash_hmac('sha1', $data, $authToken, true)), $signature);
    }

    /**
     * A status callback carries `MessageStatus`; an incoming message carries
     * `Body`. Twilio only sends status callbacks to a messaging service with a
     * status callback URL configured, or to one set per message.
     */
    public function parseWebhook(Request $request, ?string $salesChannelId = null): ?WebhookEvent
    {
        $params = $request->request->all();
        $status = self::stringField($params, 'MessageStatus');

        if ($status !== null) {
            return new DeliveryReportEvent($this->getName(), self::stringField($params, 'MessageSid'), match ($status) {
                'delivered', 'read' => DeliveryStatus::Delivered,
                'undelivered', 'failed', 'canceled' => DeliveryStatus::Failed,
                default => DeliveryStatus::Pending,
            }, $status, $params, $salesChannelId);
        }

        if (isset($params['Body'])) {
            return new InboundEvent($this->getName(), self::stringField($params, 'From'), self::stringField($params, 'Body'), $params, $salesChannelId);
        }

        return null;
    }

    /**
     * The sender half of the payload.
     *
     * A messaging service wins over a fixed number: merchants who configure one
     * are opting into Twilio's own sender selection, and sending both is an
     * error on Twilio's side.
     *
     * @return array<string, string> empty when neither is configured
     */
    private function sender(?string $salesChannelId): array
    {
        $messagingServiceSid = $this->setting('messagingServiceSid', $salesChannelId);

        if ($messagingServiceSid !== '') {
            return ['MessagingServiceSid' => $messagingServiceSid];
        }

        $from = $this->setting('from', $salesChannelId);

        return $from !== '' ? ['From' => $from] : [];
    }

    /**
     * @throws PermanentProviderException
     */
    private function requireSetting(string $key, ?string $salesChannelId): string
    {
        $value = $this->setting($key, $salesChannelId);

        if ($value === '') {
            throw new PermanentProviderException(sprintf('Twilio is missing the "%s" setting.', $key));
        }

        return $value;
    }
}
