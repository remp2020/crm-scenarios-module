import {BEFORE_TRIGGERS_CHANGED} from '../actions/types';

const INITIAL_STATE = {
  availableBeforeTriggers: []
};

export default (state = INITIAL_STATE, action) => {
  switch (action.type) {
    case BEFORE_TRIGGERS_CHANGED:
      return { ...state, availableBeforeTriggers: action.payload };

    default:
      return state;
  }
};
