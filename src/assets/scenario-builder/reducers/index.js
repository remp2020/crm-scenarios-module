import { combineReducers } from 'redux';
import SegmentsReducer from './SegmentsReducer';
import CriteriaReducer from './CriteriaReducer';
import TriggersReducer from './TriggersReducer';
import BeforeTriggersReducer from './BeforeTriggersReducer';
import CanvasReducer from './CanvasReducer';
import ScenarioReducer from './ScenarioReducer';
import MailsReducer from './MailsReducer';
import GenericsReducer from './GenericsReducer';
import GoalsReducer from './GoalsReducer';
import BannersReducer from './BannersReducer';
import PushNotificationsReducer from './PushNotificationsReducer';
import StatisticsReducer from "./StatisticsReducer";

export default combineReducers({
  segments: SegmentsReducer,
  triggers: TriggersReducer,
  beforeTriggers: BeforeTriggersReducer,
  canvas: CanvasReducer,
  criteria: CriteriaReducer,
  scenario: ScenarioReducer,
  mails: MailsReducer,
  generics: GenericsReducer,
  goals: GoalsReducer,
  banners: BannersReducer,
  pushNotifications: PushNotificationsReducer,
  statistics: StatisticsReducer,
});
