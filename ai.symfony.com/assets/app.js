import './stimulus_bootstrap.js';
import { Tooltip } from 'bootstrap';
import 'bootstrap/dist/css/bootstrap.min.css';
import './styles/app.css';
import AOS from 'aos';
import 'aos/dist/aos.css';

document.addEventListener('DOMContentLoaded', function() {
    new App();
    AOS.init({
        duration: 700,
        once: true,
    });
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new Tooltip(el));
    initColorShiftSync();
});

// Period of the colorShift keyframes in app.css, in milliseconds.
const COLOR_SHIFT_PERIOD = 60000;

// Selector matching every element that runs the colorShift animation (directly
// or once it gains its active class).
const COLOR_SHIFT_SELECTOR = [
    '.color-shift',
    '.arch-shift-strong', '.arch-shift-medium', '.arch-shift-light', '.arch-shift-muted',
    '.feature-tab',
    '.cookbook-card-icon',
    '.cookbook-filter',
    '.logo-icon', '.logo-ai',
].join(', ');

/**
 * Anchor an element's animation to the shared wall-clock cycle. Each element runs
 * its own colorShift timeline, so an element created or toggled active mid-cycle
 * would otherwise start from phase zero and drift out of sync with the rest of the
 * page. A negative animation-delay equal to the current offset into the period
 * makes every element resolve to phase `Date.now() % period`, regardless of when
 * its animation actually begins.
 */
function alignColorShift(element) {
    element.style.animationDelay = `-${Date.now() % COLOR_SHIFT_PERIOD}ms`;
}

/**
 * Keep all brand-color-shift elements in phase: align the ones present at load,
 * then re-align any added later (Live Component re-renders) or toggled active
 * (feature tabs, cookbook filters) via a MutationObserver.
 */
function initColorShiftSync() {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    document.querySelectorAll(COLOR_SHIFT_SELECTOR).forEach(alignColorShift);

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if ('attributes' === mutation.type) {
                if (mutation.target.matches(COLOR_SHIFT_SELECTOR)) {
                    alignColorShift(mutation.target);
                }

                return;
            }

            mutation.addedNodes.forEach((node) => {
                if (Node.ELEMENT_NODE !== node.nodeType) {
                    return;
                }

                if (node.matches(COLOR_SHIFT_SELECTOR)) {
                    alignColorShift(node);
                }

                node.querySelectorAll(COLOR_SHIFT_SELECTOR).forEach(alignColorShift);
            });
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['class'],
    });
}

class App {
    constructor() {
        this.#initializeThemeSwitcher();
    }

    #initializeThemeSwitcher() {
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const html = document.documentElement;

        const icons = {
            auto: document.getElementById('icon-theme-auto').content,
            light: document.getElementById('icon-theme-light').content,
            dark: document.getElementById('icon-theme-dark').content,
        };

        const getStoredTheme = () => localStorage.getItem('theme') || 'auto';
        const setStoredTheme = (theme) => localStorage.setItem('theme', theme);

        const setTheme = (theme) => {
            const themeToApply = 'auto' === theme
                ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                : theme;
            html.setAttribute('data-bs-theme', themeToApply);
        };

        const updateIcon = (theme) => {
            themeIcon.replaceChildren(icons[theme].firstElementChild.cloneNode(true));
        };

        const cycleTheme = () => {
            const currentTheme = getStoredTheme();
            let nextTheme;

            if ('auto' === currentTheme) {
                nextTheme = 'light';
            } else if ('light' === currentTheme) {
                nextTheme = 'dark';
            } else {
                nextTheme = 'auto';
            }

            setStoredTheme(nextTheme);
            setTheme(nextTheme);
            updateIcon(nextTheme);
        };

        const storedTheme = getStoredTheme();
        updateIcon(storedTheme);

        themeToggle.addEventListener('click', cycleTheme);

        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
            if ('auto' === getStoredTheme()) {
                setTheme('auto');
            }
        });
    }
}
