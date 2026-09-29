<script data-navigate-once>
    document.addEventListener('livewire:navigate', (event) => {
        if (!event.detail.history || !event.detail.cached) return;

        const panelPath = @js($panelPath);
        const destination = event.detail.url;

        if (destination.origin !== window.location.origin) return;
        if (destination.pathname !== panelPath && !destination.pathname.startsWith(`${panelPath}/`)) return;

        event.preventDefault();
        window.location.replace(destination.href);
    });
</script>
