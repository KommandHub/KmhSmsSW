import template from './sw-flow-kmh-send-sms-modal.html.twig';
import './sw-flow-kmh-send-sms-modal.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { ShopwareError } = Shopware.Classes;

/**
 * Configuration modal for the Send SMS flow action.
 *
 * The component name is not free to choose: the flow builder derives it from
 * the action name (`action.kmh.send.sms` ->
 * `sw-flow-kmh-send-sms-modal`), so renaming either side silently breaks
 * the modal open.
 *
 * The saved config is `{ templateId, templateName }`. SendSmsAction::handleFlow()
 * reads only templateId; the name is for the sequence card.
 */
Component.register('sw-flow-kmh-send-sms-modal', {
    template,

    inject: ['repositoryFactory'],

    mixins: [Mixin.getByName('notification')],

    emits: ['process-finish', 'modal-close'],

    props: {
        sequence: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            templateId: null,
            templateError: null,
        };
    },

    computed: {
        /**
         * Inactive templates are selectable on purpose: wiring a flow before
         * switching the template on is the normal order of work, and the action
         * skips inactive templates at send time with a log line.
         */
        templateCriteria() {
            const criteria = new Criteria(1, 500);
            criteria.addSorting(Criteria.sort('name', 'ASC'));

            return criteria;
        },
    },

    watch: {
        templateId(value) {
            if (value && this.templateError) {
                this.templateError = null;
            }
        },
    },

    created() {
        this.templateId = this.sequence?.config?.templateId ?? null;
    },

    methods: {
        async onSave() {
            if (!this.templateId) {
                this.templateError = new ShopwareError({
                    code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                });

                return;
            }

            // The name is a display snapshot for the sequence card only; the
            // PHP side reads templateId and ignores it.
            // ponytail: goes stale if the template is renamed; resolve live in
            // the description callback if merchants rename often.
            const template = await this.repositoryFactory
                .create('kmh_sms_template')
                .get(this.templateId, Shopware.Context.api);

            this.$emit('process-finish', {
                ...this.sequence,
                config: { templateId: this.templateId, templateName: template?.name ?? null },
            });
            this.onClose();
        },

        onClose() {
            this.templateError = null;
            this.$emit('modal-close');
        },
    },
});
