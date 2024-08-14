export const bemClassName = (selector, classBaseName, className) => {
  return `${classBaseName}${selector} ${className}${selector} `;
};
