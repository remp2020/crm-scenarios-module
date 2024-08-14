import { v4 as uuid } from 'uuid';

export const createNode = (data) => {
  const nodeData = {
    classBaseName: 'square-node',
    className: 'email-node',
    name: data?.name,
    selectedMail: data?.selectedMail
  };

  return {
    id: data?.id || uuid(),
    type: 'email',
    data: {node: nodeData}
  };
};
