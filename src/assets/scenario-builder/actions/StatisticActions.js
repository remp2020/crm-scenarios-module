import { store } from '../store';
import { setStatistics } from '../store/statisticsSlice';
import { ScenarioApiService } from '../services';

const { dispatch } = store;

export function fetchStatistics(scenarioId) {
  return () => ScenarioApiService.getStatistics(scenarioId)
    .then(response => {
      dispatch(setStatistics(response.data));
    });
}
