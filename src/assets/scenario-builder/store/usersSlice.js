import { createSlice } from '@reduxjs/toolkit';

export const usersSlice = createSlice({
  name: 'usersSlice',
  initialState: {
    items: [],
    loading: false,
    selectedUser: null
  },
  reducers: {
    setUsers: (state, action) => {
      state.items = action.payload.users
    },
    setUsersLoading: (state, action) => {
      state.loading = action.payload
    },
    setSelectedUser: (state, action) => {
      state.selectedUser = action.payload
    },
  }
})

export const { setUsers, setUsersLoading, setSelectedUser } = usersSlice.actions

export default usersSlice.reducer
