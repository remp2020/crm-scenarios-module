import React from 'react';
import { render } from 'react-dom';
import { Provider } from 'react-redux';
import { createStore, applyMiddleware, compose } from 'redux';
import thunkMiddleware from 'redux-thunk';
import axios from 'axios';

import rootReducer from './reducers';
import App from './App';
import * as config from './config';

window.__MUI_USE_NEXT_TYPOGRAPHY_VARIANTS__ = true;
axios.defaults.headers.common['Authorization'] = config.AUTH_TOKEN;

const composeEnhancers = window.__REDUX_DEVTOOLS_EXTENSION_COMPOSE__ || compose;

const store = createStore(
  rootReducer,
  {},
  composeEnhancers(applyMiddleware(thunkMiddleware))
);

render(
  <Provider store={store}>
    <App />
  </Provider>,
  document.getElementById('root')
);
