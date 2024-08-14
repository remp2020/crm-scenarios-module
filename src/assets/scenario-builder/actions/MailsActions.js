import { store } from '../store';
import { setAvailableMails } from '../store/mailsSlice';
import { setScenarioLoading } from '../store/scenarioSlice';
import { setCanvasNotification } from '../store/canvasSlice';
import { WidgetsApiService } from '../services';
import { actionWithLoading } from './actionWithLoading';

const { dispatch } = store;

export function fetchMails() {
  return actionWithLoading(() =>
    WidgetsApiService.getMails()
      .then(response => {
        dispatch(setAvailableMails(response.data.mail_templates));
        dispatch(setScenarioLoading(false));
      })
      .catch(() => {
        dispatch(setScenarioLoading(false));
        dispatch(
          setCanvasNotification({
            open: true,
            variant: 'error',
            text: 'Mails fetching failed.'
          })
        );
      })
  )
}
