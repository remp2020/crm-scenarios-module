import { setScenarioLoading } from '../store/scenarioSlice';
import { store } from '../store';

const {dispatch} = store;

export const actionWithLoading = (callback) => {
  dispatch(setScenarioLoading(true));

  return callback
}
