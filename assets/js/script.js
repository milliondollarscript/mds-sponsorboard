(function () {
    'use strict';

    var root = window.MillionDollarScript = window.MillionDollarScript || {};
    root.extensions = root.extensions || {};
    var extension = root.extensions.sponsorBoard = root.extensions.sponsorBoard || {};
    var config = extension.config || {};

    function sendEvent(element, eventType) {
        if (!config.ajaxUrl || !config.nonce || !element) {
            return;
        }

        var bookingId = element.getAttribute('data-mds-sponsorboard-booking');
        if (!bookingId) {
            return;
        }

        var data = new FormData();
        data.append('action', 'mds_sponsorboard_track');
        data.append('nonce', config.nonce);
        data.append('event_type', eventType);
        data.append('booking_id', bookingId);
        data.append('slot_id', element.getAttribute('data-mds-sponsorboard-slot') || '');
        data.append('board_id', element.getAttribute('data-mds-sponsorboard-board') || '');
        data.append('path', window.location.pathname || '');

        if ('click' === eventType && navigator.sendBeacon) {
            navigator.sendBeacon(config.ajaxUrl, data);
            return;
        }

        window.fetch(config.ajaxUrl, {
            body: data,
            credentials: 'same-origin',
            method: 'POST'
        }).catch(function () {});
    }

    function closestSlot(target) {
        return target && target.closest ? target.closest('[data-mds-sponsorboard-booking]') : null;
    }

    function ready() {
        var slots = Array.prototype.slice.call(document.querySelectorAll('[data-mds-sponsorboard-booking]'));
        var seen = {};

        function trackImpression(slot) {
            var bookingId = slot.getAttribute('data-mds-sponsorboard-booking');
            if (!bookingId || seen[bookingId]) {
                return;
            }
            seen[bookingId] = true;
            sendEvent(slot, 'impression');
        }

        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        trackImpression(entry.target);
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.35
            });
            slots.forEach(function (slot) {
                observer.observe(slot);
            });
        } else {
            slots.forEach(trackImpression);
        }

        document.addEventListener('click', function (event) {
            var link = event.target.closest ? event.target.closest('[data-mds-sponsorboard-track="click"]') : null;
            if (link) {
                sendEvent(closestSlot(link), 'click');
            }
        });
    }

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', ready);
    } else {
        ready();
    }
})();
