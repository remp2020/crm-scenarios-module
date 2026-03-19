import React, { useEffect, useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import BodyWidget from './components/widgets/BodyWidget';
import { Application } from './components/Application';
import * as config from './config';
import {
  fetchBanners,
  fetchBeforeTriggers,
  fetchCriteria,
  fetchGenerics,
  fetchGoals,
  fetchMails,
  fetchPushNotifications,
  fetchScenario,
  fetchSegments,
  fetchStatistics,
  fetchTriggers
} from './actions';
import { setScenarioName } from './store/scenarioSlice';

const App = () => {
  const [app, setApp] = useState();
  const dispatch = useDispatch();
  const scenarioPayload = useSelector(state => state.scenario.payload);

  useEffect(() => {
    dispatch(fetchSegments());
    dispatch(fetchCriteria());
    dispatch(fetchGoals());
    dispatch(fetchTriggers());
    dispatch(fetchBeforeTriggers());
    dispatch(fetchMails());
    dispatch(fetchGenerics());

    if (config.BANNER_ENABLED) {
      dispatch(fetchBanners());
    }

    if (config.PUSH_NOTIFICATION_ENABLED) {
      dispatch(fetchPushNotifications());
    }

    if (config.SCENARIO_ID) {
      dispatch(fetchScenario(config.SCENARIO_ID));
      dispatch(fetchStatistics(config.SCENARIO_ID));
    } else {
      dispatch(setScenarioName('Unnamed scenario'));
    }
  }, []);

  useEffect(() => {
    app.renderPayload(scenarioPayload);
  }, [scenarioPayload]);

  if (!app) {
    setApp(new Application());
  }

  return <BodyWidget app={app}/>;
};

export default App;
