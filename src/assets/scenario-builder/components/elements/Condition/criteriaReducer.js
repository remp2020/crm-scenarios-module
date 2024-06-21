import uuidv4 from 'uuid/v4';

///////////////////////////
// local reducer and state for criteria builder
///////////////////////////

export function emptyNode() {
	return {
		id: uuidv4(),
		key: '',
		params: [],
	};
}

export function actionSetKeyForNode(nodeId, criterionKey, criterionParams) {
	return {
		type: 'SET_KEY_FOR_NODE',
		payload: {
			key: criterionKey,
			nodeId: nodeId,
			// Criterion params are associated with key, 
			// but since we do not have access to blueprint here, request criterionParams as additional parameter
			params: criterionParams 
		}
	};
}

export function actionDeleteNode(nodeId) {
	return {
		type: 'DELETE_NODE',
		payload: {
			nodeId: nodeId,
		}
	};
}

export function actionAddCriterion() {
	return {
		type: 'ADD_CRITERION'
	};
}

export function actionSetEvent(event) {
	return {
		type: 'SET_EVENT',
		payload: event
	};
}

export function reducer(state, action) {
	switch (action.type) {
		// params actions
		case 'UPDATE_PARAM_VALUES': {
			let [nodeId, paramKey] = action.payload.name;
			return {
				...state, nodes: state.nodes.map(node => {
					if (node.id === nodeId) {
						return {
							...node, params: node.params.map(param => {
								if (param.key === paramKey) {
									return {...param, values: Object.assign(param.values, action.payload.values)};
								}
								return param;
							})
						};
					}
					return node;
				})
			};
		}
		case 'SET_PARAM_VALUES': {
			let [nodeId, paramKey] = action.payload.name;
			return {
				...state, nodes: state.nodes.map(node => {
					if (node.id === nodeId) {
						return {
							...node, params: node.params.map(param => {
								if (param.key === paramKey) {
									return {...param, values: action.payload.values};
								}
								return param;
							})
						};
					}
					return node;
				})
			};
		}

		// internal criteriaReducer actions
		case 'SET_EVENT':
			// this also resets nodes state
			return {
				...state, nodes: [emptyNode()], event: action.payload
			};
		case 'ADD_CRITERION':
			return {
				...state, nodes: [...state.nodes, emptyNode()]
			};
		case 'DELETE_NODE':
			return {
				...state, nodes: state.nodes.filter(n => n.id !== action.payload.nodeId)
			};
		case 'SET_KEY_FOR_NODE': {
			let newNodes = state.nodes.map(node => {
				if (action.payload.nodeId === node.id) return {
					id: node.id,
					key: action.payload.key,
					// TODO: load params from blueprint without needing them in a payload (since they are associated with a criteria key)
					params: action.payload.params, 
				};
				return node;
			});
			return {
				...state, nodes: newNodes
			};
		}
			
		default:
			throw new Error("unsupported action type " + action.type);
	}
}
