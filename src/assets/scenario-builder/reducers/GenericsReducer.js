import { GENERICS_CHANGED } from './../actions/types';

const INITIAL_STATE = {
  generics: []
};

export default (state = INITIAL_STATE, action) => {
  switch (action.type) {
    case GENERICS_CHANGED:
      return { ...state, generics: action.payload };

    default:
      return state;
  }
};
