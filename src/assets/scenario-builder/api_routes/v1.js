import * as config from '../config'

export const scenario = {
  detail: (id) => `${config.CRM_HOST}/api/v1/scenarios/info?id=${id}`,
  create: `${config.CRM_HOST}/api/v1/scenarios/create`,
  criteria: `${config.CRM_HOST}/api/v1/scenarios/criteria`,
  statistics: (id) => `${config.CRM_HOST}/api/v1/scenarios/stats?id=${id}`,
}
export const segments = {
  index: `${config.CRM_HOST}/api/v1/segments/list`,
  new: `${config.CRM_HOST}/segment/stored-segments/new`,
  show: (id) => `${config.CRM_HOST}/segment/stored-segments/show/${id}`
}
export const triggers = {
  index: `${config.CRM_HOST}/api/v1/events/list`
};
export const before_triggers = {
  index: `${config.CRM_HOST}/api/v1/event-generators/list`
};
export const mails = {
  index: `${config.CRM_HOST}/api/v1/mail-template/list`
};
export const generics = {
  index: `${config.CRM_HOST}/api/v1/scenarios/generics`
};
export const goals = {
  index: `${config.CRM_HOST}/api/v1/onboarding-goals/list`
};
export const banners = {
  index: `${config.CRM_HOST}/api/v1/remp/list-banners`
};
export const pushNotifications = {
  templates: `${config.CRM_HOST}/api/v1/onesignal-templates/list`,
  applications: `${config.CRM_HOST}/api/v1/onesignal-applications/list`,
}
