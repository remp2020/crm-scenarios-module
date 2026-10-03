import { store } from '../store';
import {
  setJourneyJobs,
  setJourneyJobsLoading,
  setJourneys,
  setJourneysLoading
} from '../store/journeysSlice';
import { ScenarioApiService } from '../services';
import { notifyApiError } from '../utils/apiError';

const { dispatch, getState } = store;

// responses of a user or journey switched away from must not overwrite the current one
const isSelectedUser = userId => getState().users.selectedUser?.id === userId;
const isSelectedJourney = journeyId => getState().journeys.selectedJourney?.journey_id === journeyId;

export function fetchUserJourneys(scenarioId, userId) {
  return () => {
    dispatch(setJourneysLoading(true));

    return ScenarioApiService.getUserJourneys(scenarioId, userId)
      .then(response => {
        if (!isSelectedUser(userId)) {
          return;
        }
        dispatch(setJourneys(response.data));
        dispatch(setJourneysLoading(false));
      })
      .catch(error => {
        if (!isSelectedUser(userId)) {
          return;
        }
        dispatch(setJourneys({ journeys: [] }));
        dispatch(setJourneysLoading(false));
        notifyApiError(error, 'Journeys');
      });
  };
}

export function fetchJourneyJobs(scenarioId, journeyId) {
  return () => {
    dispatch(setJourneyJobsLoading(true));

    return ScenarioApiService.getJourneyJobs(scenarioId, journeyId)
      .then(response => {
        if (!isSelectedJourney(journeyId)) {
          return;
        }
        dispatch(setJourneyJobs(response.data));
        dispatch(setJourneyJobsLoading(false));
      })
      .catch(error => {
        if (!isSelectedJourney(journeyId)) {
          return;
        }
        dispatch(setJourneyJobs({ jobs: [] }));
        dispatch(setJourneyJobsLoading(false));
        notifyApiError(error, 'Journey jobs');
      });
  };
}
