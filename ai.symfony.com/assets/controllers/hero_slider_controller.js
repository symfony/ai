import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['slide', 'indicator', 'progress'];
    static values = {
        interval: { type: Number, default: 8000 },
    };

    connect() {
        this.currentIndex = 0;
        this.paused = false;
        this.prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        this.slideTargets.forEach((slide, index) => {
            if (0 !== index) {
                slide.classList.remove('active');
            }
        });

        this.#scheduleNext(this.intervalValue);

        this.handleMouseEnter = () => this.#pause();
        this.handleMouseLeave = () => this.#resume();
        this.element.addEventListener('mouseenter', this.handleMouseEnter);
        this.element.addEventListener('mouseleave', this.handleMouseLeave);
    }

    disconnect() {
        this.#clearTimer();
        this.element.removeEventListener('mouseenter', this.handleMouseEnter);
        this.element.removeEventListener('mouseleave', this.handleMouseLeave);
    }

    goToSlide(event) {
        const index = parseInt(event.currentTarget.dataset.index, 10);

        if (index === this.currentIndex) {
            return;
        }

        this.#clearTimer();
        this.#showSlide(index);
        this.#scheduleNext(this.intervalValue);
    }

    #showSlide(index) {
        this.slideTargets[this.currentIndex].classList.remove('active');
        this.slideTargets[index].classList.add('active');

        this.indicatorTargets.forEach((indicator, i) => {
            indicator.classList.toggle('active', i === index);
        });

        this.currentIndex = index;
    }

    #scheduleNext(delay, resetBar = true) {
        if (this.prefersReducedMotion || this.paused) {
            return;
        }

        this.remainingTime = delay;
        this.timerStart = Date.now();

        this.#startProgressBar(delay, resetBar);

        this.timerId = setTimeout(() => {
            this.timerId = null;
            const nextIndex = (this.currentIndex + 1) % this.slideTargets.length;
            this.#showSlide(nextIndex);
            this.#scheduleNext(this.intervalValue);
        }, delay);
    }

    #pause() {
        this.paused = true;

        if (this.timerId == null) {
            return;
        }

        clearTimeout(this.timerId);
        this.timerId = null;

        const elapsed = Date.now() - this.timerStart;
        this.remainingTime = Math.max(0, this.remainingTime - elapsed);

        this.#freezeProgressBar();
    }

    #resume() {
        this.paused = false;
        this.#scheduleNext(this.remainingTime, false);
    }

    #clearTimer() {
        if (this.timerId) {
            clearTimeout(this.timerId);
            this.timerId = null;
        }

        this.remainingTime = this.intervalValue;
    }

    #startProgressBar(duration, reset = true) {
        if (!this.hasProgressTarget) {
            return;
        }

        const bar = this.progressTarget;

        if (reset) {
            bar.style.transition = 'none';
            bar.style.width = '0';
        }

        // Force reflow so the browser registers the current width before animating
        bar.offsetWidth; // eslint-disable-line no-unused-expressions

        bar.style.transition = 'width ' + duration + 'ms linear';
        bar.style.width = '100%';
    }

    #freezeProgressBar() {
        if (!this.hasProgressTarget) {
            return;
        }

        const bar = this.progressTarget;
        const currentWidth = getComputedStyle(bar).width;
        bar.style.transition = 'none';
        bar.style.width = currentWidth;
    }
}
