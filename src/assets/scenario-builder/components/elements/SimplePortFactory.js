import { AbstractPortFactory } from '@projectstorm/react-diagrams';

export class SimplePortFactory extends AbstractPortFactory {
  cb;

  constructor(type, cb) {
    super(type);
    this.cb = cb;
  }

  getNewInstance(initialConfig) {
    return this.cb(initialConfig);
  }
}
