// Same-origin script (allowed by CSP script-src 'self'): confirm prompts for forms marked data-confirm.
document.addEventListener('submit', function (event) {
    var message = event.target.getAttribute('data-confirm');
    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});
