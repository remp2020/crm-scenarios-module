import { GOALS_CHANGED } from './../actions/types';

const INITIAL_STATE = {
  availableGoals: []
};

export default (state = INITIAL_STATE, action) => {
  switch (action.type) {
    case GOALS_CHANGED:
      return { ...state, availableGoals: action.payload };

    default:
      return state;
  }
};
