{{--
    Show/hide behavior for every [data-password-toggle] button on the page.
    Uses one delegated listener, so it works no matter how many password
    fields the page renders. Include once, just before </body>.
--}}
<script>
    document.addEventListener('click', function (event) {
        const toggle = event.target.closest('[data-password-toggle]');

        if (! toggle) {
            return;
        }

        const input = document.getElementById(toggle.dataset.passwordToggle);

        if (! input) {
            return;
        }

        const revealed = input.type === 'password';

        input.type = revealed ? 'text' : 'password';

        toggle.setAttribute('aria-pressed', revealed ? 'true' : 'false');
        toggle.setAttribute('aria-label', revealed ? 'Hide password' : 'Show password');

        toggle.querySelector('[data-icon-show]').classList.toggle('hidden', revealed);
        toggle.querySelector('[data-icon-hide]').classList.toggle('hidden', ! revealed);
    });
</script>
