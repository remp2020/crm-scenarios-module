import { store } from '../store';
import { setCanvasNotification } from '../store/canvasSlice';

// shows failed API request in the canvas notification, so it is not mistaken for an empty result
export function notifyApiError(error, what) {
  const status = error?.response?.status;
  let text = `${what} fetching failed`;

  if (status === 401 || status === 403) {
    text += ` (HTTP ${status}). Check that the API route is seeded and accessible for your API key.`;
  } else if (status) {
    text += ` (HTTP ${status}).`;
  } else {
    text += '.';
  }

  store.dispatch(
    setCanvasNotification({
      open: true,
      variant: 'error',
      text
    })
  );
}
