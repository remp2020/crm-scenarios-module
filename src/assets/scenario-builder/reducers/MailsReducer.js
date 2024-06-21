import { MAILS_CHANGED } from './../actions/types';

const INITIAL_STATE = {
  availableMails: []
};

export default (state = INITIAL_STATE, action) => {
  switch (action.type) {
    case MAILS_CHANGED:
      return { ...state, availableMails: action.payload };

    default:
      return state;
  }
};
