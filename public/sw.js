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
