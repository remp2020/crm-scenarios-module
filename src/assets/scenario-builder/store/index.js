import { configureStore } from '@reduxjs/toolkit'
import bannersReducer from './bannersSlice'
import beforeTriggersReducer from './beforeTriggersSlice'
import canvasReducer from './canvasSlice'
import criteriaReducer from './criteriaSlice'
import genericsReducer from './genericsSlice'
import goalsReducer from './goalsSlice'
import journeysReducer from './journeysSlice'
import mailsReducer from './mailsSlice'
import pushNotificationsReducer from './pushNotificationsSlice'
import scenarioReducer from './scenarioSlice'
import segmentsReducer from './segmentsSlice'
import statisticsReducer from './statisticsSlice'
import triggersReducer from './triggersSlice'
import usersReducer from './usersSlice'
import thunkMiddleware from 'redux-thunk';

export const store = configureStore({
  reducer: {
    banners: bannersReducer,
    beforeTriggers: beforeTriggersReducer,
    canvas: canvasReducer,
    criteria: criteriaReducer,
    generics: genericsReducer,
    goals: goalsReducer,
    journeys: journeysReducer,
    mails: mailsReducer,
    pushNotifications: pushNotificationsReducer,
    scenario: scenarioReducer,
    segments: segmentsReducer,
    statistics: statisticsReducer,
    triggers: triggersReducer,
    users: usersReducer,
  },
  middleware: (getDefaultMiddleware) => getDefaultMiddleware().concat(thunkMiddleware),
})
