import axios from 'axios';
import * as config from '../config';
import { setScenarioLoading } from './ScenarioActions';
import { setCanvasNotification } from './CanvasActions';
import { CRITERIA_CHANGED } from './types';

export function updateCriteria(criteria) {
  return {
    type: CRITERIA_CHANGED,
    payload: criteria
  };
}

export function fetchCriteria() {
  return dispatch => {
    dispatch(setScenarioLoading(true));
    return axios
      .get(config.URL_SCENARIO_CRITERIA)
      .then(response => {
        dispatch(updateCriteria(response.data.blueprint));
        dispatch(setScenarioLoading(false));
      })
      .catch(error => {
        console.log(error);
        dispatch(setScenarioLoading(false));
        dispatch(
          setCanvasNotification({
            open: true,
            variant: 'error',
            text: 'Criteria fetching failed.'
          })
        );
      });
  };
}
