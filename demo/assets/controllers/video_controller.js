import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

/**
 * Heavily inspired by https://github.com/ngxson/smolvlm-realtime-webcam
 */
export default class extends Controller {
    async initialize() {
        this.component = await getComponent(this.element);

        this.video = document.getElementById('videoFeed');
        this.canvas = document.getElementById('canvas');

        await this.initCamera();
    };

    async initCamera() {
        try {
            this.stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
            this.video.srcObject = this.stream;
            console.log('Camera access granted. Ready to start.');
        } catch (err) {
            console.error('Error accessing camera:', err);
            alert(`Error accessing camera: ${err.name}. Make sure you've granted permission and are on HTTPS or localhost.`);
        }
    }

    async submit(event) {
        event.preventDefault();

        const image = this.captureImage();

        if (null === image) {
            console.warn('No image captured. Cannot submit message.');
            return;
        }

        this.component.set('image', image, false);
        await this.component.action('submit');
        document.getElementById('chat-message').value = '';
    }

    captureImage() {
        if (!this.stream || !this.video.videoWidth) {
            console.warn('Video stream not ready for capture.');
            return null;
        }

        this.canvas.width = this.video.videoWidth;
        this.canvas.height = this.video.videoHeight;
        const context = this.canvas.getContext('2d');
        context.drawImage(this.video, 0, 0, this.canvas.width, this.canvas.height);
        return this.canvas.toDataURL('image/jpeg', 0.8);
    }
}
