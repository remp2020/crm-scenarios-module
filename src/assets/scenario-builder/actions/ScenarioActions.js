import axios from 'axios';
import {
  SET_SCENARIO_ID,
  SET_SCENARIO_NAME,
  SET_SCENARIO_LOADING,
  SET_SCENARIO_PAYLOAD
} from './types';
import * as config from '../config';
import { setCanvasNotification } from './CanvasActions';

export function setScenarioId(id) {
  return {
    type: SET_SCENARIO_ID,
    payload: id
  };
}

export function setScenarioName(name) {
  return {
    type: SET_SCENARIO_NAME,
    payload: name
  };
}

export function setScenarioPayload(payload) {
  return {
    type: SET_SCENARIO_PAYLOAD,
    payload
  };
}

export function setScenarioLoading(loading) {
  return {
    type: SET_SCENARIO_LOADING,
    payload: loading
  };
}

export function fetchScenario(scenarioId) {
  return dispatch => {
    dispatch(setScenarioLoading(true));
    return axios
      .get(config.URL_SCENARIO_DETAIL + scenarioId)
      .then(response => {
        dispatch(setScenarioPayload(response.data));
        dispatch(setScenarioName(response.data.name));
        dispatch(setScenarioId(response.data.id));
        dispatch(setScenarioLoading(false));
      })
      .catch(error => {
        dispatch(setScenarioLoading(false));
        dispatch(
          setCanvasNotification({
            open: true,
            variant: 'error',
            text: 'Scenario fetching failed.'
          })
        );
        console.log(error);
      });
  };
}
