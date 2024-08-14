import { store } from '../store';
import { setAvailableTriggers } from '../store/triggersSlice';
import { setScenarioLoading } from '../store/scenarioSlice';
import { setCanvasNotification } from '../store/canvasSlice';
import { WidgetsApiService } from '../services';
import { actionWithLoading } from './actionWithLoading';

const {dispatch} = store;

export function fetchTriggers() {
  return actionWithLoading(() =>
    WidgetsApiService.getTriggers()
      .then(response => {
        dispatch(setAvailableTriggers(response.data.events));
        dispatch(setScenarioLoading(false));
      })
      .catch(() => {
        dispatch(setScenarioLoading(false));
        dispatch(
          setCanvasNotification({
            open: true,
            variant: 'error',
            text: 'Triggers fetching failed.'
          })
        );
      })
  )
}
