import { DiagramService } from '../services';
import './sass/main.scss';
import { setCanvasNotification } from '../store/canvasSlice';
import { store } from '../store';
const {dispatch} = store;

export class Application {
  diagramService;

  constructor() {
    this.diagramService = new DiagramService();
    this.corruptedPayload = false;
  }

  renderPayload(payload = null) {
    this.payload = payload || this.payload;

    if (!this.payload) {
      return;
    }

    this.payload = JSON.parse(JSON.stringify( this.payload));

    try {
      this.diagramService.restore(this.payload);

      setTimeout(() => {
        this.diagramService.fitView()
      })
    } catch (ex) {
      // In case of rendering error, dump loaded model, log and flag as corrupted scenario
      console.error(ex.message);
      this.corruptedPayload = true;

      dispatch(
        setCanvasNotification({
          open: true,
          variant: 'error',
          text: 'Unable to load corrupted scenario.'
        })
      );
    }
  }

  getDiagramService() {
    return this.diagramService;
  }

  isCorruptedPayload() {
    return this.corruptedPayload;
  }
}
