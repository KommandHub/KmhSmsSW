<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Webhook\Enum;

/**
 * Where a message ended up, in words every provider can be mapped onto.
 *
 * Each provider owns the translation from its own vocabulary; the raw word is
 * kept alongside on the event for anyone who needs the detail.
 */
enum DeliveryStatus: string
{
    case Delivered = 'delivered';
    case Failed = 'failed';
    // Accepted, queued, sent towards the carrier — not yet final.
    case Pending = 'pending';
}
