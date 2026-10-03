import { createSelector } from '@reduxjs/toolkit';

const selectJourneys = state => state.journeys;

const selectOpenedJourneyJobs = createSelector(
  [selectJourneys],
  journeys => (journeys.selectedJourney ? journeys.jobs : [])
);

// canvas node ids are trigger/element uuids
export const selectJourneyNodeIds = createSelector(
  [selectOpenedJourneyJobs],
  jobs => new Set(jobs.map(job => job.node.uuid).filter(Boolean))
);

// nodes where the journey currently is, e.g. a running wait
export const selectJourneyInProgressNodeIds = createSelector(
  [selectOpenedJourneyJobs],
  jobs => new Set(jobs.filter(job => job.in_progress).map(job => job.node.uuid).filter(Boolean))
);

export const journeyEdgeKey = (source, target) => `${source}>${target}`;

// each job was scheduled by its parent job, so parent node -> job node is a traversed edge
export const selectJourneyEdgeKeys = createSelector(
  [selectOpenedJourneyJobs],
  jobs => {
    const uuidsByJobId = new Map(jobs.map(job => [job.job_id, job.node.uuid]));
    const keys = new Set();

    jobs.forEach(job => {
      const source = uuidsByJobId.get(job.parent_job_id);
      if (source && job.node.uuid) {
        keys.add(journeyEdgeKey(source, job.node.uuid));
      }
    });

    return keys;
  }
);
