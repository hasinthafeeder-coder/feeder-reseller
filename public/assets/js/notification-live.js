(function () {
    'use strict';

    var configNode = document.getElementById('notificationLiveConfig');
    if (!configNode) {
        return;
    }

    var endpoint = configNode.getAttribute('data-endpoint');
    var intervalMs = parseInt(configNode.getAttribute('data-interval-ms') || '15000', 10);
    var includeBanners = configNode.getAttribute('data-include-banners') === '1';
    if (!endpoint || !intervalMs || intervalMs < 5000) {
        return;
    }

    var lastVersion = null;
    var timer = null;
    var inFlight = false;

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function formatCount(count) {
        return count > 99 ? '99+' : String(count);
    }

    function setBadge(el, count) {
        if (!el) {
            return;
        }
        if (count > 0) {
            el.textContent = formatCount(count);
            el.hidden = false;
            el.style.display = '';
        } else {
            el.textContent = '';
            el.hidden = true;
            el.style.display = 'none';
        }
    }

    function ensureSidebarBadge(key) {
        var link = document.querySelector('[data-notification-live-menu="' + key + '"]');
        if (!link) {
            return null;
        }
        var badge = link.querySelector('[data-notification-live-badge="' + key + '"]');
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'count';
            badge.setAttribute('data-notification-live-badge', key);
            link.appendChild(badge);
        }
        return badge;
    }

    function updateCounts(payload) {
        var unread = parseInt(payload.unread_count || 0, 10);
        var meetingUnread = parseInt(payload.unread_meeting_count || 0, 10);

        if (isNaN(unread)) {
            unread = 0;
        }
        if (isNaN(meetingUnread)) {
            meetingUnread = 0;
        }

        setBadge(document.getElementById('headerNotificationUnreadBadge'), unread);

        var label = document.getElementById('headerNotificationUnreadLabel');
        if (label) {
            label.textContent = '(' + unread + ')';
        }

        var inboxLabel = document.getElementById('notificationInboxUnreadLabel');
        if (inboxLabel) {
            inboxLabel.textContent = unread > 0 ? unread + ' unread' : '';
        }

        setBadge(ensureSidebarBadge('notifications'), unread);
        setBadge(ensureSidebarBadge('meetings'), meetingUnread);
    }

    function renderDropdown(items) {
        var list = document.getElementById('headerNotificationRecentList');
        if (!list) {
            return;
        }

        if (!items || !items.length) {
            list.innerHTML = '<div class="p-3 text-body fs-14">No notifications yet.</div>';
            return;
        }

        var html = items.map(function (item) {
            var unreadClass = item.is_unread ? 'unseen' : '';
            var icon = item.is_unread ? 'mark_email_unread' : 'mark_email_read';
            var iconClass = item.is_unread ? 'text-primary' : 'text-secondary';
            var media = item.banner_url
                ? '<img src="' + item.banner_url + '" alt="" class="rounded-2" style="width: 40px; height: 40px; object-fit: cover;">'
                : '<i class="material-symbols-outlined ' + iconClass + '">' + icon + '</i>';

            return (
                '<div class="notification-menu ' + unreadClass + '" data-recipient-uuid="' + item.recipient_uuid + '">' +
                    '<a href="' + (item.show_url || '#') + '" class="dropdown-item">' +
                        '<div class="d-flex align-items-center">' +
                            '<div class="flex-shrink-0">' + media + '</div>' +
                            '<div class="flex-grow-1 ms-3">' +
                                '<p class="fs-16 fw-medium text-secondary mb-0">' + escapeHtml(item.title) + '</p>' +
                                '<span class="fs-13 text-body d-block">' + escapeHtml(item.body_preview || '') + '</span>' +
                                '<span class="fs-13 text-body d-block">From ' + escapeHtml(item.sender_label || 'Admin') + '</span>' +
                                '<span class="fs-14 fw-medium">' + escapeHtml(item.publish_at_human || '') + '</span>' +
                            '</div>' +
                        '</div>' +
                    '</a>' +
                '</div>'
            );
        }).join('');

        list.innerHTML = html;
    }

    function upsertInbox(items) {
        var list = document.getElementById('notificationInboxList');
        if (!list || !items || !items.length) {
            return;
        }

        var empty = list.querySelector('[data-notification-empty]');
        items.slice().reverse().forEach(function (item) {
            if (!item.recipient_uuid || list.querySelector('[data-recipient-uuid="' + item.recipient_uuid + '"]')) {
                return;
            }
            if (empty) {
                empty.remove();
            }
            var wrapper = document.createElement('div');
            wrapper.className = 'col-12';
            wrapper.setAttribute('data-recipient-uuid', item.recipient_uuid);
            wrapper.innerHTML = inboxCardHtml(item);
            list.insertBefore(wrapper, list.firstChild);
        });
    }

    function inboxCardHtml(item) {
        var unread = !!item.is_unread;
        var border = unread ? 'border-primary' : 'border-white';
        var titleClass = unread ? 'fw-semibold text-secondary' : 'fw-medium text-body';
        var badge = unread
            ? '<span class="text-primary bg-primary bg-opacity-10 fs-13 fw-normal d-inline-block default-badge">Unread</span>'
            : '<span class="text-secondary bg-secondary bg-opacity-10 fs-13 fw-normal d-inline-block default-badge">Read</span>';
        var media = item.banner_url
            ? '<img src="' + item.banner_url + '" alt="" class="rounded-10 border flex-shrink-0" style="width: 96px; height: 64px; object-fit: cover;">'
            : '<div class="rounded-10 border flex-shrink-0 d-flex align-items-center justify-content-center bg-body-tertiary" style="width: 96px; height: 64px;"><i class="material-symbols-outlined ' + (unread ? 'text-primary' : 'text-secondary') + '">' + (unread ? 'mark_email_unread' : 'mark_email_read') + '</i></div>';

        return (
            '<a href="' + (item.show_url || '#') + '" class="card bg-white border ' + border + ' rounded-10 text-decoration-none d-block p-20 h-100">' +
                '<div class="d-flex gap-3 align-items-start">' + media +
                    '<div class="flex-grow-1 min-w-0">' +
                        '<div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">' +
                            '<h4 class="fs-16 mb-1 ' + titleClass + '">' + escapeHtml(item.title) + '</h4>' + badge +
                        '</div>' +
                        '<p class="fs-14 text-body mb-2">' + escapeHtml(item.body_preview || '') + '</p>' +
                        '<div class="fs-13 text-secondary">From ' + escapeHtml(item.sender_label || 'Admin') + ' · ' + escapeHtml(item.type_label || 'Notice') + ' · ' + escapeHtml(item.publish_at_label || '') + '</div>' +
                    '</div>' +
                '</div>' +
            '</a>'
        );
    }

    function renderBanners(banners) {
        var mount = document.getElementById('resellerDashboardBannerMount');
        if (!mount) {
            return;
        }

        if (!banners || !banners.length) {
            mount.innerHTML = '';
            return;
        }

        var slides = banners.map(function (slide, index) {
            return (
                '<div class="carousel-item' + (index === 0 ? ' active' : '') + '" style="height: 170px;">' +
                    '<a href="' + (slide.url || '#') + '" class="d-block h-100 text-decoration-none" aria-label="Open ' + escapeHtml(slide.title || 'Banner') + '">' +
                        '<img src="' + slide.image_url + '" class="d-block w-100 h-100 rounded-10" style="object-fit: cover;" alt="' + escapeHtml(slide.title || 'Banner') + '" />' +
                    '</a>' +
                '</div>'
            );
        }).join('');

        var controls = banners.length > 1
            ? (
                '<button class="carousel-control-prev" type="button" data-bs-target="#resellerNotificationBannerCarousel" data-bs-slide="prev" style="background: transparent; opacity: 1; width: 72px;">' +
                    '<span class="d-flex align-items-center justify-content-center text-white" aria-hidden="true"><i class="material-symbols-outlined fs-40" style="font-variation-settings: \'FILL\' 1, \'wght\' 700, \'GRAD\' 0, \'opsz\' 48;">chevron_left</i></span>' +
                    '<span class="visually-hidden">Previous</span>' +
                '</button>' +
                '<button class="carousel-control-next" type="button" data-bs-target="#resellerNotificationBannerCarousel" data-bs-slide="next" style="background: transparent; opacity: 1; width: 72px;">' +
                    '<span class="d-flex align-items-center justify-content-center text-white" aria-hidden="true"><i class="material-symbols-outlined fs-40" style="font-variation-settings: \'FILL\' 1, \'wght\' 700, \'GRAD\' 0, \'opsz\' 48;">chevron_right</i></span>' +
                    '<span class="visually-hidden">Next</span>' +
                '</button>'
            )
            : '';

        mount.innerHTML =
            '<div class="row"><div class="col-12">' +
                '<div id="resellerNotificationBannerCarousel" class="carousel slide mb-4 position-relative" data-bs-touch="false" data-bs-ride="carousel">' +
                    '<a href="javascript:void(0);" class="position-absolute top-0 end-0 d-inline-flex align-items-center justify-content-center rounded-circle text-white text-decoration-none" style="width: 30px; height: 30px; background-color: #EF4923; margin: 12px; z-index: 3;" aria-label="Close banner" onclick="document.getElementById(\'resellerNotificationBannerCarousel\')?.remove();">' +
                        '<i class="material-symbols-outlined fs-24" style="font-variation-settings: \'FILL\' 1, \'wght\' 700, \'GRAD\' 0, \'opsz\' 24;">close</i>' +
                    '</a>' +
                    '<div class="carousel-inner rounded-10">' + slides + '</div>' +
                    controls +
                '</div>' +
            '</div></div>';
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function applyPayload(payload, options) {
        options = options || {};
        if (!payload) {
            return;
        }

        // Counts always refresh from the authenticated live snapshot.
        updateCounts(payload);

        if (!options.force && payload.version === lastVersion) {
            return;
        }

        lastVersion = payload.version;
        renderDropdown(payload.items || []);
        upsertInbox(payload.items || []);
        if (includeBanners) {
            renderBanners(payload.banners || []);
        }
    }

    function poll(options) {
        options = options || {};
        if (inFlight || (document.hidden && !options.force)) {
            return;
        }
        inFlight = true;

        var separator = endpoint.indexOf('?') >= 0 ? '&' : '?';
        var url = endpoint + separator + '_=' + Date.now();
        if (includeBanners) {
            url += '&include_banners=1';
        }

        fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken()
            }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('poll failed');
            }
            return response.json();
        }).then(function (payload) {
            applyPayload(payload, options);
        }).catch(function () {
            // Keep polling; transient failures should not clear existing UI.
        }).finally(function () {
            inFlight = false;
        });
    }

    function start() {
        stop();
        poll({ force: true });
        timer = window.setInterval(function () {
            poll();
        }, intervalMs);
    }

    function stop() {
        if (timer) {
            window.clearInterval(timer);
            timer = null;
        }
    }

    function refresh() {
        poll({ force: true });
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            stop();
        } else {
            start();
        }
    });

    window.addEventListener('pageshow', function () {
        refresh();
    });

    document.addEventListener('feeder:notifications-changed', function () {
        refresh();
    });

    window.FeederNotificationLive = {
        refresh: refresh,
        poll: poll
    };

    start();
})();
