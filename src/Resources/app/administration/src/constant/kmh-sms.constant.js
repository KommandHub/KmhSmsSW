/**
 * The flow action's technical name, mirroring SendSmsAction::getName() on the
 * PHP side. The two must stay identical: the server registers the action under
 * this name and the administration derives the modal component name, icon key
 * and label key from it.
 *
 * Derivations the flow builder performs on this string (see core's
 * flow-builder.service.ts):
 * - modal component:  action.kmh.send.sms -> sw-flow-kmh-send-sms-modal
 * - icon/label key:   kmhSendSms
 */
export const SEND_SMS_ACTION = 'action.kmh.send.sms';

/**
 * Key the icon and label are registered under — the service builds it from the
 * action name segments after "action.", camel-cased.
 */
export const SEND_SMS_ACTION_KEY = 'kmhSendSms';
