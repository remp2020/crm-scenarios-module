import React from 'react';
import { Provider } from 'react-redux';
import App from './App';
import { createRoot } from 'react-dom/client';
import { createTheme, StyledEngineProvider, ThemeProvider } from '@mui/material';
import { store } from './store';

window.__MUI_USE_NEXT_TYPOGRAPHY_VARIANTS__ = true;
window.__REDUX_DEVTOOLS_EXTENSION__ && window.__REDUX_DEVTOOLS_EXTENSION__()

const domNode = document.getElementById('scenario-editor-root');

const root = createRoot(domNode);
const theme = createTheme({
  typography: {
    htmlFontSize: 10
  },
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
