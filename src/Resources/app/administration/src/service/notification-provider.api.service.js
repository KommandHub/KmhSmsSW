const ApiService = Shopware.Classes.ApiService;

/**
 * Client for the plugin's provider endpoints.
 */
export default class NotificationProviderApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'kmh-sms') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'notificationProviderApiService';
    }

    /**
     * Checks the provider's saved credentials against the provider itself.
     *
     * @param {string} providerName
     * @param {string|null} salesChannelId
     * @returns {Promise<{valid: boolean, message: string, detail: ?string}>}
     */
    verify(providerName, salesChannelId = null) {
        return this.httpClient
            .post(
                `/_action/kmh-sms/provider/${providerName}/verify`,
                { salesChannelId },
                { headers: this.getBasicHeaders() },
            )
            .then((response) => ApiService.handleResponse(response));
    }
}
