import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

export default class extends Controller {
    async initialize() {
        this.component = await getComponent(this.element);
        this.scrollToBottom();

        this.component.on('loading.state:started', (e,r) => {
            if (!r.actions.includes('submit')) {
                return;
            }

            document
                .getElementById('loading-message')
                .getElementsByClassName('user-message')[0].innerHTML = this.component.getData('message');
            document.getElementById('welcome')?.remove();
            this.element.querySelector('.movie-modal-overlay')?.remove();
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

    ask({ params: { prompt } }) {
        const input = document.getElementById('chat-message');

        input.value = prompt;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.form.requestSubmit();
    }

    scrollToBottom() {
        const chatBody = document.getElementById('chat-body');
        chatBody.scrollTop = chatBody.scrollHeight;
    }
}
