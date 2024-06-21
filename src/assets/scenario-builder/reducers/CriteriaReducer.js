
import {
    CRITERIA_CHANGED
} from './../actions/types';
  
const INITIAL_STATE = {
    criteria: []
};

export default (state = INITIAL_STATE, action) => {
    switch (action.type) {
        case CRITERIA_CHANGED:
            return { ...state, criteria: action.payload };

        default:
            return state;
    }
};