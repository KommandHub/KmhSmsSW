import template from './kmh-sms-credential-check.html.twig';
import './kmh-sms-credential-check.scss';

const { Component } = Shopware;

/**
 * "Check credentials" button inside a provider's card on the plugin settings
 * page, declared in config.xml as `<component name="kmh-sms-credential-check">`
 * with a `<provider>` child naming the provider.
 *
 * It checks what is saved, not what is typed: the provider reads its settings
 * from the system config, so unsaved edits are invisible to it.
 */
Component.register('kmh-sms-credential-check', {
    template,

    inject: ['notificationProviderApiService'],

    props: {
        provider: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            isChecking: false,
            result: null,
        };
    },

    methods: {
        /**
         * The sales channel picked at the top of the settings page.
         *
         * ponytail: sw-system-config exposes it to no child, so this walks up
         * to it. If a Shopware update renames `currentSalesChannelId`, the
         * check silently falls back to "All Sales Channels".
         */
        salesChannelId() {
            let parent = this.$parent;

            while (parent && parent.currentSalesChannelId === undefined) {
                parent = parent.$parent;
            }

            return parent?.currentSalesChannelId ?? null;
        },

        async onCheck() {
            this.isChecking = true;
            this.result = null;

            try {
                this.result = await this.notificationProviderApiService.verify(this.provider, this.salesChannelId());
            } catch {
                this.result = {
                    valid: false,
                    message: this.$t('kmh-sms.credentialCheck.errorUnexpected'),
                    detail: null,
                };
            } finally {
                this.isChecking = false;
            }
        },
    },
});
