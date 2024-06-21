// window.Scenario = {
//   config: {
//     AUTH_TOKEN: '',
//     CRM_HOST: 'https://predplatne.dennikn.sk',
//     SCENARIO_ID: null,
//     BANNER_ENABLED: null,
//     PUSH_NOTIFICATION_ENABLED: null,
//   }
// };

export const { AUTH_TOKEN, CRM_HOST, SCENARIO_ID, BANNER_ENABLED, PUSH_NOTIFICATION_ENABLED } = window.Scenario.config;

export const URL_SCENARIO_DETAIL = `${CRM_HOST}/api/v1/scenarios/info?id=`;
export const URL_SCENARIO_CREATE = `${CRM_HOST}/api/v1/scenarios/create`;
export const URL_SCENARIO_CRITERIA = `${CRM_HOST}/api/v1/scenarios/criteria`;
export const URL_SEGMENTS_INDEX = `${CRM_HOST}/api/v1/segments/list`;
export const URL_TRIGGERS_INDEX = `${CRM_HOST}/api/v1/events/list`;
export const URL_BEFORE_TRIGGERS_INDEX = `${CRM_HOST}/api/v1/event-generators/list`;
export const URL_MAILS_INDEX = `${CRM_HOST}/api/v1/mail-template/list`;
export const URL_GENERICS_INDEX = `${CRM_HOST}/api/v1/scenarios/generics`;
export const URL_GOALS_INDEX = `${CRM_HOST}/api/v1/onboarding-goals/list`;
export const URL_BANNERS_INDEX = `${CRM_HOST}/api/v1/remp/list-banners`;
export const URL_PUSH_NOTIFICATION_TEMPLATES = `${CRM_HOST}/api/v1/onesignal-templates/list`;
export const URL_PUSH_NOTIFICATION_APPLICATIONS = `${CRM_HOST}/api/v1/onesignal-applications/list`;
export const URL_SCENARIO_STATISTIC = `${CRM_HOST}/api/v1/scenarios/stats?id=`;

export const URL_SEGMENT_NEW = `${CRM_HOST}/segment/stored-segments/new`;
export const URL_SEGMENT_SHOW = `${CRM_HOST}/segment/stored-segments/show/`;