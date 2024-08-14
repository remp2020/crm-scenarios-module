import { createSlice } from '@reduxjs/toolkit';

export const goalsSlice = createSlice({
  name: 'goalsSlice',
  initialState: {
    availableGoals: []
  },
  reducers: {
    setAvailableGoals: (state, action) => {
      state.availableGoals = action.payload
    }
  }
})

export const { setAvailableGoals } = goalsSlice.actions

export default goalsSlice.reducer
