import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['source', 'button'];

    async copy() {
        const code = this.sourceTarget.textContent
            .replace(/^\$ /gm, '')
            .trim();

        if (!navigator.clipboard) {
            return;
        }

        try {
            await navigator.clipboard.writeText(code);
            this.buttonTarget.classList.add('copied');
            setTimeout(() => this.buttonTarget.classList.remove('copied'), 2000);
        } catch {
            // Clipboard access denied — silently ignore
        }
    }
}
