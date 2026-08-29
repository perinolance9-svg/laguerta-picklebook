(() => {
    const intervalMilliseconds = 5000;
    let formIsDirty = false;
    let submitting = false;

    document.addEventListener('input', () => { formIsDirty = true; }, true);
    document.addEventListener('change', () => { formIsDirty = true; }, true);
    document.addEventListener('submit', () => { submitting = true; }, true);

    window.setInterval(() => {
        const editing = document.querySelector('input:focus, select:focus, textarea:focus');
        if (document.visibilityState === 'visible' && !formIsDirty && !submitting && !editing) {
            window.location.reload();
        }
    }, intervalMilliseconds);
})();
