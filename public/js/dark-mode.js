(function() {
    const key = 'ebaemy_dark_mode';
    const html = document.documentElement;

    // Apply saved preference
    if (localStorage.getItem(key) === 'true') {
        html.classList.add('dark');
    }

    // Toggle function available globally
    window.toggleDarkMode = function() {
        html.classList.toggle('dark');
        localStorage.setItem(key, html.classList.contains('dark'));
    };
})();
