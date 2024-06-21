import {STATISTICS_CHANGED} from "./types";
import axios from "axios";
import * as config from "../config";

export function fetchStatistics(scenarioId) {
  return dispatch => {
    return axios
      .get(config.URL_SCENARIO_STATISTIC + scenarioId)
      .then(response => {
        dispatch({
          type: STATISTICS_CHANGED,
            payload: response.data
        });
      })
      .catch(error => {
        console.log(error);
      });
  };
}