import { createSlice } from '@reduxjs/toolkit';

export const genericsSlice = createSlice({
  name: 'genericsSlice',
  initialState: {
    generics: []
  },
  reducers: {
    setGenerics: (state, action) => {
      state.generics = action.payload
    }
  }
})

export const { setGenerics } = genericsSlice.actions

export default genericsSlice.reducer
