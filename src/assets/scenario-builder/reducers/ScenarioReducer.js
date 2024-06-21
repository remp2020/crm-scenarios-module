import {
  SET_SCENARIO_ID,
  SET_SCENARIO_NAME,
  SET_SCENARIO_LOADING,
  SET_SCENARIO_PAYLOAD
} from './../actions/types';

const INITIAL_STATE = {
  id: null,
  name: '',
  loading: 0,
  payload: null
};

export default (state = INITIAL_STATE, action) => {
  switch (action.type) {
    case SET_SCENARIO_ID:
      return { ...state, id: action.payload };

    case SET_SCENARIO_NAME:
      return { ...state, name: action.payload };

    case SET_SCENARIO_LOADING:
      let loading = state.loading;
      if (action.payload) {
        ++loading;
      } else {
        --loading;
      }
      return { ...state, loading };

    case SET_SCENARIO_PAYLOAD:
      return { ...state, payload: action.payload };

    default:
      return state;
  }
};
