export function createOption(key, values) {
  return {
    key: key,
    values: {
      selection: values
    }
  };
}

export function actionCreateOption(optionKey, values) {
  return {
    type: 'CREATE_OPTION',
    payload: {
      values: values,
      optionKey: optionKey,
    }
  };
}

export function actionDeleteOption(optionKey) {
  return {
    type: 'DELETE_OPTION',
    payload: {
      optionKey: optionKey,
    }
  };
}

export function reducer(state, action) {
  switch (action.type) {
    case 'CREATE_OPTION':
      return {
        ...state, options: [...state.options, {
          key: action.payload.optionKey,
          values: {
            selection: action.payload.values
          }
        }]
      };
    case 'DELETE_OPTION':
      return {
        ...state, options: state.options.filter(n => n.key !== action.payload.optionKey)
      };
    case 'SET_PARAM_VALUES':
      return {
        ...state, options: state.options.map(option => {
          if (option.key === action.payload.name) {
            return {
              ...option,
              values: action.payload.values
            };
          }
          return option;
        })
      };
    case 'UPDATE_PARAM_VALUES':
      return {
        ...state, options: state.options.map(option => {
          if (option.key === action.payload.name) {
            return {
              ...option,
              values: Object.assign(option.values, action.payload.values)
            };
          }
          return option;
        })
      };
    default:
      throw new Error("unsupported action type " + action.type);
  }
}
