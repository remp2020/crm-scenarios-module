import axios from 'axios';
import * as config from './../config';
import { setScenarioLoading } from './ScenarioActions';
import { setCanvasNotification } from './CanvasActions';

import { BEFORE_TRIGGERS_CHANGED } from './types';

export function updateBeforeTriggers(beforeTriggers) {
  return {
    type: BEFORE_TRIGGERS_CHANGED,
    payload: beforeTriggers
  };
}

export function fetchBeforeTriggers() {
  return dispatch => {
    dispatch(setScenarioLoading(true));
    return axios
      .get(`${config.URL_BEFORE_TRIGGERS_INDEX}`)
      .then(response => {
        dispatch(updateBeforeTriggers(response.data.events));
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
