import axios from 'axios';
import { v1 } from '../api_routes';

export class ScenarioApiService {
  static getScenario(scenarioId) {
    return axios.get(v1.scenario.detail(scenarioId))
  }

  static getStatistics(scenarioId) {
    return axios.get(v1.scenario.statistics(scenarioId))
  }
}
