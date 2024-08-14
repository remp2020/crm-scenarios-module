import { v4 as uuid } from 'uuid';

export const createNode = (data) => {
  const nodeData = {
    classBaseName: 'square-node',
    className: 'before-trigger-node',
    name: data?.name,
    selectedTrigger: data?.selectedTrigger,
    time: data?.time !== undefined ? data.time : 10,
    timeUnit: data?.timeUnit !== undefined ? data.timeUnit : 'hours'
  };

  return {
    id: data?.id || uuid(),
    type: 'before_trigger',
    data: {node: nodeData}
  };
};
