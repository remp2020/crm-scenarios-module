import { store } from '../store';
import { setUsers, setUsersLoading } from '../store/usersSlice';
import { ScenarioApiService } from '../services';
import { notifyApiError } from '../utils/apiError';

const { dispatch } = store;

// only the latest search may update the list, slower earlier ones are dropped
let latestUsersRequest = 0;

export function fetchScenarioUsers(scenarioId, query = '') {
  return () => {
    const request = ++latestUsersRequest;
    dispatch(setUsersLoading(true));

    return ScenarioApiService.getScenarioUsers(scenarioId, query)
      .then(response => {
        if (request !== latestUsersRequest) {
          return;
        }
        dispatch(setUsers(response.data));
        dispatch(setUsersLoading(false));
      })
      .catch(error => {
        if (request !== latestUsersRequest) {
          return;
        }
        dispatch(setUsers({ users: [] }));
        dispatch(setUsersLoading(false));
        notifyApiError(error, 'Users');
      });
  };
}
