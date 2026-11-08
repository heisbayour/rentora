/**
 * Disables every submit button of a form while it is submitting (spec 6.4),
 * so a double click cannot post twice. Forms opt out with data-no-guard.
 */
export function guardFormSubmissions() {
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-no-guard')) {
            return;
        }
        if (form.dataset.submitting === '1') {
            event.preventDefault();
            return;
        }
        form.dataset.submitting = '1';
        form.querySelectorAll('button[type="submit"], button:not([type])').forEach((button) => {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.dataset.loading = '1';
        });
    });

    // Restore buttons if the page is shown again from the back/forward cache.
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('form[data-submitting="1"]').forEach((form) => {
            delete form.dataset.submitting;
            form.querySelectorAll('[data-loading="1"]').forEach((button) => {
                button.disabled = false;
                button.removeAttribute('aria-busy');
                delete button.dataset.loading;
            });
        });
    });
}
