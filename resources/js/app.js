import './glightbox';

// Keep the theme control in sync and persist each manual light/dark selection.
const themeToggle = document.querySelector('[data-theme-toggle]');

if (themeToggle) {
    const themeLabel = themeToggle.querySelector('[data-theme-label]');

    const updateThemeControl = () => {
        const isDark = document.documentElement.classList.contains('dark');
        themeToggle.setAttribute('aria-pressed', String(isDark));
        themeToggle.setAttribute('aria-label', isDark ? 'Light mode' : 'Dark mode');
        themeLabel.textContent = isDark ? 'Light mode' : 'Dark mode';
    };

    updateThemeControl();

    themeToggle.addEventListener('click', () => {
        const isDark = document.documentElement.classList.toggle('dark');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        updateThemeControl();
    });
}
