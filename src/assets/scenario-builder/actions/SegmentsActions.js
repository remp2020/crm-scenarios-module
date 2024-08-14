import { store } from '../store';
import { setAvailableSegments } from '../store/segmentsSlice';
import { setScenarioLoading } from '../store/scenarioSlice';
import { setCanvasNotification } from '../store/canvasSlice';
import { WidgetsApiService } from '../services';
import { actionWithLoading } from './actionWithLoading';

const { dispatch } = store;

export function fetchSegments() {
  return actionWithLoading(() =>
    WidgetsApiService.getSegments()
      .then(response => {
        dispatch(setAvailableSegments(response.data.result));
        dispatch(setScenarioLoading(false));
      })
      .catch(() => {
        dispatch(setScenarioLoading(false));
        dispatch(
          setCanvasNotification({
            open: true,
            variant: 'error',
            text: 'Segments fetching failed.'
          })
        );
      })
  )
}
