import React, {useState} from 'react';
import {useSelector} from 'react-redux';
import RepeatIcon from '@mui/icons-material/Repeat';
import Grid from '@mui/material/Grid';
import TextField from '@mui/material/TextField';
import Button from '@mui/material/Button';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogContentText from '@mui/material/DialogContentText';
import DialogTitle from '@mui/material/DialogTitle';
import StatisticBadge from '../../StatisticBadge';
import StatisticsTooltip from '../../StatisticTooltip';
import {Handle, Position} from 'reactflow';
import {NodePopover} from '../../NodePopover';
import {useNode} from '../../../hooks/useNode';
import SegmentFormSelect from '../Segment/SegmentFormSelect';
import ScheduleForm from './ScheduleForm';
import {describeSchedule} from './schedule';

const SEGMENT_SOURCE_TABLE = 'users';

const NodeWidget = (props) => {
    const segments = useSelector(state => state.segments.availableSegments);
    const [selectedSegment, setSelectedSegment] = useState(props.data.node.selectedSegment);
    const [frequency, setFrequency] = useState(props.data.node.frequency);
    const [day, setDay] = useState(props.data.node.day);
    const [time, setTime] = useState(props.data.node.time);
    const [segmentError, setSegmentError] = useState(false);
    const {
        bem,
        getClassName,
        anchorElementForTooltip,
        anchorElForPopover,
        deleteNode,
        closePopover,
        dialogOpened,
        openDialog,
        onNodeClick,
        onNodeDoubleClick,
        nodeFormName,
        setNodeFormName,
        closeDialog,
        onDialogOpen,
        handleNodeMouseEnter,
        handleNodeMouseLeave
    } = useNode(props)

    onDialogOpen(() => {
        setSelectedSegment(props.data.node.selectedSegment);
        setFrequency(props.data.node.frequency);
        setDay(props.data.node.day);
        setTime(props.data.node.time);
        setSegmentError(false);
    })

    const segmentSelectedChange = segment => {
        const code = segment && segment.hasOwnProperty('code') ? segment.code : null;

        setSelectedSegment(code);
        setSegmentError(false);
    };

    const getSelectedSegmentName = () => {
        const selected = segments.flatMap(item => item.segments).find(
            segment => segment.code === props.data.node.selectedSegment
        );

        return selected ? selected.name : null;
    };

    const getNodeCaption = () => {
        const segmentName = getSelectedSegmentName();
        const schedule = describeSchedule(
            props.data.node.frequency,
            props.data.node.day,
            props.data.node.time
        );

        if (!segmentName && !schedule) {
            return 'Repeating';
        }

        return [schedule, segmentName].filter(Boolean).join(' - ');
    };

    return (
        <div
            className={getClassName()}
            style={{background: props.data.node.color}}
            onClick={onNodeClick}
            onDoubleClick={onNodeDoubleClick}
            onMouseEnter={handleNodeMouseEnter}
            onMouseLeave={handleNodeMouseLeave}
        >
            <NodePopover
                anchorEl={anchorElForPopover}
                onClose={closePopover}
                onEdit={openDialog}
                onDelete={deleteNode}
            />
            <div className="node-container">
                <div className={bem('__icon')}>
                    <RepeatIcon/>
                </div>

                <div className={bem('__ports')}>
                    <div className={bem('__right')}>
                        <Handle
                            type="source"
                            id="right"
                            position={Position.Right}
                            isConnectable={props.isConnectable}
                            className="port"
                        />
                        <StatisticBadge elementId={props.id} color="#6435c9" position="right"/>
                    </div>
                </div>
            </div>

            <div className={bem('__title')}>
                <div className={bem('__name')}>
                    {props.data.node.name ? props.data.node.name : getNodeCaption()}
                </div>
            </div>

            <StatisticsTooltip
                id={props.id}
                isTrigger={true}
                anchorElement={anchorElementForTooltip}
            />

            <Dialog
                fullWidth={true}
                maxWidth="md"
                open={dialogOpened}
                onClose={closeDialog}
                aria-labelledby="form-dialog-title"
                onKeyUp={event => {
                    if (event.key === 'Delete' || event.key === 'Backspace') {
                        event.preventDefault();
                        event.stopPropagation();
                        return false;
                    }
                }}
            >
                <DialogTitle id="form-dialog-title">Repeating node</DialogTitle>

                <DialogContent>
                    <DialogContentText>
                        Starts the scenario for every user of the selected segment, on the configured schedule.
                        A user who stays in the segment is enrolled again on every occurrence. Only segments
                        based on the users table can be used.
                    </DialogContentText>

                    <Grid container spacing={3}>
                        <Grid item xs={6}>
                            <TextField
                                margin="normal"
                                id="repeating-trigger-name"
                                label="Node name"
                                variant="standard"
                                fullWidth
                                value={nodeFormName}
                                onChange={event => {
                                    setNodeFormName(event.target.value);
                                }}
                            />
                        </Grid>
                    </Grid>

                    <Grid container spacing={3} alignItems="flex-end">
                        <Grid item xs={12}>
                            <SegmentFormSelect
                                selectedSegment={selectedSegment}
                                selectedSegmentSourceTable={SEGMENT_SOURCE_TABLE}
                                onSegmentSelectedChange={segmentSelectedChange}
                                error={segmentError}
                                helperText={segmentError ? 'Please select a segment.' : null}
                            />
                        </Grid>
                    </Grid>

                    <ScheduleForm
                        frequency={frequency}
                        day={day}
                        time={time}
                        onFrequencyChange={setFrequency}
                        onDayChange={setDay}
                        onTimeChange={setTime}
                    />
                </DialogContent>

                <DialogActions>
                    <Button
                        color="secondary"
                        onClick={() => {
                            closeDialog();
                        }}
                    >
                        Cancel
                    </Button>

                    <Button
                        color="primary"
                        onClick={() => {
                            if (!selectedSegment) {
                                setSegmentError(true);
                                return;
                            }

                            props.data.node.name = nodeFormName;
                            props.data.node.selectedSegment = selectedSegment;
                            props.data.node.frequency = frequency;
                            props.data.node.day = day;
                            props.data.node.time = time;

                            closeDialog();
                        }}
                    >
                        Save changes
                    </Button>
                </DialogActions>
            </Dialog>
        </div>
    );
};

export default NodeWidget;
