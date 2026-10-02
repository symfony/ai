import { Controller } from '@hotwired/stimulus';

/**
 * Empties a search field and gives the focus back to it. The dispatched input
 * event lets a Live Component update the bound model, as if the text was erased.
 */
export default class extends Controller {
    static targets = ['input'];

    clear() {
        this.inputTarget.value = '';
        this.inputTarget.dispatchEvent(new Event('input', { bubbles: true }));
        this.inputTarget.focus();
    }
}
