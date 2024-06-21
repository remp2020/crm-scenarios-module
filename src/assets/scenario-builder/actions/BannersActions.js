import axios from 'axios';
import * as config from '../config';
import { setScenarioLoading } from './ScenarioActions';
import { setCanvasNotification } from './CanvasActions';
import { BANNERS_CHANGED } from './types';

export function updateBanners(banners) {
  return {
    type: BANNERS_CHANGED,
    payload: banners
  };
}

export function fetchBanners() {
  return dispatch => {
    dispatch(setScenarioLoading(true));
    return axios
      .get(config.URL_BANNERS_INDEX)
      .then(response => {
        dispatch(updateBanners(response.data.banners));
        dispatch(setScenarioLoading(false));
      })
      .catch(error => {
        dispatch(setScenarioLoading(false));
        console.log(error);
        dispatch(
          setCanvasNotification({
            open: true,
            variant: 'error',
            text: 'Banners fetching failed.'
          })
        );
      });
  };
}
