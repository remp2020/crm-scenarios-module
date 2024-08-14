import { createSlice } from '@reduxjs/toolkit';

export const criteriaSlice = createSlice({
  name: 'criteriaSlice',
  initialState: {
    criteria: []
  },
  reducers: {
    setCriteria: (state, action) => {
      state.criteria = action.payload
    }
  }
})

export const { setCriteria } = criteriaSlice.actions

export default criteriaSlice.reducer
