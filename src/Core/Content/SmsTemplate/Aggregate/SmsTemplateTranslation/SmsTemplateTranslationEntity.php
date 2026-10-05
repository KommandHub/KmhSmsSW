<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Core\Content\SmsTemplate\Aggregate\SmsTemplateTranslation;

use Kommandhub\SmsSW\Core\Content\SmsTemplate\SmsTemplateEntity;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

class SmsTemplateTranslationEntity extends TranslationEntity
{
    /**
     * Named after the parent entity, which the DAL derives from
     * `kmh_sms_template` — the prefix is part of the property name, not
     * decoration that can be trimmed.
     */
    protected string $kmhSmsTemplateId;

    protected string $name;

    protected string $content;

    protected ?SmsTemplateEntity $smsTemplate = null;

    public function getKmhSmsTemplateId(): string
    {
        return $this->kmhSmsTemplateId;
    }

    public function setKmhSmsTemplateId(string $kmhSmsTemplateId): void
    {
        $this->kmhSmsTemplateId = $kmhSmsTemplateId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getSmsTemplate(): ?SmsTemplateEntity
    {
        return $this->smsTemplate;
    }

    public function setSmsTemplate(?SmsTemplateEntity $smsTemplate): void
    {
        $this->smsTemplate = $smsTemplate;
    }
}
