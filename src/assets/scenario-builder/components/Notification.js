import React from 'react';
import PropTypes from 'prop-types';
import Snackbar from '@material-ui/core/Snackbar';
import SnackbarContent from '@material-ui/core/SnackbarContent';
import CheckCircleIcon from '@material-ui/icons/CheckCircle';
import ErrorIcon from '@material-ui/icons/Error';
import InfoIcon from '@material-ui/icons/Info';
import WarningIcon from '@material-ui/icons/Warning';

const variantIcon = {
  success: CheckCircleIcon,
  warning: WarningIcon,
  error: ErrorIcon,
  info: InfoIcon
};

class Notification extends React.Component {
  render() {
    const Icon = variantIcon[this.props.variant];

    return (
      <Snackbar
        anchorOrigin={{
          vertical: 'bottom',
          horizontal: 'right'
        }}
        open={this.props.open}
        autoHideDuration={3000}
        onClose={this.props.handleClose}
      >
        <SnackbarContent
          className={'toast-' + this.props.variant}
          message={
            <span id='client-snackbar' className='toast__message'>
              <Icon className='toast__icon toast__icon-variant' />
              {this.props.text}
            </span>
          }
        />
      </Snackbar>
    );
  }
}

Notification.propTypes = {
  variant: PropTypes.oneOf(['success', 'warning', 'info', 'error']).isRequired,
  text: PropTypes.string.isRequired,
  handleClose: PropTypes.func,
  open: PropTypes.bool.isRequired
};

export default Notification;
