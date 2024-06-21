import { LeftRightPort } from './../Ports';

export class PortModel extends LeftRightPort {
  constructor(position = 'left') {
    super(position, 'wait');
  }
}
