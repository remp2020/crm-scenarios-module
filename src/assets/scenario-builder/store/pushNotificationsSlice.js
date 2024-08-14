import { createSlice } from '@reduxjs/toolkit';

export const pushNotificationsSlice = createSlice({
  name: 'pushNotificationsSlice',
  initialState: {
    availableTemplates: [],
    availableApplications: [],
  },
  reducers: {
    setPushNotifications: (state, action) => {
      state.availableTemplates = action.payload?.templates ?? []
      state.availableApplications = action.payload?.applications ?? []
    }
  }
})

export const { setPushNotifications } = pushNotificationsSlice.actions

export default pushNotificationsSlice.reducer
