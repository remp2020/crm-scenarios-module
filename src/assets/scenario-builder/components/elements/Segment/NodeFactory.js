import { v4 as uuid } from 'uuid';

export const createNode = (data) => {
  const nodeData = {
    classBaseName: 'diamond-node',
    className: 'segment-node',
    name: data?.name,
    selectedSegment: data?.selectedSegment,
  }

  return {
    id: data?.id || uuid(),
    type: 'segment',
    data: { node: nodeData }
  }
}
