import {
  CANVAS_PANNABLE,
  CANVAS_ZOOMABLE,
  CANVAS_ZOOMABLE_PANNABLE,
  CANVAS_NOTIFICATION
} from './types';

export function setCanvasZoomable(zoomable) {
  return {
    type: CANVAS_ZOOMABLE,
    payload: zoomable
  };
}

export function setCanvasPannable(pannable) {
  return {
    type: CANVAS_PANNABLE,
    payload: pannable
  };
}

export function setCanvasZoomingAndPanning(zoomingAndPanning) {
  return {
    type: CANVAS_ZOOMABLE_PANNABLE,
    payload: zoomingAndPanning
  };
}

export function setCanvasNotification(notificationOptions) {
  return {
    type: CANVAS_NOTIFICATION,
    payload: notificationOptions
  };
}
