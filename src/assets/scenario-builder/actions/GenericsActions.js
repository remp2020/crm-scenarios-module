import { setGenerics } from '../store/genericsSlice';
import { setScenarioLoading } from '../store/scenarioSlice';
import { setCanvasNotification } from '../store/canvasSlice';
import { WidgetsApiService } from '../services';
import { store } from '../store';
import { actionWithLoading } from './actionWithLoading';

const {dispatch} = store;

export function fetchGenerics() {
  return actionWithLoading(() =>
    WidgetsApiService.getGenerics()
      .then(response => {
        dispatch(setGenerics(response.data));
        dispatch(setScenarioLoading(false));
      })
      .catch(() => {
        dispatch(setScenarioLoading(false));
        dispatch(
          setCanvasNotification({
            open: true,
            variant: 'error',
            text: 'Generics fetching failed.'
          })
        );
      })
  )
}
