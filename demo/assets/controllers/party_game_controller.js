import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

// Chrome ships SpeechRecognition (prefixed), Firefox does not: feature-detect and hide
// the mic entirely when it is missing, typing stays the baseline everywhere.
const SpeechRecognitionImpl = window.SpeechRecognition || window.webkitSpeechRecognition;

// Opera advertises webkitSpeechRecognition but ships no working speech backend behind it:
// start() never fires a single event, not even an error, so it is unusable rather than
// merely unreliable. Hide the mic there instead of offering a button that can only ever
// time out.
const IS_OPERA = /\bOPR\//.test(navigator.userAgent);

const SUPPORTS_VOICE = !!SpeechRecognitionImpl && !IS_OPERA;

// SpeechRecognition reports failures only through its "error" event, followed by a plain "end".
// Without surfacing them the mic just silently resets, so map the codes to something actionable.
const VOICE_ERRORS = {
    'not-allowed': 'Microphone access was blocked. Allow it in the browser settings or type your answer.',
    'service-not-allowed': 'Speech recognition is disabled in this browser. Type your answer instead.',
    'audio-capture': 'No microphone found. Type your answer instead.',
    'network': 'The browser could not reach its speech service (Chromium forks like Brave do not ship one). Try Chrome or type your answer.',
    'no-speech': 'Did not catch anything. Try again and speak right after clicking.',
    'language-not-supported': 'Speech recognition does not support your browser language. Type your answer instead.',
};

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
        if (field && count && !field.dataset.wired) {
            field.dataset.wired = '1';
            count.textContent = String(field.value.length);
            field.addEventListener('input', () => { count.textContent = String(field.value.length); });
            field.addEventListener('keydown', (e) => {
                if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') field.closest('form')?.requestSubmit();
            });
        }

        const micButton = document.getElementById('party-mic-btn');
        // the morphing re-render keeps elements alive, a second listener would stop the recording right after starting it
        if (!micButton || micButton.dataset.wired) return;
        micButton.dataset.wired = '1';

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
        // Continuous, so the browser's own silence detection never cuts a player off
        // mid-thought: recording only ends when they click "Stop recording" themselves,
        // which is what triggers the cleanup call.
        recognizer.continuous = true;
        recognizer.interimResults = true;
        recognizer.maxAlternatives = 1;

        const status = document.getElementById('party-voice-status');
        const micButton = document.getElementById('party-mic-btn');
        let finalText = '';
        let interimText = '';
        let errorMessage = null;
        let started = false;
        let gaveUp = false;

        const resetButton = () => {
            if (!micButton) return;
            micButton.disabled = false;
            micButton.textContent = '🎙️ Speak instead';
            micButton.classList.add('party-mint');
            micButton.classList.remove('party-hot');
            micButton.setAttribute('aria-pressed', 'false');
        };

        // Some Chromium forks (observed in Opera) advertise webkitSpeechRecognition but
        // never fire a single event once started, since they ship no speech backend:
        // no "start", no "error", nothing, so the mic just looks dead forever. Give up
        // after a few seconds if we never even got a "start".
        const startupTimeout = setTimeout(() => {
            if (started) return;
            gaveUp = true;
            this.recognition = null;
            try {
                recognizer.abort();
            } catch {
                // best effort, the recognizer may not be listening to us at all
            }
            if (status) status.textContent = 'This browser did not respond to the microphone request. Type your answer instead.';
            resetButton();
        }, 4000);

        recognizer.addEventListener('start', () => {
            if (gaveUp) return;
            started = true;
            clearTimeout(startupTimeout);
            micButton?.classList.add('party-hot');
            micButton?.classList.remove('party-mint');
            micButton?.setAttribute('aria-pressed', 'true');
            if (micButton) micButton.textContent = '⏹️ Stop recording';
            if (status) status.textContent = 'Listening…';
        });

        recognizer.addEventListener('result', (event) => {
            if (gaveUp) return;
            let interim = '';
            for (let i = event.resultIndex; i < event.results.length; i++) {
                const transcript = event.results[i][0].transcript;
                if (event.results[i].isFinal) {
                    finalText += `${transcript} `;
                } else {
                    interim += transcript;
                }
            }
            interimText = interim;
            if (status) status.textContent = (finalText + interim).trim() || 'Listening…';
        });

        recognizer.addEventListener('error', (event) => {
            if (gaveUp || event.error === 'aborted') return;
            errorMessage = VOICE_ERRORS[event.error] ?? `Speech recognition failed (${event.error}). Type your answer instead.`;
        });

        recognizer.addEventListener('end', () => {
            clearTimeout(startupTimeout);
            if (gaveUp) return;
            this.recognition = null;
            // stopping manually can end the session before the last phrase was finalized
            const text = (finalText + interimText).trim();
            if (text !== '') {
                if (status) status.textContent = `Tidying up: “${text}”`;
                if (micButton) {
                    micButton.disabled = true;
                    micButton.textContent = '⏳ Tidying up…';
                }
                this.component.action('requestCleanup', { transcript: text });
                return;
            }
            if (status) status.textContent = errorMessage ?? '';
            resetButton();
        });

        try {
            recognizer.start();
        } catch (e) {
            clearTimeout(startupTimeout);
            this.recognition = null;
            if (status) status.textContent = `Could not start the microphone (${e.message}). Type your answer instead.`;
        }
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
