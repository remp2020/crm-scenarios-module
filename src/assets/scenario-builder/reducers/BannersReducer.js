import { BANNERS_CHANGED } from '../actions/types';

const INITIAL_STATE = {
  availableBanners: []
};

export default (state = INITIAL_STATE, action) => {
  switch (action.type) {
    case BANNERS_CHANGED:
      return { ...state, availableBanners: action.payload };

    default:
      return state;
  }
};
