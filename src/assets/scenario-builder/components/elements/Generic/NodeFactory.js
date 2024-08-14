import { v4 as uuid } from 'uuid';

export const createNode = (data) => {
  const nodeData = {
    classBaseName: 'square-node',
    className: 'generic-node',
    name: data?.name,
    selectedGeneric: data?.selectedGeneric,
    options: data?.options
  };

  return {
    id: data?.id || uuid(),
    type: 'generic',
    data: {node: nodeData}
  };
};
