import {v4 as uuid} from 'uuid';

export const createNode = (data) => {
    const nodeData = {
        classBaseName: 'square-node',
        className: 'repeating-trigger-node',
        name: data?.name,
        selectedSegment: data?.selectedSegment !== undefined ? data.selectedSegment : null,
        frequency: data?.frequency !== undefined ? data.frequency : 'daily',
        day: data?.day !== undefined ? data.day : null,
        time: data?.time !== undefined ? data.time : '09:00'
    };

    return {
        id: data?.id || uuid(),
        type: 'repeating_trigger',
        data: {node: nodeData}
    };
};
