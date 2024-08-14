import { store } from '../store';
import { setCanvasNotification } from '../store/canvasSlice';
import { setScenarioLoading } from '../store/scenarioSlice';
import { setAvailableGoals } from '../store/goalsSlice';
import { WidgetsApiService } from '../services';
import { actionWithLoading } from './actionWithLoading';

const { dispatch } = store;

export function fetchGoals() {
  return actionWithLoading(() =>
    WidgetsApiService.getGoals()
      .then(response => {
        dispatch(setAvailableGoals(response.data.goals));
        dispatch(setScenarioLoading(false));
      })
      .catch(() => {
        dispatch(setScenarioLoading(false));
        dispatch(
          setCanvasNotification({
            open: true,
            variant: 'error',
            text: 'Goals fetching failed.'
          })
        );
      })
  )
}
