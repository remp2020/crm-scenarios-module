import axios from 'axios';
import * as config from './../config';
import { setScenarioLoading } from './ScenarioActions';
import { setCanvasNotification } from './CanvasActions';

import { TRIGGERS_CHANGED } from './types';

export function updateTriggers(triggers) {
  return {
    type: TRIGGERS_CHANGED,
    payload: triggers
  };
}

export function fetchTriggers() {
  return dispatch => {
    dispatch(setScenarioLoading(true));
    return axios
      .get(`${config.URL_TRIGGERS_INDEX}`)
      .then(response => {
        dispatch(updateTriggers(response.data.events));
        dispatch(setScenarioLoading(false));
      })
      .catch(error => {
        dispatch(setScenarioLoading(false));
        console.log(error);
        dispatch(
          setCanvasNotification({
            open: true,
            variant: 'error',
            text: 'Triggers fetching failed.'
          })
        );
      });
  };
}
