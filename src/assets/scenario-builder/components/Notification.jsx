import React from 'react';
import Snackbar from '@mui/material/Snackbar';
import SnackbarContent from '@mui/material/SnackbarContent';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import ErrorIcon from '@mui/icons-material/Error';
import InfoIcon from '@mui/icons-material/Info';
import WarningIcon from '@mui/icons-material/Warning';

const variantIcon = {
  success: CheckCircleIcon,
  warning: WarningIcon,
  error: ErrorIcon,
  info: InfoIcon
};

const Notification = (props) => {
  const Icon = variantIcon[props.variant];

  return (
    <Snackbar
      anchorOrigin={{
        vertical: 'bottom',
        horizontal: 'right'
      }}
      open={props.open}
      autoHideDuration={3000}
      onClose={props.handleClose}
    >
      <SnackbarContent
        className={'toast-' + props.variant}
        message={
          <span id='client-snackbar' className='toast__message'>
            <Icon className='toast__icon toast__icon-variant' />
            {props.text}
          </span>
        }
      />
    </Snackbar>
  );
}

export default Notification;
