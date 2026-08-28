@once
    <script>
        (function () {
            const vapidPublicKey = @js(config('webpush.vapid.public_key'));

            function urlBase64ToUint8Array(base64String) {
                const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
                const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
                const rawData = window.atob(base64);

                return Uint8Array.from([...rawData].map((char) => char.charCodeAt(0)));
            }

            function csrfToken() {
                return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            }

            async function subscribeToPush() {
                if (!('serviceWorker' in navigator) || !('PushManager' in window) || !vapidPublicKey) {
                    return;
                }

                const registration = await navigator.serviceWorker.register('/sw.js');

                if (Notification.permission === 'denied') {
                    return;
                }

                const permission = await Notification.requestPermission();

                if (permission !== 'granted') {
                    return;
                }

                let subscription = await registration.pushManager.getSubscription();

                if (!subscription) {
                    subscription = await registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
                    });
                }

                await fetch('/push-subscriptions', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify(subscription.toJSON()),
                });
            }

            if (document.readyState === 'complete') {
                subscribeToPush();
            } else {
                window.addEventListener('load', subscribeToPush);
            }
        })();
    </script>
@endonce
