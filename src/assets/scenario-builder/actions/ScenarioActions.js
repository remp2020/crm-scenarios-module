import { store } from '../store';
import { setScenarioId, setScenarioLoading, setScenarioName, setScenarioPayload } from '../store/scenarioSlice';
import { setCanvasNotification } from '../store/canvasSlice';
import { ScenarioApiService } from '../services';
import { actionWithLoading } from './actionWithLoading';

const { dispatch } = store;

export function fetchScenario(scenarioId) {
  return actionWithLoading(() =>
    ScenarioApiService.getScenario(scenarioId)
      .then(response => {
        dispatch(setScenarioPayload(response.data));
        dispatch(setScenarioName(response.data.name));
        dispatch(setScenarioId(response.data.id));
        dispatch(setScenarioLoading(false));
      })
      .catch(() => {
        dispatch(setScenarioLoading(false));
        dispatch(
          setCanvasNotification({
            open: true,
            variant: 'error',
            text: 'Scenario fetching failed.'
          })
        );
      })
  )
}
