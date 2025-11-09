function startNotificationPolling() {
    // Poll every 30 seconds
    const POLL_INTERVAL = 30000;
    
    function checkNotifications() {
        fetch('../config/get-notifications.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.notifications.length > 0) {
                    updateNotificationBadge(data.notifications.length);
                    showNotificationPopup(data.notifications[0]);
                }
            })
            .catch(error => console.error('Error checking notifications:', error));
    }

    function updateNotificationBadge(count) {
        const badge = document.getElementById('notificationBadge');
        if (badge) {
            badge.textContent = count;
            badge.classList.remove('hidden');
        }
    }

    function showNotificationPopup(notification) {
        // Only show if the browser supports notifications and permission is granted
        if (!("Notification" in window)) {
            return;
        }

        // Check if permission is already granted
        if (Notification.permission === "granted") {
            createNotification(notification);
        }
        // Request permission if not already requested
        else if (Notification.permission !== "denied") {
            Notification.requestPermission().then(function (permission) {
                if (permission === "granted") {
                    createNotification(notification);
                }
            });
        }
    }

    function createNotification(notification) {
        const notif = new Notification("Medicare App", {
            body: notification.message,
            icon: "/public/images/logo.png",
            tag: notification.id
        });

        notif.onclick = function() {
            window.focus();
            if (notification.type === 'message') {
                window.location.href = '/messages.php';
            } else if (notification.type === 'appointment') {
                window.location.href = '/appointments.php';
            }
        };
    }

    // Start polling
    setInterval(checkNotifications, POLL_INTERVAL);
    
    // Initial check
    checkNotifications();
}