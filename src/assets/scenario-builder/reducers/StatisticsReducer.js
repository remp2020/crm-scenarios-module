import {STATISTICS_CHANGED} from "../actions/types";

const INITIAL_STATE = {
  statistics: []
};

export default (state = INITIAL_STATE, action) => {
  switch (action.type) {
    case STATISTICS_CHANGED:
      return {
        ...state,
        statistics: action.payload.statistics,
      };

    default:
      return state;

  }
};