import { createSlice } from '@reduxjs/toolkit';

const emptyState = {
  items: [],
  // more journeys exist than the API returned
  hasMore: false,
  loading: false,
  selectedJourney: null,
  jobs: [],
  jobsLoading: false,
  // node of the job hovered in the journey detail
  hoveredNodeUuid: null
};

export const journeysSlice = createSlice({
  name: 'journeysSlice',
  initialState: { ...emptyState },
  reducers: {
    setJourneys: (state, action) => {
      state.items = action.payload.journeys
      state.hasMore = !!action.payload.has_more
    },
    setJourneysLoading: (state, action) => {
      state.loading = action.payload
    },
    setSelectedJourney: (state, action) => {
      state.selectedJourney = action.payload
      state.jobs = []
      state.hoveredNodeUuid = null
    },
    setHoveredJourneyNode: (state, action) => {
      state.hoveredNodeUuid = action.payload
    },
    setJourneyJobs: (state, action) => {
      state.jobs = action.payload.jobs
    },
    setJourneyJobsLoading: (state, action) => {
      state.jobsLoading = action.payload
    },
    // called when the selected user changes, nothing of the previous one may stay on screen
    resetJourneys: () => ({ ...emptyState })
  }
})

export const {
  setJourneys,
  setJourneysLoading,
  setSelectedJourney,
  setJourneyJobs,
  setJourneyJobsLoading,
  setHoveredJourneyNode,
  resetJourneys
} = journeysSlice.actions

export default journeysSlice.reducer
