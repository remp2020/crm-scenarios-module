import axios from 'axios';
import { v1 } from '../api_routes';

export class WidgetsApiService {
  static getBanners() {
    return axios.get(v1.banners.index)
  }

  static getBeforeTriggers() {
    return axios.get(v1.before_triggers.index)
  }

  static getCriteria() {
    return axios.get(v1.scenario.criteria)
  }

  static getGenerics() {
    return axios.get(v1.generics.index)
  }

  static getGoals() {
    return axios.get(v1.goals.index)
  }

  static getMails() {
    return axios.get(v1.mails.index)
  }

  static getPushNotificationTemplates() {
    return axios.get(v1.pushNotifications.templates);
  }

  static getPushNotificationApplications() {
    return axios.get(v1.pushNotifications.applications);
  }

  static getPushNotifications() {
    return axios.all([WidgetsApiService.getPushNotificationTemplates(), WidgetsApiService.getPushNotificationApplications()])
  }

  static getScenario(scenarioId) {
    return axios.get(v1.scenario.detail(scenarioId))
  }

  static getSegments() {
    return axios.get(v1.segments.index)
  }

  static getStatistics(scenarioId) {
    return axios.get(v1.scenario.statistics(scenarioId))
  }

  static getTriggers() {
    return axios.get(v1.triggers.index)
  }
}
