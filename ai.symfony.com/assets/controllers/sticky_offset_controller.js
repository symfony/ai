import { Controller } from '@hotwired/stimulus';

/**
 * Exposes the height of a sticky element as a CSS custom property of the controller
 * element, so that the sticky elements below can stack under it, whatever the number
 * of lines it wraps on.
 */
export default class extends Controller {
    static targets = ['source'];
    static values = { property: String };

    initialize() {
        this.observer = new ResizeObserver(() => this.update());
    }

    sourceTargetConnected(element) {
        // measured right away, the observer only reports changes when the page renders
        this.update();
        this.observer.observe(element);
    }

    sourceTargetDisconnected(element) {
        this.observer.unobserve(element);
    }

    disconnect() {
        this.observer.disconnect();
    }

    update() {
        if (this.hasSourceTarget) {
            // not offsetHeight, rounded, which could leave a gap between the stacked elements
            this.element.style.setProperty(this.propertyValue, `${this.sourceTarget.getBoundingClientRect().height}px`);
        }
    }
}
