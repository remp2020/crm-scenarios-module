import axios from 'axios';
import { v1 } from '../api_routes';

export class ScenarioApiService {
  static getScenario(scenarioId) {
    return axios.get(v1.scenario.detail(scenarioId))
  }

  static getStatistics(scenarioId) {
    return axios.get(v1.scenario.statistics(scenarioId))
  }

  static getScenarioUsers(scenarioId, query, limit) {
    return axios.get(v1.scenario.users, {
      params: { id: scenarioId, query, limit }
    })
  }

  static getUserJourneys(scenarioId, userId) {
    return axios.get(v1.scenario.journeys, {
      params: { id: scenarioId, user_id: userId }
    })
  }

  static getJourneyJobs(scenarioId, journeyId) {
    return axios.get(v1.scenario.journeyJobs, {
      params: { id: scenarioId, journey_id: journeyId }
    })
  }
}
