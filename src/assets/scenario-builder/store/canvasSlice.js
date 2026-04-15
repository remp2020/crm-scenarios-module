import { createSlice } from '@reduxjs/toolkit';

export const canvasSlice = createSlice({
  name: 'canvasSlice',
  initialState: {
    pannable: true,
    zoomable: true,
    nodeDetailOpened: false,
    notification: {
      open: false,
      variant: 'success',
      text: ''
    }
  },
  reducers: {
    setCanvasPannable: (state, action) => {
      state.pannable = action.payload
    },
    setCanvasZoomable: (state, action) => {
      state.zoomable = action.payload
    },
    setCanvasZoomingAndPanning: (state, action) => {
      setCanvasPannable(action.payload?.pannable)
      setCanvasZoomable(action.payload?.zoomable)
    },
    setNodeDetailOpened: (state, action) => {
      state.nodeDetailOpened = action.payload
    },
    setCanvasNotification: (state, action) => {
      state.notification = { ...state.notification, ...action.payload}
    }
  }
})

export const { setCanvasPannable, setCanvasZoomable, setCanvasZoomingAndPanning, setNodeDetailOpened, setCanvasNotification } = canvasSlice.actions

export default canvasSlice.reducer
