import React from 'react';
import Grid from '@material-ui/core/Grid';
import ButtonGroup from "@material-ui/core/ButtonGroup";
import {makeStyles} from "@material-ui/core/styles";
import Button from "@material-ui/core/Button";
import {useSelector} from "react-redux";
import SegmentFormSelect from "./SegmentFormSelect";

const useCriteriaBuilderStyles = makeStyles({
    selectedButton: {
        backgroundColor: "#E4E4E4"
    },
    deselectedButton: {
        color:  "#A6A6A6"
    }
});

function getSourceTable(selectedSegmentSourceTable, selectedSegment, items) {
    if (selectedSegmentSourceTable) {
        return selectedSegmentSourceTable;
    }

    if (selectedSegment) {
        const segment = items.filter(item => item.segments.filter(item => item.code === selectedSegment).length > 0);

        return (segment[0] && segment[0].hasOwnProperty('table')) ? segment[0].table : 'users';
    }

    return 'users';
}

export default function SegmentSelector(props) {
    const classes = useCriteriaBuilderStyles();
    const items = useSelector(state => state.segments.avalaibleSegments);
    const sourceTable = getSourceTable(props.selectedSegmentSourceTable, props.selectedSegment, items);

    return (
        <Grid container item xs={12} spacing={2}>
            <Grid item xs={12}>
                <ButtonGroup aria-label="outlined button group">
                    {items.map(item => (
                        <Button
                            onClick={() => props.onSegmentTypeButtonClick(item.table)}
                            className={sourceTable === item.table ? classes.selectedButton : classes.deselectedButton}
                            key={item.table}>{item.table}
                        </Button>
                    ))}
                </ButtonGroup>
            </Grid>
            <SegmentFormSelect
                selectedSegment={props.selectedSegment}
                selectedSegmentSourceTable={sourceTable}
                onSegmentSelectedChange={props.onSegmentSelectedChange}
            >
            </SegmentFormSelect>
        </Grid>
    );
}