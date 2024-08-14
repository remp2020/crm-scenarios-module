import { store } from '../store';
import { setAvailableBanners } from '../store/bannersSlice';
import { setScenarioLoading } from '../store/scenarioSlice';
import { setCanvasNotification } from '../store/canvasSlice';
import { WidgetsApiService } from '../services';
import { actionWithLoading } from './actionWithLoading';

const {dispatch} = store;

export function fetchBanners() {
  return actionWithLoading(() =>
    WidgetsApiService.getBanners()
      .then(response => {
        dispatch(setAvailableBanners(response.data.banners));
        dispatch(setScenarioLoading(false));
      })
      .catch(() => {
        dispatch(setScenarioLoading(false));
        dispatch(
          setCanvasNotification({
            open: true,
            variant: 'error',
            text: 'Banners fetching failed.'
          })
        );
      })
  )
}
