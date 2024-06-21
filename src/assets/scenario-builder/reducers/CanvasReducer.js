import {
  CANVAS_PANNABLE,
  CANVAS_ZOOMABLE,
  CANVAS_ZOOMABLE_PANNABLE,
  CANVAS_NOTIFICATION
} from './../actions/types';

const INITIAL_STATE = {
  pannable: true,
  zoomable: true,
  notification: {
    open: false,
    variant: 'success',
    text: ''
  }
};

export default (state = INITIAL_STATE, action) => {
  switch (action.type) {
    case CANVAS_PANNABLE:
      return { ...state, pannable: action.payload };

    case CANVAS_ZOOMABLE:
      return { ...state, zoomable: action.payload };

    case CANVAS_ZOOMABLE_PANNABLE:
      return { ...state, zoomable: action.payload, pannable: action.payload };

    case CANVAS_NOTIFICATION:
      return {
        ...state,
        notification: { ...state.notification, ...action.payload }
      };

    default:
      return state;
  }
};
