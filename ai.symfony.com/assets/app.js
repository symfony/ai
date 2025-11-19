import './stimulus_bootstrap.js';
import 'bootstrap';
import 'bootstrap/dist/css/bootstrap.min.css';
import './styles/app.css';

document.addEventListener('DOMContentLoaded', function() {
    new App();
});

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
