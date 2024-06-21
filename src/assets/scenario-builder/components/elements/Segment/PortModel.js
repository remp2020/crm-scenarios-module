import { LeftRightBottomPort } from './../Ports';

export class PortModel extends LeftRightBottomPort {
  constructor(position = 'top') {
    super(position, 'segment');
  }
}
