import axios from 'axios';
import * as config from './../config';
import { setScenarioLoading } from './ScenarioActions';
import { setCanvasNotification } from './CanvasActions';

import { MAILS_CHANGED } from './types';

export function updateMails(mails) {
  return {
    type: MAILS_CHANGED,
    payload: mails
  };
}

export function fetchMails() {
  return dispatch => {
    dispatch(setScenarioLoading(true));
    return axios
      .get(`${config.URL_MAILS_INDEX}`)
      .then(response => {
        dispatch(updateMails(response.data.mail_templates));
        dispatch(setScenarioLoading(false));
      })
      .catch(error => {
        dispatch(setScenarioLoading(false));
        console.log(error);
        dispatch(
          setCanvasNotification({
            open: true,
            variant: 'error',
            text: 'Mails fetching failed.'
          })
        );
      });
  };
}
