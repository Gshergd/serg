document.querySelectorAll('[data-copy]').forEach(button => {
  button.addEventListener('click', async () => {
    const original = button.textContent;
    try {
      await navigator.clipboard.writeText(button.dataset.copy);
      button.textContent = 'Copied ✓';
      document.getElementById('copy-status').textContent = 'Short link copied.';
      setTimeout(() => { button.textContent = original; }, 1800);
    } catch {
      window.prompt('Copy your short link:', button.dataset.copy);
    }
  });
});
