import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

export default class extends Controller {
    async initialize() {
        this.component = await getComponent(this.element);

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
            document.getElementById('recipe-card')?.setAttribute('class', 'd-none');
            document.getElementById('loading-message').removeAttribute('class');
        });

        this.component.on('loading.state:finished', () => {
            document.getElementById('loading-message').setAttribute('class', 'd-none');
            document.getElementById('recipe-card')?.removeAttribute('class');
        });
    };

    submitMessage() {
        const input = document.getElementById('chat-message');
        const message = input.value;
        this.component.action('submit', { message });
        input.value = '';
    }
}
