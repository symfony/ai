import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this._onChange = this._onChange.bind(this);
        this._onClear = this._onClear.bind(this);
        this._onUploadCleared = this._onUploadCleared.bind(this);

        this.element.addEventListener('dropzone:change', this._onChange);
        this.element.addEventListener('dropzone:clear', this._onClear);
        document.addEventListener('crop:upload-cleared', this._onUploadCleared);
    }

    disconnect() {
        this.element.removeEventListener('dropzone:change', this._onChange);
        this.element.removeEventListener('dropzone:clear', this._onClear);
        document.removeEventListener('crop:upload-cleared', this._onUploadCleared);
    }

    async _onChange(event) {
        const cropComponent = document.getElementById('crop-component').__component;

        cropComponent.set('presetId', null);
        cropComponent.set('imageData', await this.blobToBase64(event.detail));
        await cropComponent.render();
    }

    async _onClear() {
        const cropComponent = document.getElementById('crop-component').__component;

        cropComponent.set('imageData', null);
        await cropComponent.render();
    }

    _onUploadCleared() {
        const previewClearButton = this.element.parentElement?.querySelector(
            '[data-symfony--ux-dropzone--dropzone-target="previewClearButton"]',
        );

        if (previewClearButton) {
            previewClearButton.click();
        }
    }

    blobToBase64(blob) {
        return new Promise((resolve) => {
            const reader = new FileReader();
            reader.readAsDataURL(blob);
            reader.onloadend = () => resolve(reader.result);
        });
    }
}
