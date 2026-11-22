// Take over immediately on deploy — without this, a currently-active
// service worker keeps running until every tab for this origin is fully
// closed and reopened, so updates to this file (e.g. the acknowledge logic
// below) silently never take effect for already-subscribed devices.
self.addEventListener("install", function (event) {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener("activate", function (event) {
    event.waitUntil(self.clients.claim());
});

// Page navigations go straight to the network (nothing is cached). When the
// device is offline, an installed app shows a friendly Bengali notice
// instead of the browser's error page. Having a real fetch handler also
// keeps the student/teacher apps installable on every Chromium version.
self.addEventListener("fetch", function (event) {
    const request = event.request;

    if (request.method !== "GET" || request.mode !== "navigate") {
        return;
    }

    event.respondWith(
        fetch(request).catch(function () {
            return new Response(offlinePage(), {
                status: 503,
                headers: { "Content-Type": "text/html; charset=utf-8" },
            });
        }),
    );
});

function offlinePage() {
    return (
        '<!doctype html><html lang="bn"><head><meta charset="utf-8">' +
        '<meta name="viewport" content="width=device-width, initial-scale=1">' +
        "<title>ইন্টারনেট সংযোগ নেই</title>" +
        "<style>body{margin:0;min-height:100vh;display:grid;place-items:center;padding:16px;" +
        "font-family:'Hind Siliguri','Nirmala UI',system-ui,sans-serif;background:#fbfaff;color:#1e1b4b;text-align:center}" +
        "@media (prefers-color-scheme:dark){body{background:#0b0d24;color:#e2e8f0}}" +
        "h1{font-size:1.25rem;margin:.6rem 0 .3rem}p{font-size:.85rem;opacity:.75;margin:0 0 1.2rem}" +
        "button{font:inherit;font-weight:600;border:0;border-radius:999px;padding:.6rem 1.4rem;color:#fff;" +
        "background:linear-gradient(135deg,#4f46e5,#7c3aed 50%,#db2777);cursor:pointer}</style></head>" +
        '<body><main><div style="font-size:2.5rem">📡</div><h1>ইন্টারনেট সংযোগ নেই</h1>' +
        "<p>সংযোগ ফিরে এলে আবার চেষ্টা করুন।</p>" +
        '<button onclick="location.reload()">আবার চেষ্টা করুন</button></main></body></html>'
    );
}

self.addEventListener("push", function (event) {
    if (!event.data) {
        return;
    }

    const payload = event.data.json();
    const title = payload.title || "Notification";
    const options = { ...payload };
    delete options.title;

    event.waitUntil(
        Promise.all([
            acknowledgeReceived(payload.data && payload.data.delivery_token),
            self.registration.showNotification(title, options),
        ]),
    );
});

self.addEventListener("notificationclick", function (event) {
    event.notification.close();

    const url = (event.notification.data && event.notification.data.url) || "/";

    event.waitUntil(
        clients
            .matchAll({ type: "window", includeUncontrolled: true })
            .then(function (windowClients) {
                for (const client of windowClients) {
                    if (client.url === url && "focus" in client) {
                        return client.focus();
                    }
                }

                if (clients.openWindow) {
                    return clients.openWindow(url);
                }
            }),
    );
});

function acknowledgeReceived(deliveryToken) {
    if (!deliveryToken) {
        return Promise.resolve();
    }

    return fetch("/push-notification-deliveries/acknowledge", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
        },
        body: JSON.stringify({ delivery_token: deliveryToken }),
        credentials: "same-origin",
    }).catch(function () {});
}
