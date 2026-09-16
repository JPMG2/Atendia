/*
 * Live security notices for the session on screen: when a new device joins
 * the account, the broadcast lands here and comes out as a toast — the mail
 * alerts the inbox, this alerts the eyes already on the app.
 */
const userId = document.querySelector('meta[name="auth-user-id"]')?.content;

if (userId && window.Echo) {
    window.Echo.private(`security.user.${userId}`).listen('.device.added', (event) => {
        window.dispatchEvent(
            new CustomEvent('notify', { detail: { type: 'warning', message: event.message, action: event.action } }),
        );
    });
}
