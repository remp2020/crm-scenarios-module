import { createSlice } from '@reduxjs/toolkit';

export const triggersSlice = createSlice({
  name: 'triggersSlice',
  initialState: {
    availableTriggers: []
  },
  reducers: {
    setAvailableTriggers: (state, action) => {
      state.availableTriggers = action.payload
    },
  }
})

export const { setAvailableTriggers } = triggersSlice.actions

export default triggersSlice.reducer
