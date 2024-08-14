import { v4 as uuid } from 'uuid';

export const createNode = (data) => {
  const nodeData = {
    classBaseName: 'diamond-node',
    className: 'condition-node',
    name: data?.name,
    conditions: data?.conditions
  };

  return {
    id: data?.id || uuid(),
    type: 'condition',
    data: {node: nodeData}
  };
};
