@if (auth()->check() && config('screen-lock.idle_minutes') > 0)
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const idleMs = {{ (int) config('screen-lock.idle_minutes') }} * 60 * 1000;
            const lockUrl = @json(route('screen-lock.store'));
            const redirectUrl = @json(route('screen-lock.show'));
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            let timer = null;

            const lockScreen = () => {
                fetch(lockUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: '{}',
                }).finally(() => {
                    window.location.assign(redirectUrl);
                });
            };

            const resetIdleTimer = () => {
                window.clearTimeout(timer);
                timer = window.setTimeout(lockScreen, idleMs);
            };

            ['click', 'keydown', 'mousemove', 'scroll', 'touchstart'].forEach((eventName) => {
                document.addEventListener(eventName, resetIdleTimer, { passive: true });
            });

            resetIdleTimer();
        });
    </script>
@endif
