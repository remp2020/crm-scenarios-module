import { v4 as uuid } from 'uuid';

export const createNode = (data) => {
  const nodeData = {
    classBaseName: 'square-node',
    className: 'banner-node',
    name: data?.name,
    selectedBanner: data?.selectedBanner,
    expiresInTime: data?.expiresInTime,
    expiresInUnit: data?.expiresInUnit
  };

  return {
    id: data?.id || uuid(),
    type: 'banner',
    data: {node: nodeData}
  };
};
