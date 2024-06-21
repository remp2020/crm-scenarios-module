import { TRIGGERS_CHANGED } from './../actions/types';

const INITIAL_STATE = {
  avalaibleTriggers: []
};

export default (state = INITIAL_STATE, action) => {
  switch (action.type) {
    case TRIGGERS_CHANGED:
      return { ...state, avalaibleTriggers: action.payload };

    default:
      return state;
  }
};
