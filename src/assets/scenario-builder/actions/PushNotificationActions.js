import axios from 'axios';
import * as config from './../config';
import { setScenarioLoading } from './ScenarioActions';
import { setCanvasNotification } from './CanvasActions';

import {PUSH_NOTIFICATIONS_CHANGED} from './types';

export function updateNotifications(templates, applications) {
  return {
    type: PUSH_NOTIFICATIONS_CHANGED,
    payload: {
      templates: templates,
      applications: applications,
    }
  };
}

export function fetchPushNotifications() {
  return dispatch => {

    dispatch(setScenarioLoading(true));

    let requestTemplates = axios.get(`${config.URL_PUSH_NOTIFICATION_TEMPLATES}`);
    let requestApplications = axios.get(`${config.URL_PUSH_NOTIFICATION_APPLICATIONS}`);

    return axios.all([requestTemplates, requestApplications])
      .then(responses => {
        dispatch(updateNotifications(responses[0].data.templates, responses[1].data.applications));
        dispatch(setScenarioLoading(false));
      })
      .catch(error => {
        dispatch(setScenarioLoading(false));
        console.log(error);
        dispatch(
          setCanvasNotification({
            open: true,
            variant: 'error',
            text: 'Notifications fetching failed.'
          })
        );
      });
  };
}
