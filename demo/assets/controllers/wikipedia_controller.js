import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

export default class extends Controller {
    async initialize() {
        this.component = await getComponent(this.element);
        this.scrollToBottom();

        this.component.on('loading.state:started', (e,r) => {
            if (r.actions.includes('reset')) {
                // Answers pushed via Mercure are not removed by the live component's re-render
                this.element.querySelectorAll('.research-message').forEach((answer) => answer.remove());
            }

            if (!r.actions.includes('submit')) {
                return;
            }

            document
                .getElementById('loading-message')
                .getElementsByClassName('user-message')[0].innerHTML = this.component.getData('message');
            document.getElementById('welcome')?.remove();
            document.getElementById('loading-message').removeAttribute('class');
            document.getElementById('chat-message').value = '';
            this.scrollToBottom();
        });

        this.component.on('loading.state:finished', () => {
            document.getElementById('loading-message').setAttribute('class', 'd-none');
        });

        this.component.on('render:finished', () => {
            this.scrollToBottom();
        });
    };

    connect() {
        this.onStreamRender = (event) => {
            const render = event.detail.render;
            event.detail.render = async (stream) => {
                await render(stream);
                this.scrollToBottom();
            };
        };
        document.addEventListener('turbo:before-stream-render', this.onStreamRender);
    }

    disconnect() {
        document.removeEventListener('turbo:before-stream-render', this.onStreamRender);
    }

    scrollToBottom() {
        const chatBody = document.getElementById('chat-body');
        chatBody.scrollTop = chatBody.scrollHeight;
    }
}
