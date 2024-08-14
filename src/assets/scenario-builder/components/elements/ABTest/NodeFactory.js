import { v4 as uuid } from 'uuid';

export const createNode = (data) => {
  const nodeData = {
    classBaseName: 'diamond-node',
    className: 'abtest-node',
    name: data?.name,
    scenarioName: data?.scenarioName
  };

  if (data?.variants) {
    nodeData.variants = data.variants;
  } else {
    nodeData.variants = [{
      code: uuid().slice(0, 6),
      name: 'Variant A',
      distribution: 50
    }, {
      code: uuid().slice(0, 6),
      name: 'Variant B',
      distribution: 50
    }];
  }

  return {
    id: data.id || uuid(),
    type: 'ab_test',
    data: {node: nodeData}
  };
};
