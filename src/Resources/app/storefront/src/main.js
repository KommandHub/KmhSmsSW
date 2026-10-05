const PluginManager = window.PluginManager;

// Lazily imported so the bundle only loads on pages that render a phone field.
PluginManager.register(
    'KmhDialCodePhone',
    () => import('./dial-code-phone/dial-code-phone.plugin'),
    '[data-kmh-dial-code-phone]'
);
