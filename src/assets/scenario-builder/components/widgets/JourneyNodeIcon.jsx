import React from 'react';
import EmailIcon from '@mui/icons-material/Mail';
import ExtensionIcon from '@mui/icons-material/Extension';
import BannerIcon from '@mui/icons-material/Adjust';
import TriggerIcon from '@mui/icons-material/Notifications';
import WaitIcon from '@mui/icons-material/AccessAlarmsOutlined';
import SegmentIcon from '@mui/icons-material/SubdirectoryArrowRight';
import ConditionIcon from '@mui/icons-material/CallSplit';
import GoalIcon from '@mui/icons-material/CheckBox';
import NotificationsActiveIcon from '@mui/icons-material/NotificationsActive';
import RepeatIcon from '@mui/icons-material/Repeat';
import PushNotificationIcon from '@mui/icons-material/PhonelinkRing';
import ABTestIcon from '@mui/icons-material/SwapVert';

// keys are DB types of triggers/elements, same icons as in the editor
const NODE_ICONS = {
  event: TriggerIcon,
  before_event: NotificationsActiveIcon,
  repeating: RepeatIcon,
  email: EmailIcon,
  generic: ExtensionIcon,
  banner: BannerIcon,
  push_notification: PushNotificationIcon,
  segment: SegmentIcon,
  condition: ConditionIcon,
  wait: WaitIcon,
  goal: GoalIcon,
  ab_test: ABTestIcon
};

// unknown type, fall back by kind
const JourneyNodeIcon = ({ node, ...props }) => {
  const Icon = NODE_ICONS[node.type] ?? (node.kind === 'trigger' ? TriggerIcon : ExtensionIcon);
  return <Icon {...props}/>;
};

export default JourneyNodeIcon;
