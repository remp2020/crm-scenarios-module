import { store } from '../store';
import { setCriteria } from '../store/criteriaSlice';
import { setScenarioLoading } from '../store/scenarioSlice';
import { setCanvasNotification } from '../store/canvasSlice';
import { WidgetsApiService } from '../services';
import { actionWithLoading } from './actionWithLoading';

const {dispatch} = store;

export function fetchCriteria() {
  return actionWithLoading(() =>
    WidgetsApiService.getCriteria()
      .then(response => {
        dispatch(setCriteria(response.data.blueprint));
        dispatch(setScenarioLoading(false));
      })
      .catch(() => {
        dispatch(setScenarioLoading(false));
        dispatch(
          setCanvasNotification({
            open: true,
            variant: 'error',
            text: 'Criteria fetching failed.'
          })
        );
      })
  )
}
