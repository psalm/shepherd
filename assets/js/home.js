/*
 * Home page: tabs for the leaderboards (the server renders all four panels, this only switches between them)
 * and the live refresh of "Recently active" while that tab is selected.
 */
(function () {
    'use strict';

    var tabs = Array.prototype.slice.call(document.querySelectorAll('.tablist [role="tab"]'));

    if (!tabs.length) {
        return;
    }

    var panels = {};
    var selected = tabs[0];

    tabs.forEach(function (tab) {
        var panel = document.getElementById(tab.getAttribute('aria-controls'));

        panels[tab.id] = panel;
        panel.setAttribute('role', 'tabpanel');
        panel.setAttribute('aria-labelledby', tab.id);
    });

    function select(tab, focus) {
        selected = tab;
        tabs.forEach(function (other) {
            var on = other === tab;

            other.setAttribute('aria-selected', String(on));
            other.tabIndex = on ? 0 : -1;
            panels[other.id].classList.toggle('is-active', on);
        });

        if (focus) {
            tab.focus();
        }

        live.sync();
    }

    tabs.forEach(function (tab, index) {
        tab.addEventListener('click', function () { select(tab, false); });
        tab.addEventListener('keydown', function (event) {
            var target = null;

            if (event.key === 'ArrowRight') {
                target = tabs[(index + 1) % tabs.length];
            } else if (event.key === 'ArrowLeft') {
                target = tabs[(index - 1 + tabs.length) % tabs.length];
            } else if (event.key === 'Home') {
                target = tabs[0];
            } else if (event.key === 'End') {
                target = tabs[tabs.length - 1];
            }

            if (target) {
                event.preventDefault();
                select(target, true);
            }
        });
    });

    /*
     * Live "Recently active": polls the fragment endpoint every 30 s with If-None-Match and swaps the <tbody> on a
     * 200. It runs only while the tab is selected, the page is visible and the user was active in the last 60 s.
     * Errors keep the old rows and back the polling off; nothing is shown about them.
     */
    var live = (function () {
        var POLL_MS = 30000;
        var IDLE_MS = 60000;
        var MAX_BACKOFF_MS = 300000;
        var HIGHLIGHT_MS = 2200;

        var panel = document.getElementById('panel-recent');
        var src = panel ? panel.dataset.liveSrc : null;

        if (!src || !window.fetch || !window.AbortController) {
            return { sync: function () {} };
        }

        var etag = null;
        var failures = 0;
        var idle = false;
        var timer = 0;
        var idleTimer = 0;
        var lastReset = 0;
        var lastPoll = 0;
        var controller = null;

        function active() {
            return selected.id === 'tab-recent' && !document.hidden && !idle;
        }

        function delay() {
            var interval = Math.min(POLL_MS * Math.pow(2, failures), MAX_BACKOFF_MS);

            return Math.max(0, lastPoll + interval - Date.now());
        }

        // Starts, keeps or stops the poll loop to match active(); called on every state change.
        function sync() {
            if (!active()) {
                window.clearTimeout(timer);
                timer = 0;

                if (controller) {
                    controller.abort();
                }

                return;
            }

            if (!timer && !controller) {
                timer = window.setTimeout(poll, delay());
            }
        }

        function rowsBySha(tbody) {
            var map = {};

            tbody.querySelectorAll('tr[data-repo]').forEach(function (row) {
                map[row.dataset.repo] = row.dataset.sha;
            });

            return map;
        }

        function swap(html) {
            var template = document.createElement('template');
            var current = panel.querySelector('tbody');

            template.innerHTML = html;

            var next = template.content.querySelector('tbody');

            if (!current || !next) {
                return false;
            }

            // Never pull the row away from someone who is reading or has a link focused in it.
            var selection = window.getSelection();

            if (current.contains(document.activeElement)
                || (selection && !selection.isCollapsed && current.contains(selection.anchorNode))) {
                return null;
            }

            var before = rowsBySha(current);

            next.querySelectorAll('tr[data-repo]').forEach(function (row) {
                if (before[row.dataset.repo] !== row.dataset.sha) {
                    row.classList.add('is-fresh');
                }
            });

            current.replaceWith(next);

            window.setTimeout(function () {
                next.querySelectorAll('.is-fresh').forEach(function (row) { row.classList.remove('is-fresh'); });
            }, HIGHLIGHT_MS);

            return true;
        }

        function poll() {
            timer = 0;

            if (!active()) {
                return;
            }

            lastPoll = Date.now();
            controller = new AbortController();

            var headers = etag ? { 'If-None-Match': etag } : {};

            window.fetch(src, { headers: headers, credentials: 'same-origin', signal: controller.signal })
                .then(function (response) {
                    if (response.status === 304) {
                        return true;
                    }

                    if (!response.ok) {
                        return false;
                    }

                    var validator = response.headers.get('ETag');

                    return response.text().then(function (html) {
                        var done = swap(html);

                        // Skipped while the user is busy in the table: the same body is fetched again next time.
                        if (done === true) {
                            etag = validator;
                        }

                        return done !== false;
                    });
                })
                // An abort (the tab was left) is not a failure: null keeps the back-off as it is.
                .catch(function (error) { return error && error.name === 'AbortError' ? null : false; })
                .then(function (ok) {
                    controller = null;
                    failures = ok === null ? failures : (ok ? 0 : failures + 1);
                    sync();
                });
        }

        function setIdle(value) {
            if (idle !== value) {
                idle = value;
                sync();
            }
        }

        // Any input resets the 60 s idle clock; it is touched at most once a second.
        function activity() {
            var now = Date.now();

            if (idle || now - lastReset > 1000) {
                lastReset = now;
                window.clearTimeout(idleTimer);
                idleTimer = window.setTimeout(function () { setIdle(true); }, IDLE_MS);
                setIdle(false);
            }
        }

        ['pointermove', 'pointerdown', 'keydown', 'scroll', 'wheel', 'touchstart'].forEach(function (name) {
            document.addEventListener(name, activity, { passive: true, capture: true });
        });
        document.addEventListener('visibilitychange', sync);
        lastReset = Date.now();
        idleTimer = window.setTimeout(function () { setIdle(true); }, IDLE_MS);
        // The first poll comes one interval after load: the page itself is at most a minute old.
        lastPoll = Date.now();

        return { sync: sync };
    }());

    live.sync();
}());
