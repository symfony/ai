import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

export default class extends Controller {
    async initialize() {
        this.component = await getComponent(this.element);

        this.component.on('loading.state:started', (e,r) => {
            document.getElementById('chat-message').value = '';
        });
    };
}
