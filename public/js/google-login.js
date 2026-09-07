(function () {
    'use strict';

    let attempts = 0;

    function initGoogleLogin() {
        const button = document.getElementById('google-signin-button');
        const form = document.getElementById('google-login-form');
        const credentialInput = document.getElementById('google-credential');
        if (!button || !form || !credentialInput) return;
        if (!window.google || !google.accounts || !google.accounts.id) {
            attempts += 1;
            if (attempts < 50) window.setTimeout(initGoogleLogin, 100);
            return;
        }

        const clientId = button.dataset.googleClientId || '';
        if (!clientId) return;

        google.accounts.id.initialize({
            client_id: clientId,
            callback: function (response) {
                if (!response || !response.credential) return;
                credentialInput.value = response.credential;
                form.submit();
            },
            auto_select: false,
            cancel_on_tap_outside: true
        });

        google.accounts.id.renderButton(button, {
            type: 'standard',
            theme: 'outline',
            size: 'large',
            text: 'continue_with',
            shape: 'rectangular',
            logo_alignment: 'left',
            width: 320
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initGoogleLogin);
    else initGoogleLogin();
})();
