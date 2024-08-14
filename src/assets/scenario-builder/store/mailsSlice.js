import { createSlice } from '@reduxjs/toolkit';

export const mailsSLice = createSlice({
  name: 'mailsSLice',
  initialState: {
    availableMails: []
  },
  reducers: {
    setAvailableMails: (state, action) => {
      state.availableMails = action.payload
    }
  }
})

export const { setAvailableMails } = mailsSLice.actions

export default mailsSLice.reducer
