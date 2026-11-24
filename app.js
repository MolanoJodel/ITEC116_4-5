'use strict';
document.documentElement.classList.add('js-enabled');
// Browser-side validation and UI enhancements. PHP remains the security authority.
document.querySelectorAll('[data-toggle-password]').forEach(button => {
    button.addEventListener('click', () => {
        const field = document.getElementById(button.dataset.togglePassword);
        const show = field.type === 'password';
        field.type = show ? 'text' : 'password';
        button.textContent = show ? 'Hide' : 'Show';
        button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        button.setAttribute('aria-pressed', String(show));
    });
});
const menu = document.querySelector('.menu-toggle');
const sidebar = document.getElementById('sidebar');
function setMenu(open) {
    if (!menu || !sidebar) return;
    sidebar.classList.toggle('open', open);
    menu.setAttribute('aria-expanded', String(open));
}
if (menu && sidebar) {
    menu.addEventListener('click', () => setMenu(!sidebar.classList.contains('open')));
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && sidebar.classList.contains('open')) { setMenu(false); menu.focus(); } });
    document.addEventListener('click', event => { if (sidebar.classList.contains('open') && !sidebar.contains(event.target) && !menu.contains(event.target)) setMenu(false); });
}
document.querySelectorAll('[data-search-table]').forEach(input => {
    const table = document.getElementById(input.dataset.searchTable);
    const rows = Array.from(table.querySelectorAll('[data-search-row]'));
    input.addEventListener('input', () => {
        const term = input.value.toLowerCase().trim();
        let visible = 0;
        rows.forEach(row => { row.hidden = !row.textContent.toLowerCase().includes(term); if (!row.hidden) visible++; });
        const status = document.getElementById('searchStatus');
        if (status) status.textContent = `${visible} matching student(s)`;
    });
});
// Client-side password confirmation is a usability check, repeated by PHP.
document.querySelectorAll('[data-password-form]').forEach(form => {
    const password = form.elements.new_password;
    const confirm = form.elements.confirm_password;
    function validate() { confirm.setCustomValidity(confirm.value && confirm.value !== password.value ? 'Passwords do not match.' : ''); }
    password.addEventListener('input', validate);
    confirm.addEventListener('input', validate);
});
document.querySelectorAll('form[data-confirm], form[data-submit-lock]').forEach(form => {
    form.addEventListener('submit', event => {
        if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) { event.preventDefault(); return; }
        if (form.hasAttribute('data-submit-lock')) {
            if (form.dataset.submitting === 'true') { event.preventDefault(); return; }
            form.dataset.submitting = 'true';
            const submit = form.querySelector('button[type="submit"]');
            if (submit) { submit.disabled = true; submit.setAttribute('aria-busy', 'true'); }
        }
    });
});
// Restore buttons when returning through the browser's back/forward cache.
window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-submit-lock]').forEach(form => {
        delete form.dataset.submitting;
        const button = form.querySelector('button[type="submit"]');
        if (button) { button.disabled = false; button.removeAttribute('aria-busy'); }
    });
});
