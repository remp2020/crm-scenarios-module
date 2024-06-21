import React, { Component } from 'react';
import { connect } from 'react-redux';

import BodyWidget from './components/widgets/BodyWidget';
import { Application } from './components/Application';
import * as config from './config';
import {
  fetchSegments,
  fetchGoals,
  fetchBanners,
  fetchTriggers,
  fetchBeforeTriggers,
  fetchCriteria,
  fetchScenario,
  setScenarioName,
  fetchMails,
  fetchGenerics,
  fetchPushNotifications,
  fetchStatistics
} from './actions';

class App extends Component {
  componentDidMount() {
    const { dispatch } = this.props;

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
  }

  render() {
    var app = new Application(this.props.scenarioPayload);

    return <BodyWidget app={app} />;
  }
}

function mapStateToProps(state) {
  return {
    scenarioPayload: state.scenario.payload
  };
}

export default connect(mapStateToProps)(App);
