import React from 'react';
import { Provider } from 'react-redux';
import axios from 'axios';
import App from './App';
import * as config from './config';
import { createRoot } from 'react-dom/client';
import { createTheme, StyledEngineProvider, ThemeProvider } from '@mui/material';
import { store } from './store';

window.__MUI_USE_NEXT_TYPOGRAPHY_VARIANTS__ = true;
window.__REDUX_DEVTOOLS_EXTENSION__ && window.__REDUX_DEVTOOLS_EXTENSION__()

axios.defaults.headers.common['Authorization'] = config.AUTH_TOKEN;

const domNode = document.getElementById('root')

const root = createRoot(domNode);
const theme = createTheme({
  palette: {
    primary: {
      main: '#3f51b5',
    },
    secondary: {
      main: '#f50057'
    }
  }
})
root.render(
  <ThemeProvider theme={theme}>
    <Provider store={store}>
      <StyledEngineProvider injectFirst>
        <App />
      </StyledEngineProvider>
    </Provider>
  </ThemeProvider>
)
