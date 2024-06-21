import axios from 'axios';
import * as config from './../config';
import { setScenarioLoading } from './ScenarioActions';
import { setCanvasNotification } from './CanvasActions';

import { SEGMENTS_CHANGED } from './types';

export function updateSegments(segments) {
  return {
    type: SEGMENTS_CHANGED,
    payload: segments
  };
}

export function fetchSegments() {
  return dispatch => {
    dispatch(setScenarioLoading(true));
    return axios
      .get(`${config.URL_SEGMENTS_INDEX}`)
      .then(response => {
        dispatch(updateSegments(response.data.result));
        dispatch(setScenarioLoading(false));
      })
      .catch(error => {
        console.log(error);
        dispatch(setScenarioLoading(false));
        dispatch(
          setCanvasNotification({
            open: true,
            variant: 'error',
            text: 'Segments fetching failed.'
          })
        );
      });
  };
}
