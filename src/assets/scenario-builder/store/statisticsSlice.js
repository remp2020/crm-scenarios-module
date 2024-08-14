import { createSlice } from '@reduxjs/toolkit';

export const statisticsSlice = createSlice({
  name: 'statisticsSlice',
  initialState: {
    statistics: []
  },
  reducers: {
    setStatistics: (state, action) => {
      state.statistics = action.payload.statistics
    },
  }
})

export const { setStatistics } = statisticsSlice.actions

export default statisticsSlice.reducer
