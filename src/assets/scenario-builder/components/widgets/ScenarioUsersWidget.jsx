import React, { useEffect, useMemo, useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import debounce from 'lodash/debounce';
import Autocomplete from '@mui/material/Autocomplete';
import TextField from '@mui/material/TextField';
import CircularProgress from '@mui/material/CircularProgress';
import Typography from '@mui/material/Typography';
import { makeStyles } from '@mui/styles';
import { fetchScenarioUsers } from '../../actions';
import { setSelectedUser } from '../../store/usersSlice';

const SEARCH_DEBOUNCE = 300;
// email prefixes shorter than this match too many users to be worth a request,
// numeric terms are exempt - those are looked up as user IDs (primary key)
const MIN_SEARCH_LENGTH = 3;

const useStyles = makeStyles(theme => ({
  hint: {
    color: theme.palette.grey[600]
  },
  subtitle: {
    color: theme.palette.grey[600]
  }
}));

const optionStyle = {
  display: 'flex',
  flexDirection: 'column',
  alignItems: 'flex-start',
  justifyContent: 'flex-start',
  width: '100%',
  textAlign: 'left'
};

function optionLabel(option) {
  return typeof (option) === 'string' ? option : (option.email ?? '');
}

function isSearchable(term) {
  return term.length >= MIN_SEARCH_LENGTH || /^\d+$/.test(term);
}

const ScenarioUsersWidget = ({ scenarioId }) => {
  const classes = useStyles();
  const dispatch = useDispatch();
  const users = useSelector(state => state.users);
  const [searchTerm, setSearchTerm] = useState('');

  const searchUsers = useMemo(
    () => debounce(query => dispatch(fetchScenarioUsers(scenarioId, query)), SEARCH_DEBOUNCE),
    [dispatch, scenarioId]
  );

  // preload the most recent users when the sidebar opens (component mounts)
  useEffect(() => {
    if (!scenarioId) {
      return;
    }

    dispatch(fetchScenarioUsers(scenarioId));

    return () => searchUsers.cancel();
  }, [dispatch, scenarioId, searchUsers]);

  const noOptionsText = () => {
    if (users.loading) {
      return 'Searching…';
    }
    if (searchTerm !== '' && !isSearchable(searchTerm)) {
      return `Type at least ${MIN_SEARCH_LENGTH} characters`;
    }
    return 'No user passed through this scenario';
  };

  if (!scenarioId) {
    return (
      <Typography variant='body2' className={classes.hint}>
        Save the scenario first to search users who passed through it.
      </Typography>
    );
  }

  return (
    <Autocomplete
      fullWidth
      options={users.items}
      value={users.selectedUser}
      loading={users.loading}
      // options are already filtered by the API
      filterOptions={options => options}
      getOptionLabel={optionLabel}
      isOptionEqualToValue={(option, value) => option.id === value?.id}
      noOptionsText={noOptionsText()}
      slotProps={{
        paper: { sx: { textAlign: 'left' } }
      }}
      onInputChange={(event, value, reason) => {
        // 'clear' is the ✕ button, it brings the recent users back like an emptied input does
        if (reason !== 'input' && reason !== 'clear') {
          return;
        }

        const term = reason === 'clear' ? '' : value.trim();
        setSearchTerm(term);

        // an emptied input brings the recent users back, too short a term waits for more typing
        if (term === '' || isSearchable(term)) {
          searchUsers(term);
        } else {
          searchUsers.cancel();
        }
      }}
      onChange={(event, value) => {
        dispatch(setSelectedUser(value));
      }}
      renderInput={params => (
        <TextField
          {...params}
          variant='standard'
          placeholder='E-mail or user ID'
          fullWidth
          InputProps={{
            ...params.InputProps,
            endAdornment: (
              <>
                {users.loading && <CircularProgress size={16} color='inherit'/>}
                {params.InputProps.endAdornment}
              </>
            )
          }}
        />
      )}
      renderOption={(props, option) => (
        // inline styles - the popup is portaled outside the editor root, where the admin
        // layout styles apply and would win over a class
        <li {...props} key={option.id} style={optionStyle}>
          <span>{option.email}</span>
          <small className={classes.subtitle}>
            ID {option.id} · {option.journeys_count} journey{option.journeys_count === 1 ? '' : 's'}
          </small>
        </li>
      )}
    />
  );
};

export default ScenarioUsersWidget;
