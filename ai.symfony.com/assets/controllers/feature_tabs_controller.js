import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['tab', 'panel'];

    switch(event) {
        const index = parseInt(event.currentTarget.dataset.index, 10);

        this.tabTargets.forEach((tab, i) => {
            tab.classList.toggle('active', i === index);
        });

        this.panelTargets.forEach((panel, i) => {
            panel.classList.toggle('active', i === index);
        });
    }
}
