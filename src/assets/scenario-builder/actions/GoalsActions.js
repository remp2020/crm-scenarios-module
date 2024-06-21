import axios from 'axios';
import * as config from './../config';
import { setScenarioLoading } from './ScenarioActions';
import { setCanvasNotification } from './CanvasActions';
import { GOALS_CHANGED } from './types';

export function updateGoals(goals) {
  return {
    type: GOALS_CHANGED,
    payload: goals
  };
}

export function fetchGoals() {
  return dispatch => {
    dispatch(setScenarioLoading(true));
    return axios
      .get(config.URL_GOALS_INDEX)
      .then(response => {
        dispatch(updateGoals(response.data.goals));
        dispatch(setScenarioLoading(false));
      })
      .catch(error => {
        dispatch(setScenarioLoading(false));
        console.log(error);
        dispatch(
          setCanvasNotification({
            open: true,
            variant: 'error',
            text: 'Goals fetching failed.'
          })
        );
      });
  };
}
