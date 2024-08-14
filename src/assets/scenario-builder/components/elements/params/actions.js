export function actionSetParamValues(name, values) {
	return {
		type: 'SET_PARAM_VALUES',
		payload: {
			name: name,
			values: values,
		}
	};
}

export function actionUpdateParamValues(name, values) {
	return {
		type: 'UPDATE_PARAM_VALUES',
		payload: {
			name: name,
			values: values,
		}
	};
}
