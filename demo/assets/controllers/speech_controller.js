import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

export default class extends Controller {
    async initialize() {
        this.component = await getComponent(this.element);
        this.scrollToBottom();

        const resetButton = document.getElementById('chat-reset');
        resetButton.addEventListener('click', (event) => {
            this.component.action('reset');
        });

        const startButton = document.getElementById('micro-start');
        const stopButton = document.getElementById('micro-stop');
        const botThinkingButton = document.getElementById('bot-thinking');

        startButton.addEventListener('click', (event) => {
            event.preventDefault();
            startButton.classList.add('d-none');
            stopButton.classList.remove('d-none');
            this.startRecording();
        });
        stopButton.addEventListener('click', (event) => {
            event.preventDefault();
            stopButton.classList.add('d-none');
            botThinkingButton.classList.remove('d-none');
            this.mediaRecorder.stop();
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
            botThinkingButton.classList.add('d-none');
            startButton.classList.remove('d-none');
        });

        this.component.on('render:finished', () => {
            this.scrollToBottom();
        });
    };

    async startRecording() {
        const stream = await navigator.mediaDevices.getUserMedia({audio: true});
        this.mediaRecorder = new MediaRecorder(stream);
        let audioChunks = [];

        this.mediaRecorder.ondataavailable = (event) => {
            audioChunks.push(event.data);
        };

        this.mediaRecorder.onstop = async () => {
            const audioBlob = new Blob(audioChunks, {type: 'audio/wav'});
            this.mediaRecorder.stream.getAudioTracks().forEach(track => track.stop());

            const base64String = await this.blobToBase64(audioBlob);
            this.component.action('submit', { audio: base64String });
        };

        this.mediaRecorder.start();
    }

    scrollToBottom() {
        const chatBody = document.getElementById('chat-body');
        chatBody.scrollTop = chatBody.scrollHeight;
    }

    blobToBase64(blob) {
        return new Promise((resolve) => {
            const reader = new FileReader();
            reader.readAsDataURL(blob);
            reader.onloadend = () => resolve(reader.result.split(',')[1]);
        });
    }
}
