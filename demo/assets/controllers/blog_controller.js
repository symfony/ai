import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

export default class extends Controller {
    async initialize() {
        this.component = await getComponent(this.element);
        this.scrollToBottom();

        const input = document.getElementById('chat-message');
        input.addEventListener('keypress', (event) => {
            if (event.key === 'Enter') {
                this.submitMessage();
            }
        });
        input.focus();

        const resetButton = document.getElementById('chat-reset');
        resetButton.addEventListener('click', (event) => {
            this.component.action('reset');
        });

        const submitButton = document.getElementById('chat-submit');
        submitButton.addEventListener('click', (event) => {
            this.submitMessage();
        });

        this.component.on('loading.state:started', (e,r) => {
            if (r.actions.includes('reset')) {
                return;
            }
            document.getElementById('welcome')?.remove();
            document.getElementById('loading-message').removeAttribute('class');
            this.scrollToBottom();
        });

        this.component.on('loading.state:finished', () => {
            document.getElementById('loading-message').setAttribute('class', 'd-none');
        });

        this.component.on('render:finished', () => {
            this.scrollToBottom();
        });
    };

    submitMessage() {
        const input = document.getElementById('chat-message');
        const message = input.value;
        document
            .getElementById('loading-message')
            .getElementsByClassName('user-message')[0].innerHTML = message;
        this.component.action('submit', { message });
        input.value = '';
    }

    scrollToBottom() {
        const chatBody = document.getElementById('chat-body');
        chatBody.scrollTop = chatBody.scrollHeight;
    }
}
