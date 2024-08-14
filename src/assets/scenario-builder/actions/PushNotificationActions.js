import { store } from '../store';
import { setPushNotifications } from '../store/pushNotificationsSlice';
import { setScenarioLoading } from '../store/scenarioSlice';
import { setCanvasNotification } from '../store/canvasSlice';
import { WidgetsApiService } from '../services';
import { actionWithLoading } from './actionWithLoading';

const { dispatch } = store;

export function fetchPushNotifications() {
  return actionWithLoading(() =>
    WidgetsApiService.getPushNotifications()
      .then(responses => {
        dispatch(setPushNotifications({ templates: responses[0].data.templates, applications: responses[1].data.applications }));
        dispatch(setScenarioLoading(false));
      })
      .catch(() => {
        dispatch(setScenarioLoading(false));
        dispatch(
          setCanvasNotification({
            open: true,
            variant: 'error',
            text: 'Notifications fetching failed.'
          })
        );
      })
  )
}
