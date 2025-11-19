import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.element.addEventListener('dropzone:change', this._onChange.bind(this));
        this.element.addEventListener('dropzone:clear', this._onClear);
    }

    disconnect() {
        this.element.removeEventListener('dropzone:change', this._onChange.bind(this));
        this.element.removeEventListener('dropzone:clear', this._onClear);
    }

    async _onChange(event) {
        const cropComponent = document.getElementById('crop-component').__component;

        cropComponent.set('imageData', await this.blobToBase64(event.detail));
    }

    _onClear(event) {
        const cropComponent = document.getElementById('crop-component').__component;

        cropComponent.set('imageData', null);
    }

    blobToBase64(blob) {
        return new Promise((resolve) => {
            const reader = new FileReader();
            reader.readAsDataURL(blob);
            reader.onloadend = () => resolve(reader.result);
        });
    }
}
