import { Controller } from '@hotwired/stimulus';

/**
 * Sends one of the suggested prompts of a chat as if it was typed into the chat input.
 */
export default class extends Controller {
    send({ params: { text } }) {
        const form = this.element.querySelector('form');
        const input = form?.querySelector('input[type="text"]');

        if (!input || input.disabled) {
            return;
        }

        input.value = text;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        form.requestSubmit();
    }
}
