import { createSlice } from '@reduxjs/toolkit';

export const segmentsSlice = createSlice({
  name: 'segmentsSlice',
  initialState: {
    availableSegments: []
  },
  reducers: {
    setAvailableSegments: (state, action) => {
      state.availableSegments = action.payload
    },
  }
})

export const { setAvailableSegments } = segmentsSlice.actions

export default segmentsSlice.reducer
