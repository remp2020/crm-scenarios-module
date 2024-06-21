import React from "react";
import {useSelector} from 'react-redux';
import Chip from "@material-ui/core/Chip";
import {CircularProgress} from "@material-ui/core";

function StatisticBadge(props) {

  const formatLabelNumbers = number => {
    if (number < 1000) {
      return number;
    }
    if (number < 1000000) {
      return Number(number / 1000).toFixed((number > 100000 ? 0 : 1)) + 'K';
    }

    return Number(number / 1000000).toFixed(1) + 'M';
  };

  const defaultTimePeriod = '24h';
  const statistics = useSelector(state => state.statistics.statistics);
  const data = statistics[props.elementId] ?? null;

  let label = null;
  if (statistics.length === 0) {
    label = <div sx={{color: 'white'}}><CircularProgress size={8} color="inherit" /></div>;
  } else {
    if (data) {
      if (data.hasOwnProperty('finished')) {
        label = data.finished[defaultTimePeriod];
      }

      if (props.position === 'right' && data.hasOwnProperty('matched')) {
        label = data.matched[defaultTimePeriod];
      }

      if (props.position === 'right' && data.hasOwnProperty('completed')) {
        label = data.completed[defaultTimePeriod];
      }

      if (props.position === 'bottom' && data.hasOwnProperty('notMatched')) {
        label = data.notMatched[defaultTimePeriod];
      }

      if (props.position === 'bottom' && data.hasOwnProperty('timeout')) {
        label = data.timeout[defaultTimePeriod];
      }

      if (props.hasOwnProperty('index')) {
        if (!data[props.index]) {
          label = 0;
        } else {
          label = data[props.index][defaultTimePeriod];
        }
      }
    }

    if (label !== null) {
      label=formatLabelNumbers(label);
    } else {
      return null;
    }
  }

  return (
    <div className={"statistic-badge-container-" + props.position}>
      <Chip
        label={label}
        color="primary"
        size="small"
        style={{backgroundColor: props.color, height: '16px', borderRadius: '4px', fontSize: '0.7rem'}}
        className={"statistic-badge statistic-badge-" + props.position}
      />
    </div>
  );
}


export default StatisticBadge;