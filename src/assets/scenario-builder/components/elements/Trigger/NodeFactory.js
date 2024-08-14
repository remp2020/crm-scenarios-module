import { v4 as uuid } from 'uuid';

export const createNode = (data) => {
  const nodeData = {
    classBaseName: 'square-node',
    className: 'trigger-node',
    name: data?.name,
    selectedTrigger: data?.selectedTrigger
  };

  return {
    id: data?.id || uuid(),
    type: 'trigger',
    data: {node: nodeData}
  };
};
