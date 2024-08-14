import { store } from '../store';
import { setAvailableBeforeTriggers } from '../store/beforeTriggersSlice';
import { setScenarioLoading } from '../store/scenarioSlice';
import { setCanvasNotification } from '../store/canvasSlice';
import { WidgetsApiService } from '../services';
import { actionWithLoading } from './actionWithLoading';

const {dispatch} = store;

export function fetchBeforeTriggers() {
  return actionWithLoading(() =>
    WidgetsApiService.getBeforeTriggers()
      .then(response => {
        dispatch(setAvailableBeforeTriggers(response.data.events));
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
