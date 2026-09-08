/*
 * Livewire's default handling of a failed request is a native browser dialog
 * (419) or a full-page overlay framing the error response (5xx). Native
 * dialogs are banned in AtendIa, so we take over: a 419 lands on the login,
 * and a 5xx offers our retry dialog. The body's data-* attributes carry the
 * config, since JS can resolve neither routes nor translations.
 */
document.addEventListener('livewire:init', () => {
    window.Livewire.hook('request', ({ fail }) => {
        fail(({ status, preventDefault }) => {
            if (status === 419) {
                preventDefault();
                window.location.assign(document.body.dataset.loginUrl ?? '/login');

                return;
            }

            // With debug on the overlay shows the stack trace — that is the
            // useful thing to see while developing, so we leave it alone.
            if (status >= 500 && 'gracefulFailures' in document.body.dataset) {
                preventDefault();
                offerRetry();
            }
        });
    });
});

async function offerRetry() {
    const { failTitle, failMessage } = document.body.dataset;

    if (await window.dialog?.retry({ title: failTitle, message: failMessage })) {
        window.location.reload();
    }
}
