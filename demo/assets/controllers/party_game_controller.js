import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

// Chrome ships SpeechRecognition (prefixed), Firefox does not: feature-detect and hide
// the mic entirely when it is missing, typing stays the baseline everywhere.
const SpeechRecognitionImpl = window.SpeechRecognition || window.webkitSpeechRecognition;
const SUPPORTS_VOICE = !!SpeechRecognitionImpl;

const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const verdictClass = (p) => p >= 0.85 ? 'party-stamp-sold' : p >= 0.6 ? 'party-stamp-yes' : p >= 0.4 ? 'party-stamp-maybe' : p >= 0.15 ? 'party-stamp-no' : 'party-stamp-never';

const judgeFace = (convincing, chaosLevel) => {
    if (chaosLevel === 'Unhinged') return '🤯';
    if (convincing >= 0.85) return '🤩';
    if (convincing >= 0.6) return '😏';
    if (convincing >= 0.4) return '🤔';
    if (convincing >= 0.15) return '🙄';
    return '😤';
};

export default class extends Controller {
    async initialize() {
        this.component = await getComponent(this.element);
        this.recognition = null;
        this.lastAnimatedKey = null;

        this.component.on('render:finished', () => this.onRenderFinished());
        this.onRenderFinished();
    }

    onRenderFinished() {
        const screen = this.element.dataset.screen;

        if (screen === 'input') {
            this.wireInputScreen();
        }
        if (screen === 'reveal') {
            this.animateReveal();
        }
        if (screen === 'final') {
            this.launchConfetti();
        }
    }

    // --- input screen: character count, submit shortcut, mic ---------------

    wireInputScreen() {
        const field = document.getElementById('party-answer');
        const count = document.getElementById('party-answer-count');
        if (field && count) {
            count.textContent = String(field.value.length);
            field.addEventListener('input', () => { count.textContent = String(field.value.length); });
            field.addEventListener('keydown', (e) => {
                if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') field.closest('form')?.requestSubmit();
            });
        }

        const micButton = document.getElementById('party-mic-btn');
        if (!micButton) return;

        if (!SUPPORTS_VOICE) {
            micButton.closest('[data-party-game-target="voiceRow"]')?.remove();
            return;
        }

        micButton.addEventListener('click', () => {
            if (this.recognition) {
                this.recognition.stop();
            } else {
                this.startRecording();
            }
        });
    }

    startRecording() {
        if (this.recognition) return;
        const recognizer = new SpeechRecognitionImpl();
        this.recognition = recognizer;
        recognizer.lang = navigator.language || 'en-US';
        recognizer.continuous = false;
        recognizer.interimResults = true;
        recognizer.maxAlternatives = 1;

        const status = document.getElementById('party-voice-status');
        const micButton = document.getElementById('party-mic-btn');
        let finalText = '';

        recognizer.addEventListener('start', () => {
            micButton?.classList.add('party-hot');
            micButton?.classList.remove('party-mint');
            micButton?.setAttribute('aria-pressed', 'true');
            if (micButton) micButton.textContent = '⏹️ Stop recording';
            if (status) status.textContent = 'Listening…';
        });

        recognizer.addEventListener('result', (event) => {
            let interim = '';
            for (let i = event.resultIndex; i < event.results.length; i++) {
                const transcript = event.results[i][0].transcript;
                if (event.results[i].isFinal) {
                    finalText += `${transcript} `;
                } else {
                    interim += transcript;
                }
            }
            if (status) status.textContent = (finalText + interim).trim() || 'Listening…';
        });

        recognizer.addEventListener('end', () => {
            this.recognition = null;
            const text = finalText.trim();
            if (text !== '') {
                this.component.action('requestCleanup', { transcript: text });
                return;
            }
            if (status) status.textContent = '';
            if (micButton) {
                micButton.textContent = '🎙️ Speak instead';
                micButton.classList.add('party-mint');
                micButton.classList.remove('party-hot');
                micButton.setAttribute('aria-pressed', 'false');
            }
        });

        recognizer.start();
    }

    // --- reveal screen animation --------------------------------------------

    async animateReveal() {
        const card = document.getElementById('party-reveal-card');
        if (!card) return;

        const key = `${card.dataset.player}:${card.dataset.index}`;
        if (this.lastAnimatedKey === key) return;
        this.lastAnimatedKey = key;

        const slot = document.getElementById('party-gauge-slot');
        const tpl = document.getElementById('party-tpl-gauge');
        if (slot && tpl && !slot.firstElementChild) {
            slot.appendChild(tpl.content.cloneNode(true));
        }
        const needle = slot?.querySelector('.party-gauge-needle');
        const face = document.getElementById('party-judge-face');
        const stamp = document.getElementById('party-stamp');
        const pct = document.getElementById('party-pct');
        const next = document.getElementById('party-next-zone');

        const convincing = parseFloat(card.dataset.convincing);
        const chaosLevel = card.dataset.chaosLevel;

        const alive = () => document.getElementById('party-reveal-card') === card;

        await wait(900);
        if (!alive() || !needle) return;
        needle.style.transform = `rotate(${-90 + 180 * convincing}deg)`;
        await wait(1100);
        if (!alive()) return;
        face?.classList.remove('party-thinking');
        if (face) face.textContent = judgeFace(convincing, chaosLevel);
        face?.classList.add('party-react');
        stamp?.classList.add(verdictClass(convincing), 'party-show');
        if (stamp) stamp.textContent = card.dataset.verdict;
        pct?.classList.add('party-show');
        if (pct) pct.textContent = `${Math.round(convincing * 100)}% chance the judge buys it`;
        await wait(500);
        if (!alive()) return;

        const light = (id, score) => {
            const meter = document.getElementById(id);
            if (!meter) return;
            meter.classList.add('party-show');
            const lit = Math.round(parseFloat(score)) + 1;
            meter.querySelectorAll('.party-m-bar span').forEach((span, i) => {
                setTimeout(() => { if (i < lit) span.classList.add('party-on'); }, 120 * i);
            });
        };
        light('party-meter-creative', card.dataset.creativity);
        await wait(350);
        if (!alive()) return;
        light('party-meter-chaos', card.dataset.chaos);
        await wait(500);
        if (!alive()) return;
        const vibe = document.getElementById('party-vibe');
        if (vibe) {
            vibe.textContent = card.dataset.vibe;
            vibe.classList.add('party-show');
        }
        await wait(300);
        if (!alive()) return;
        next?.classList.add('party-show');
    }

    // --- final screen confetti ------------------------------------------------

    launchConfetti() {
        const box = document.getElementById('party-confetti');
        if (!box || box.dataset.launched) return;
        box.dataset.launched = '1';

        const colors = ['#ff3d7f', '#ffd23f', '#26d0a8', '#5b2a86', '#58b6ff', '#ff5a36'];
        for (let i = 0; i < 70; i++) {
            const piece = document.createElement('i');
            piece.style.left = `${Math.random() * 100}%`;
            piece.style.background = colors[i % colors.length];
            piece.style.animationDuration = `${2.6 + Math.random() * 2.4}s`;
            piece.style.animationDelay = `${Math.random() * 1.8}s`;
            piece.style.transform = `rotate(${Math.random() * 360}deg)`;
            box.appendChild(piece);
        }
        setTimeout(() => box.remove(), 7000);
    }
}
