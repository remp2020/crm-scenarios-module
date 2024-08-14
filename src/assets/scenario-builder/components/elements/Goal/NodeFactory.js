import { v4 as uuid } from 'uuid';

export const createNode = (data) => {
  const nodeData = {
    classBaseName: 'diamond-node',
    className: 'goal-node',
    name: data?.name,
    selectedGoals: data?.selectedGoals,
    timeoutTime: data?.timeoutTime,
    timeoutUnit: data?.timeoutUnit,
    recheckPeriodTime: data?.recheckPeriodTime,
    recheckPeriodUnit: data?.recheckPeriodUnit
  };

  return {
    id: data?.id || uuid(),
    type: 'goal',
    data: {node: nodeData}
  };
};
