import axios from 'axios';
import * as config from './../config';
import { setScenarioLoading } from './ScenarioActions';
import { setCanvasNotification } from './CanvasActions';

import { GENERICS_CHANGED } from './types';

export function updateGenerics(generics) {
  return {
    type: GENERICS_CHANGED,
    payload: generics
  };
}

export function fetchGenerics() {
  return dispatch => {
    dispatch(setScenarioLoading(true));
    return axios
      .get(`${config.URL_GENERICS_INDEX}`)
      .then(response => {
        dispatch(updateGenerics(response.data));
        dispatch(setScenarioLoading(false));
      })
      .catch(error => {
        dispatch(setScenarioLoading(false));
        console.log(error);
        dispatch(
          setCanvasNotification({
            open: true,
            variant: 'error',
            text: 'Generics fetching failed.'
          })
        );
      });
  };
}
