import { createSlice } from '@reduxjs/toolkit';

export const beforeTriggerSlice = createSlice({
  name: 'beforeTriggerSlice',
  initialState: {
    availableBeforeTriggers: []
  },
  reducers: {
    setAvailableBeforeTriggers: (state, action) => {
      state.availableBeforeTriggers = action.payload
    }
  }
})

export const { setAvailableBeforeTriggers } = beforeTriggerSlice.actions

export default beforeTriggerSlice.reducer
