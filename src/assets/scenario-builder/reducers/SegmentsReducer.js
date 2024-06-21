
import {
    SEGMENTS_CHANGED
} from './../actions/types';
  
const INITIAL_STATE = {
    avalaibleSegments: []
};

export default (state = INITIAL_STATE, action) => {
    switch (action.type) {
        case SEGMENTS_CHANGED:
            return { ...state, avalaibleSegments: action.payload };

        default:
            return state;
    }
};