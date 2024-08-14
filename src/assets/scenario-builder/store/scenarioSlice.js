import { createSlice } from '@reduxjs/toolkit';

export const scenarioSlice = createSlice({
  name: 'scenarioSlice',
  initialState: {
    id: null,
    name: '',
    loading: 0,
    payload: null
  },
  reducers: {
    setScenarioId: (state, action) => {
      state.id = action.payload
    },
    setScenarioName: (state, action) => {
      state.name = action.payload
    },
    setScenarioLoading: (state, action) => {
      if (action.payload) {
        state.loading++
        return
      }

      state.loading--
    },
    setScenarioPayload: (state, action) => {
      state.payload = action.payload
    },
  }
})

export const { setScenarioId, setScenarioName, setScenarioLoading, setScenarioPayload } = scenarioSlice.actions

export default scenarioSlice.reducer
