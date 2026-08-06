document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.querySelector('.nav-toggle');
  const nav = document.querySelector('.main-nav');
  if (toggle && nav) toggle.addEventListener('click', () => nav.classList.toggle('is-open'));
  document.querySelectorAll('[data-confirm]').forEach(el => el.addEventListener('click', event => {
    if (!confirm(el.dataset.confirm)) event.preventDefault();
  }));
  document.querySelectorAll('[data-auto-submit]').forEach(el => el.addEventListener('change', () => el.form.submit()));
});
