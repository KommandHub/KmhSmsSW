import './page/sms-template-list';
import './page/sms-template-detail';


const { Module } = Shopware;

/**
 * SMS templates: one message body per Shopware mail template type.
 *
 * Registered as a settings item rather than a top-level menu entry — this is
 * shop configuration a merchant visits occasionally, and it belongs beside the
 * mail templates it parallels rather than competing with Orders and Products
 * in the main navigation.
 *
 * `settingsItem.group` only accepts Shopware's own groups ('shop', 'system',
 * 'plugins'); 'plugins' is where third-party settings live, and the shared
 * "Notifications" grouping comes from both modules using the same group and a
 * common label prefix.
 */
Module.register('kmh-sms-template', {
    type: 'plugin',
    name: 'kmh-sms-template',
    title: 'kmh-sms-template.general.mainMenuItemGeneral',
    description: 'kmh-sms-template.general.descriptionTextModule',
    color: '#37d046',
    icon: 'regular-comments',
    favicon: 'icon-module-settings.png',
    entity: 'kmh_sms_template',

    routes: {
        index: {
            component: 'kmh-sms-template-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index.plugins',
                privilege: 'sms.manage',
            },
        },
        detail: {
            component: 'kmh-sms-template-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'kmh.sms.template.index',
                privilege: 'sms.manage',
            },
            props: {
                default: (route) => ({ templateId: route.params.id }),
            },
        },
        create: {
            component: 'kmh-sms-template-detail',
            path: 'create',
            meta: {
                parentPath: 'kmh.sms.template.index',
                privilege: 'sms.manage',
            },
        },
    },

    settingsItem: [
        {
            group: 'plugins',
            to: 'kmh.sms.template.index',
            icon: 'regular-comments',
            name: 'kmh-sms-template',
            label: 'kmh-sms-template.general.mainMenuItemGeneral',
            privilege: 'sms.manage',
        },
    ],
});
