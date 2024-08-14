import { v4 as uuid } from 'uuid';

export const createNode = (data) => {
  const nodeData = {
    classBaseName: 'square-node',
    className: 'push-notification-node',
    name: data?.name,
    selectedTemplate: data?.selectedTemplate,
    selectedApplication: data?.selectedApplication
  };

  return {
    id: data?.id || uuid(),
    type: 'push_notification',
    data: {node: nodeData}
  };
};
