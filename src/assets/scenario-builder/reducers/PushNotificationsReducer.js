import { PUSH_NOTIFICATIONS_CHANGED } from './../actions/types';

const INITIAL_STATE = {
  availableTemplates: [],
  availableApplications: [],
};

export default (state = INITIAL_STATE, action) => {
  switch (action.type) {
    case PUSH_NOTIFICATIONS_CHANGED:
      return {
        ...state,
        availableTemplates: action.payload.templates,
        availableApplications: action.payload.applications,
      };

    default:
      return state;
  }
};
