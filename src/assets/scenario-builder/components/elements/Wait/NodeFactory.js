import { v4 as uuid } from 'uuid';

export const createNode = (data) => {
  const nodeData = {
    classBaseName: 'round-node',
    className: 'wait-node',
    name: data?.name,
    waitingTime: data?.waitingTime !== undefined ? data?.waitingTime : 10,
    waitingUnit: data?.waitingUnit !== undefined ? data?.waitingUnit : 'minutes'
  };

  return {
    id: data?.id || uuid(),
    type: 'wait',
    data: {node: nodeData}
  };
};
