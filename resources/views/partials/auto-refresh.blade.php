@php
    $refreshInterval = (int) ($interval ?? 15000);
@endphp

<script>
    (() => {
        if (window.QA365AutoRefreshEnabled) {
            return;
        }

        window.QA365AutoRefreshEnabled = true;

        const refresh = () => {
            if (document.visibilityState === 'visible') {
                window.location.reload();
                return;
            }

            window.QA365AutoRefreshPending = true;
        };

        window.setTimeout(refresh, {{ $refreshInterval }});

        document.addEventListener('visibilitychange', () => {
            if (window.QA365AutoRefreshPending && document.visibilityState === 'visible') {
                window.location.reload();
            }
        });
    })();
</script>
