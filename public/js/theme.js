(function () {
    const root = document.documentElement;
    const storageKey = 'phpquest-theme';

    function getCurrentTheme() {
        return root.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
    }

    function updateButtons(theme) {
        document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
            const icon = button.querySelector('[data-theme-icon]');
            const label = button.querySelector('[data-theme-label]');
            const isDark = theme === 'dark';

            button.setAttribute('aria-label', isDark ? 'Ativar tema claro' : 'Ativar tema escuro');
            button.setAttribute('title', isDark ? 'Mudar para tema claro' : 'Mudar para tema escuro');

            if (icon) icon.textContent = isDark ? '☀' : '☾';
            if (label) label.textContent = isDark ? 'Claro' : 'Escuro';
        });
    }

    function setTheme(theme) {
        root.setAttribute('data-theme', theme);
        localStorage.setItem(storageKey, theme);
        updateButtons(theme);
    }

    document.addEventListener('DOMContentLoaded', function () {
        updateButtons(getCurrentTheme());

        document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
            button.addEventListener('click', function () {
                setTheme(getCurrentTheme() === 'dark' ? 'light' : 'dark');
            });
        });
    });
})();
