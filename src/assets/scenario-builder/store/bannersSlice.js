import { createSlice } from '@reduxjs/toolkit';

export const bannersSlice = createSlice({
  name: 'banners',
  initialState: {
    availableBanners: []
  },
  reducers: {
    setAvailableBanners: (state, action) => {
      state.availableBanners = action.payload
    }
  }
})

export const { setAvailableBanners } = bannersSlice.actions

export default bannersSlice.reducer
