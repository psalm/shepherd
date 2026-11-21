/*
 * Repository history page: one dual-axis uPlot chart (type coverage left, analysed expressions right)
 * and a navigator below it to pick the visible range.
 * Data comes from <repo>/history.json (columnar: t, c, e, s, ascending by time). All dates are UTC.
 *
 * The range is mirrored to the URL hash (#from=...&to=...). The commits table is rendered by the server
 * (?page=N); its pager links carry the current hash so paging keeps the range.
 */
(function () {
    'use strict';

    const card = document.getElementById('chart-card');
    const commits = document.getElementById('commits');

    if (!card || !commits) {
        return;
    }

    const pagerLinks = Array.from(commits.querySelectorAll('.pager a[data-page]'));
    const MONO = '"IBM Plex Mono", ui-monospace, monospace';
    const NAV_HEIGHT = 72;
    const HOUR = 3600;
    const DAY = 86400;
    const DEFAULT_MONTHS = 12;
    const DEBOUNCE_MS = 150;
    const CLICK_RADIUS = 10;
    const HANDLE_GRAB = 14; // px inside the window edge that grab a handle
    const HANDLE_REACH = 8; // px outside the window edge that still grab it

    const el = {
        status: document.getElementById('chart-status'),
        body: document.getElementById('chart-body'),
        hint: document.getElementById('chart-hint'),
        main: document.getElementById('chart-main'),
        nav: document.getElementById('chart-nav'),
        tip: document.getElementById('chart-tip'),
        wrap: document.querySelector('.chart-wrap'),
        label: document.getElementById('range-label'),
        presets: Array.from(card.querySelectorAll('.presets button')),
        reset: card.querySelector('[data-reset]'),
        pills: Array.from(card.querySelectorAll('.pill')),
        head: card.querySelector('.chart-head'),
        legend: card.querySelector('.legend'),
    };

    const commitUrl = card.dataset.commitUrl || '';
    const tablePath = window.location.pathname;

    // ---------- formatting ----------

    const isoDate = function (ts) {
        return new Date(ts * 1000).toISOString().slice(0, 10);
    };
    const isoDateTime = function (ts) {
        return new Date(ts * 1000).toISOString().slice(0, 19).replace('T', ' ');
    };
    const isoZ = function (ts) {
        return new Date(ts * 1000).toISOString().slice(0, 19) + 'Z';
    };
    // The only rounding rule for coverage: 3 decimals at display time (PHP uses sprintf('%.3f')).
    const fmtCov = function (v) {
        return v.toFixed(3) + '%';
    };
    const fmtInt = function (n) {
        return n.toLocaleString('en-US');
    };
    const esc = function (s) {
        return String(s).replace(/[&<>"']/g, function (ch) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
        });
    };

    // Same rules as the server-rendered table: 236189 -> "236.2K", 1000000 -> "1M".
    function fmtCompact(n) {
        if (n < 1000) {
            return String(n);
        }
        const units = ['K', 'M', 'B', 'T'];
        let value = n / 1000;
        let unit = 0;
        while (unit < 3 && Number(value.toFixed(1)) >= 1000) {
            value /= 1000;
            unit++;
        }
        return value.toFixed(1).replace(/\.0$/, '') + units[unit];
    }

    function decimalsFor(step) {
        let d = 0;
        while (d < 6) {
            const scaled = step * Math.pow(10, d);
            if (Math.abs(scaled - Math.round(scaled)) < 1e-6 * Math.max(1, Math.abs(scaled))) {
                break;
            }
            d++;
        }
        return d;
    }

    // ---------- data helpers ----------

    // First index whose value is > x (arr is ascending).
    function upperBound(arr, x) {
        let lo = 0;
        let hi = arr.length;
        while (lo < hi) {
            const mid = (lo + hi) >> 1;
            if (arr[mid] <= x) {
                lo = mid + 1;
            } else {
                hi = mid;
            }
        }
        return lo;
    }

    function addMonths(ts, months) {
        const d = new Date(ts * 1000);
        const day = d.getUTCDate();
        d.setUTCDate(1);
        d.setUTCMonth(d.getUTCMonth() + months);
        d.setUTCDate(Math.min(day, new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth() + 1, 0)).getUTCDate()));
        return d.getTime() / 1000;
    }

    // uPlot needs strictly increasing x. Rows are ordered (created_on, id), so the k-th row of a repeated second
    // is nudged by k milliseconds; the table and tooltips keep showing the real second from D.t.
    function strictlyIncreasing(t) {
        const x = new Array(t.length);
        let run = 0;
        for (let i = 0; i < t.length; i++) {
            run = i > 0 && t[i] === t[i - 1] ? run + 1 : 0;
            x[i] = t[i] + run * 0.001;
        }
        return x;
    }

    // Index of the last point at or before x. Nudged duplicates sit up to a few ms after their second,
    // hence the tolerance: a range ending at second S still includes every row of S.
    function lastIndexAt(x) {
        return upperBound(D.x, x + 0.5) - 1;
    }

    // ---------- state ----------

    let D = null; // { t, c, e, s, x }
    let first = 0;
    let last = 0;
    let minSpan = HOUR;

    const state = {
        min: 0,
        max: 0,
        activeMonths: null, // preset currently matching the range (null: custom range)
        visible: [true, true],
    };

    let main = null;
    let nav = null;
    let theme = null;
    let navUi = null;
    let lastPointerType = 'mouse';
    let deferTimer = 0;
    let interacting = false;
    let pendingRebuild = false;

    // ---------- theme ----------

    // Colours are custom properties using light-dark(), so resolve them through a probe element.
    function readTheme() {
        const probe = document.createElement('span');
        probe.hidden = true;
        card.appendChild(probe);
        const get = function (name) {
            probe.style.color = 'var(--' + name + ')';
            return window.getComputedStyle(probe).color;
        };
        const result = {
            cov: get('cov'),
            expr: get('expr'),
            muted: get('muted'),
            grid: get('grid'),
            bg: get('bg'),
        };
        probe.remove();
        return result;
    }

    // ---------- range ----------

    const isMobile = function () {
        return window.matchMedia('(max-width: 640px)').matches;
    };

    function presetRange(months) {
        return months === 0 ? [first, last] : [Math.max(first, addMonths(last, -months)), last];
    }

    // A preset that would cover the whole history is the same as "All", so it is disabled.
    function presetEnabled(months) {
        return months === 0 || addMonths(last, -months) > first;
    }

    function defaultMonths() {
        return presetEnabled(DEFAULT_MONTHS) ? DEFAULT_MONTHS : 0;
    }

    function near(a, b) {
        return Math.abs(a - b) < 1;
    }

    function setRange(min, max, options) {
        const opts = options || {};
        const requestedMin = min;
        const requestedMax = max;

        min = Math.max(first, Math.min(min, last));
        max = Math.max(first, Math.min(max, last));
        if (max - min < minSpan) {
            const mid = (min + max) / 2;
            min = Math.max(first, Math.min(mid - minSpan / 2, last - minSpan));
            max = min + minSpan;
        }

        state.min = min;
        state.max = max;
        state.activeMonths = opts.months === undefined ? null : opts.months;

        if (main) {
            // A drag that was clamped or enlarged to minSpan must be written back to the chart's own x scale.
            const adjusted = !near(min, requestedMin) || !near(max, requestedMax);
            applyMainScales(opts.source !== 'main' || adjusted);
        }
        syncNav();
        updateLabel();
        updatePresets();

        window.clearTimeout(deferTimer);
        if (opts.immediate) {
            applyDeferred();
        } else {
            deferTimer = window.setTimeout(applyDeferred, DEBOUNCE_MS);
        }
    }

    function applyMainScales(includeX) {
        main.batch(function () {
            if (includeX) {
                main.setScale('x', { min: state.min, max: state.max });
            }
            main.setScale('cov', yBounds('cov', state.min, state.max));
            main.setScale('expr', yBounds('expr', state.min, state.max));
        });
    }

    function applyPreset(months) {
        const range = presetRange(months);
        setRange(range[0], range[1], { months: months, immediate: true, source: 'preset' });
    }

    function applyDeferred() {
        updateAria();
        writeUrl();
        updatePagerLinks();
    }

    function updateLabel() {
        const b = el.label.querySelector('b');
        if (b) {
            b.textContent = isoDate(state.min) + ' \u2013 ' + isoDate(state.max);
        }
    }

    function updatePresets() {
        el.presets.forEach(function (button) {
            const months = Number(button.dataset.months);
            button.disabled = !presetEnabled(months);
            button.setAttribute('aria-pressed', String(state.activeMonths === months));
        });
    }

    // ---------- URL: #from=<UTC>&to=<UTC> ----------

    function currentHash() {
        return '#from=' + isoZ(Math.floor(state.min)) + '&to=' + isoZ(Math.ceil(state.max));
    }

    function writeUrl() {
        const hash = currentHash();
        if (hash !== window.location.hash) {
            window.history.replaceState(null, '', window.location.pathname + window.location.search + hash);
        }
    }

    // Accepts "YYYY-MM-DDTHH:MM:SSZ" (written by this script) and date-only values (older links).
    function parseBound(value, endOfDay) {
        const match = /^(\d{4}-\d{2}-\d{2})(?:T(\d{2}:\d{2}:\d{2})Z)?$/.exec(value || '');
        if (!match) {
            return null;
        }
        const ms = Date.parse(match[1] + 'T' + (match[2] || (endOfDay ? '23:59:59' : '00:00:00')) + 'Z');
        return Number.isNaN(ms) ? null : { sec: ms / 1000, precise: Boolean(match[2]) };
    }

    function parseHash() {
        const params = new URLSearchParams(window.location.hash.replace(/^#/, ''));
        const from = parseBound(params.get('from'), false);
        const to = parseBound(params.get('to'), true);
        if (!from || !to || to.sec < from.sec) {
            return null;
        }
        return { min: from.sec, max: to.sec, precise: from.precise && to.precise };
    }

    // A restored range that equals a preset (to the day, or to the second for precise links) is that preset.
    function matchPreset(min, max, precise) {
        let found = null;
        [1, 3, 6, 12, 0].forEach(function (months) {
            if (!presetEnabled(months)) {
                return;
            }
            const range = presetRange(months);
            const same = precise
                ? Math.abs(Math.floor(range[0]) - min) < 1.5 && Math.abs(Math.ceil(range[1]) - max) < 1.5
                : isoDate(range[0]) === isoDate(min) && isoDate(range[1]) === isoDate(max);
            if (same) {
                found = months;
            }
        });
        return found;
    }

    // ---------- chart options ----------

    // Padded y bounds for the points visible in [xmin, xmax], computed from the data rather than from uPlot's
    // auto range, which cannot see a pending x change and ignores the point a step line carries in from the left.
    function yBounds(kind, xmin, xmax) {
        const values = kind === 'cov' ? D.c : D.e;
        const i0 = Math.max(0, upperBound(D.x, xmin) - 1);
        const i1 = Math.max(i0, lastIndexAt(xmax));
        let lo = Infinity;
        let hi = -Infinity;
        for (let i = i0; i <= i1; i++) {
            const v = values[i];
            if (v < lo) {
                lo = v;
            }
            if (v > hi) {
                hi = v;
            }
        }
        const span = hi - lo;
        const pad = kind === 'cov' ? Math.max(span * 0.15, 0.005) : Math.max(span * 0.15, hi * 0.01, 1);
        return { min: Math.max(0, lo - pad), max: hi + pad };
    }

    function covTicks(u, splits, axisIdx, foundSpace, foundIncr) {
        const dec = decimalsFor(foundIncr);
        const suffix = isMobile() ? '' : '%'; // narrow screens: the legend already says "%", the axis needs the room
        return splits.map(function (v) {
            return v < 0 || v > 100 + 1e-9 ? '' : v.toFixed(dec) + suffix;
        });
    }

    function exprTicks(u, splits, axisIdx, foundSpace, foundIncr) {
        const top = Math.max.apply(null, splits.map(Math.abs));
        const unit = top >= 1e6 ? 1e6 : top >= 1e3 ? 1e3 : 1;
        const suffix = unit === 1e6 ? 'M' : unit === 1e3 ? 'K' : '';
        const dec = decimalsFor(foundIncr / unit);
        return splits.map(function (v) {
            return v < 0 ? '' : (v / unit).toFixed(dec) + suffix;
        });
    }

    // ---------- UTC time axis ----------
    // uPlot's own time ticks depend on tzDate, which shifts by the browser's DST offsets. These splits and labels
    // use only getUTC*/Date.UTC, so axis, tooltip and table agree in any browser timezone.

    const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const UNIT_SECONDS = { s: 1, m: 60, h: 3600, d: DAY, M: 30.4375 * DAY, y: 365.25 * DAY };
    const X_STEPS = [
        ['s', 1], ['s', 5], ['s', 15], ['s', 30],
        ['m', 1], ['m', 5], ['m', 15], ['m', 30],
        ['h', 1], ['h', 3], ['h', 6], ['h', 12],
        ['d', 1], ['d', 2], ['d', 5], ['d', 10],
        ['M', 1], ['M', 2], ['M', 3], ['M', 6],
        ['y', 1], ['y', 2], ['y', 5], ['y', 10], ['y', 20], ['y', 50], ['y', 100],
    ];

    function stepSeconds(step) {
        return step[1] * UNIT_SECONDS[step[0]];
    }

    function xSplits(u, axisIdx, min, max, foundIncr) {
        if (min === null || max === null || !(max > min)) {
            return [];
        }

        // Smallest step that is at least as wide as uPlot's own spacing suggestion, and never more than ~300 ticks.
        let index = 0;
        while (index < X_STEPS.length - 1 && stepSeconds(X_STEPS[index]) < foundIncr * 0.9) {
            index++;
        }
        while (index < X_STEPS.length - 1 && (max - min) / stepSeconds(X_STEPS[index]) > 300) {
            index++;
        }

        const unit = X_STEPS[index][0];
        const n = X_STEPS[index][1];
        const ticks = [];

        if (unit === 'M' || unit === 'y') {
            const d = new Date(min * 1000);
            let slot = unit === 'y' ? d.getUTCFullYear() : d.getUTCFullYear() * 12 + d.getUTCMonth();
            slot = Math.floor(slot / n) * n;
            for (; ; slot += n) {
                const ts = (unit === 'y' ? Date.UTC(slot, 0, 1) : Date.UTC(Math.floor(slot / 12), slot % 12, 1)) / 1000;
                if (ts > max) {
                    break;
                }
                if (ts >= min) {
                    ticks.push(ts);
                }
            }
        } else {
            const size = n * UNIT_SECONDS[unit];
            for (let ts = Math.ceil(min / size) * size; ts <= max; ts += size) {
                ticks.push(ts);
            }
        }

        return ticks;
    }

    function xValues(u, splits, axisIdx, foundSpace, foundIncr) {
        const step = splits.length > 1 ? splits[1] - splits[0] : foundIncr;
        const pad2 = function (v) {
            return String(v).padStart(2, '0');
        };

        return splits.map(function (ts) {
            const d = new Date(ts * 1000);
            const year = d.getUTCFullYear();
            if (step >= 330 * DAY) {
                return String(year);
            }
            if (step >= 27 * DAY) {
                return MONTHS[d.getUTCMonth()] + ' ' + year;
            }
            if (step >= 0.9 * DAY) {
                return d.getUTCDate() + ' ' + MONTHS[d.getUTCMonth()];
            }
            if (d.getUTCHours() === 0 && d.getUTCMinutes() === 0 && d.getUTCSeconds() === 0) {
                return d.getUTCDate() + ' ' + MONTHS[d.getUTCMonth()];
            }
            const hm = pad2(d.getUTCHours()) + ':' + pad2(d.getUTCMinutes());
            return step < 60 ? hm + ':' + pad2(d.getUTCSeconds()) : hm;
        });
    }

    function axisSize() {
        return isMobile() ? 56 : 70;
    }

    function mainOptions(width, height) {
        const font = (isMobile() ? 11 : 12) + 'px ' + MONO;
        const size = axisSize();
        const tick = { show: true, size: 5, width: 1 };

        return {
            width: width,
            height: height,
            padding: [10, 0, 0, 0],
            legend: { show: false },
            cursor: {
                y: false,
                drag: { x: true, y: false, setScale: true, dist: 6 },
                // No built-in double-click reset: the Reset button is the only way back to the default range.
                bind: { dblclick: function () { return null; } },
                points: { size: 9, width: 2, stroke: function (u, si) { return si === 1 ? theme.cov : theme.expr; }, fill: theme.bg },
            },
            scales: {
                x: { time: true, auto: false, min: state.min, max: state.max },
                cov: Object.assign({ auto: false }, yBounds('cov', state.min, state.max)),
                expr: Object.assign({ auto: false }, yBounds('expr', state.min, state.max)),
            },
            axes: [
                { stroke: theme.muted, font: font, space: 90, splits: xSplits, values: xValues, grid: { stroke: theme.grid, width: 1 }, ticks: Object.assign({ stroke: theme.grid }, tick) },
                { scale: 'cov', side: 3, size: size, stroke: theme.cov, font: font, space: 50, values: covTicks, grid: { stroke: theme.grid, width: 1 }, ticks: Object.assign({ stroke: theme.cov }, tick) },
                { scale: 'expr', side: 1, size: size, stroke: theme.expr, font: font, space: 50, values: exprTicks, grid: { show: false }, ticks: Object.assign({ stroke: theme.expr }, tick) },
            ],
            series: [
                {},
                {
                    label: 'Type Coverage',
                    scale: 'cov',
                    show: state.visible[0],
                    stroke: theme.cov,
                    width: 2,
                    paths: uPlot.paths.stepped({ align: 1 }),
                    points: { show: function (u, si, i0, i1) { return i1 - i0 <= 60; }, size: 5, fill: theme.cov, stroke: theme.cov },
                },
                {
                    label: 'Analysed Expressions',
                    scale: 'expr',
                    show: state.visible[1],
                    stroke: theme.expr,
                    width: 2,
                    paths: uPlot.paths.stepped({ align: 1 }),
                    points: { show: function (u, si, i0, i1) { return i1 - i0 <= 60; }, size: 5, fill: theme.expr, stroke: theme.expr },
                },
            ],
            hooks: {
                setScale: [function (u, key) {
                    if (key !== 'x') {
                        return;
                    }
                    const min = u.scales.x.min;
                    const max = u.scales.x.max;
                    if (min === null || max === null || (near(min, state.min) && near(max, state.max))) {
                        return;
                    }
                    setRange(min, max, { source: 'main' });
                }],
                setCursor: [onCursor],
                // uPlot nulls the scale of a hidden series, which would also drop its axis and shift the layout.
                setSeries: [function (u, seriesIdx, opts) {
                    if (main && opts && opts.show !== undefined) {
                        applyMainScales(false);
                    }
                }],
            },
        };
    }

    function navOptions(width) {
        const size = axisSize();
        const font = (isMobile() ? 11 : 10.5) + 'px ' + MONO;

        return {
            width: width,
            height: NAV_HEIGHT,
            padding: [4, size, 0, size],
            legend: { show: false },
            cursor: { show: false, drag: { x: false, y: false } },
            select: { show: false },
            scales: {
                x: { time: true, auto: false, min: first, max: last },
                y: Object.assign({ auto: false }, yBounds('expr', first, last)),
            },
            axes: [
                { stroke: theme.muted, font: font, size: 22, gap: 2, space: 80, splits: xSplits, values: xValues, ticks: { show: false }, grid: { show: false } },
                { show: false },
            ],
            series: [
                {},
                { label: 'Analysed Expressions', scale: 'y', stroke: theme.muted, width: 1.25, paths: uPlot.paths.stepped({ align: 1 }), points: { show: false } },
            ],
            hooks: {
                setSize: [function () {
                    syncNav();
                }],
            },
        };
    }

    // ---------- tooltip and click ----------

    function hideTip() {
        el.tip.hidden = true;
        el.tip.classList.remove('is-touch');
    }

    function onCursor(u) {
        const idx = u.cursor.idx;
        const touch = lastPointerType === 'touch';

        if (idx === null || idx === undefined) {
            if (!touch) {
                hideTip();
            }
            return;
        }

        const sha = D.s[idx];
        let html = '<div class="tip-date">' + isoDateTime(D.t[idx]) + ' UTC</div>'
            + '<div>Commit <span class="tip-sha">' + esc(sha.slice(0, 7)) + '</span></div>'
            + '<div>Type coverage <span class="tip-num">' + fmtCov(D.c[idx]) + '</span></div>'
            + '<div>Expressions <span class="tip-num">' + fmtCompact(D.e[idx]) + '</span> <span class="tip-date">(' + fmtInt(D.e[idx]) + ')</span></div>';
        if (touch) {
            html += '<div><a href="' + esc(commitUrl + sha) + '" target="_blank" rel="noopener">Open commit</a></div>';
        }
        el.tip.innerHTML = html;
        el.tip.classList.toggle('is-touch', touch);
        el.tip.hidden = false;

        const wrapBox = el.wrap.getBoundingClientRect();
        const overBox = u.over.getBoundingClientRect();
        const x = overBox.left - wrapBox.left + u.valToPos(D.x[idx], 'x');
        const tipWidth = el.tip.offsetWidth;
        let left = x + 14;
        if (left + tipWidth > wrapBox.width) {
            left = x - tipWidth - 14;
        }
        el.tip.style.left = Math.max(0, left) + 'px';
        el.tip.style.top = (overBox.top - wrapBox.top + 8) + 'px';
    }

    // Is the pointer within CLICK_RADIUS px of the hovered point of a visible series?
    function nearPlottedPoint(idx, px, py) {
        const x = main.valToPos(D.x[idx], 'x');
        return [[0, 'cov', D.c], [1, 'expr', D.e]].some(function (series) {
            return state.visible[series[0]] && Math.hypot(x - px, main.valToPos(series[2][idx], series[1]) - py) <= CLICK_RADIUS;
        });
    }

    function bindMain() {
        const over = main.over;

        let down = null;
        over.addEventListener('pointerdown', function (event) {
            interacting = true;
            lastPointerType = event.pointerType;
            down = { x: event.clientX, y: event.clientY };
        });
        over.addEventListener('pointerup', function (event) {
            if (!down || event.pointerType === 'touch' || event.button !== 0) {
                return;
            }
            const moved = Math.hypot(event.clientX - down.x, event.clientY - down.y);
            down = null;
            const idx = main.cursor.idx;
            if (moved >= 4 || idx === null || idx === undefined) {
                return;
            }
            const box = over.getBoundingClientRect();
            if (nearPlottedPoint(idx, event.clientX - box.left, event.clientY - box.top)) {
                window.open(commitUrl + D.s[idx], '_blank', 'noopener');
            }
        });
    }

    // ---------- navigator ----------

    function buildNavUi() {
        const over = nav.over;
        const dimL = document.createElement('div');
        const dimR = document.createElement('div');
        const win = document.createElement('div');
        const hMin = document.createElement('div');
        const hMax = document.createElement('div');

        dimL.className = 'nav-dim';
        dimR.className = 'nav-dim';
        win.className = 'nav-window';
        hMin.className = 'nav-handle nav-handle-min';
        hMax.className = 'nav-handle nav-handle-max';

        [[hMin, 'Range start'], [hMax, 'Range end']].forEach(function (pair) {
            pair[0].setAttribute('role', 'slider');
            pair[0].setAttribute('tabindex', '0');
            pair[0].setAttribute('aria-label', pair[1]);
        });

        win.append(hMin, hMax);
        over.append(dimL, dimR, win);
        el.nav.setAttribute('role', 'group');
        el.nav.setAttribute('aria-label', 'Range navigator');

        const secPerPx = function () {
            return (last - first) / Math.max(1, over.clientWidth);
        };

        let mode = null;
        let startX = 0;
        let startMin = 0;
        let startMax = 0;

        over.addEventListener('pointerdown', function (event) {
            if (event.button !== 0) {
                return;
            }
            interacting = true;
            // Decide by geometry, not by event.target: touch hit-testing is fuzzy, the coordinates are exact.
            const box = win.getBoundingClientRect();
            const toMin = Math.abs(event.clientX - box.left);
            const toMax = Math.abs(event.clientX - box.right);
            const grab = Math.min(HANDLE_GRAB, box.width * 0.3);
            const inMin = event.clientX >= box.left - HANDLE_REACH && event.clientX <= box.left + grab;
            const inMax = event.clientX <= box.right + HANDLE_REACH && event.clientX >= box.right - grab;
            if (inMin && (!inMax || toMin <= toMax)) {
                mode = 'min';
            } else if (inMax) {
                mode = 'max';
            } else if (event.clientX > box.left && event.clientX < box.right) {
                mode = 'move';
            } else {
                // Click on the track: centre the window there, then keep dragging.
                const x = event.clientX - over.getBoundingClientRect().left;
                const span = state.max - state.min;
                const centre = first + x * secPerPx();
                const min = Math.max(first, Math.min(centre - span / 2, last - span));
                setRange(min, min + span, { source: 'nav' });
                mode = 'move';
            }
            startX = event.clientX;
            startMin = state.min;
            startMax = state.max;
            win.classList.toggle('is-dragging', mode === 'move');
            over.setPointerCapture(event.pointerId);
            event.preventDefault();
        });

        over.addEventListener('pointermove', function (event) {
            if (!mode) {
                return;
            }
            const dx = (event.clientX - startX) * secPerPx();
            let min = startMin;
            let max = startMax;
            if (mode === 'move') {
                const span = startMax - startMin;
                min = Math.max(first, Math.min(startMin + dx, last - span));
                max = min + span;
            } else if (mode === 'min') {
                min = Math.max(first, Math.min(startMin + dx, startMax - minSpan));
            } else {
                max = Math.min(last, Math.max(startMax + dx, startMin + minSpan));
            }
            setRange(min, max, { source: 'nav' });
        });

        const end = function () {
            mode = null;
            win.classList.remove('is-dragging');
        };
        over.addEventListener('pointerup', end);
        over.addEventListener('pointercancel', end);

        // Arrow keys move a handle by 1% of the data span (Shift: 10%), whatever that span is.
        const keyDelta = function (event) {
            const step = Math.max(1, (last - first) / 100) * (event.shiftKey ? 10 : 1);
            if (event.key === 'ArrowLeft' || event.key === 'ArrowDown') {
                return -step;
            }
            if (event.key === 'ArrowRight' || event.key === 'ArrowUp') {
                return step;
            }
            return null;
        };
        const isKey = function (event) {
            return keyDelta(event) !== null || event.key === 'Home' || event.key === 'End';
        };
        hMin.addEventListener('keydown', function (event) {
            if (!isKey(event)) {
                return;
            }
            event.preventDefault();
            const target = event.key === 'Home' ? first : event.key === 'End' ? state.max - minSpan : state.min + keyDelta(event);
            setRange(Math.max(first, Math.min(target, state.max - minSpan)), state.max, { source: 'nav' });
        });
        hMax.addEventListener('keydown', function (event) {
            if (!isKey(event)) {
                return;
            }
            event.preventDefault();
            const target = event.key === 'Home' ? state.min + minSpan : event.key === 'End' ? last : state.max + keyDelta(event);
            setRange(state.min, Math.min(last, Math.max(target, state.min + minSpan)), { source: 'nav' });
        });

        return { dimL: dimL, dimR: dimR, win: win, hMin: hMin, hMax: hMax, over: over };
    }

    function syncNav() {
        if (!nav || !navUi) {
            return;
        }
        const width = navUi.over.clientWidth;
        const left = nav.valToPos(state.min, 'x');
        const right = nav.valToPos(state.max, 'x');

        navUi.win.style.left = left + 'px';
        navUi.win.style.width = Math.max(0, right - left) + 'px';
        navUi.dimL.style.left = '0px';
        navUi.dimL.style.width = Math.max(0, left) + 'px';
        navUi.dimR.style.left = Math.min(width, right) + 'px';
        navUi.dimR.style.width = Math.max(0, width - right) + 'px';

        // Short histories need the time of day to tell handle positions apart.
        const text = last - first < 2 * DAY
            ? function (ts) {
                return isoDateTime(ts) + ' UTC';
            }
            : isoDate;
        [[navUi.hMin, state.min, first, state.max - minSpan], [navUi.hMax, state.max, state.min + minSpan, last]].forEach(function (h) {
            h[0].setAttribute('aria-valuenow', String(Math.round(h[1])));
            h[0].setAttribute('aria-valuetext', text(h[1]));
            h[0].setAttribute('aria-valuemin', String(Math.round(h[2])));
            h[0].setAttribute('aria-valuemax', String(Math.round(h[3])));
        });
    }

    // ---------- build / rebuild ----------

    function destroyCharts() {
        hideTip();
        if (main) {
            main.destroy();
        }
        if (nav) {
            nav.destroy();
        }
        main = null;
        nav = null;
        navUi = null;
    }

    function buildCharts() {
        destroyCharts();
        theme = readTheme();

        const width = el.main.clientWidth;
        main = new uPlot(mainOptions(width, el.main.clientHeight), [D.x, D.c, D.e], el.main);
        nav = new uPlot(navOptions(width), [D.x, D.e], el.nav);

        bindMain();
        navUi = buildNavUi();
        syncNav();
    }

    // Colours are baked into the uPlot options, so a theme or breakpoint change rebuilds both charts.
    // The selected range lives in `state` and survives. Never while a drag is in progress: uPlot 1.6.32 would
    // leave its document-level mouseup listener behind, so the rebuild waits for the pointer to be released.
    function rebuild() {
        if (!D) {
            return;
        }
        if (interacting) {
            pendingRebuild = true;
            return;
        }
        pendingRebuild = false;
        buildCharts();
    }

    function endInteraction() {
        // Deferred so uPlot's own mouseup handler (which follows pointerup) has run first.
        window.setTimeout(function () {
            interacting = false;
            if (pendingRebuild) {
                rebuild();
            }
        }, 0);
    }

    function onResize() {
        if (!main) {
            return;
        }
        const width = el.main.clientWidth;
        if (width > 0 && width !== main.width) {
            main.setSize({ width: width, height: el.main.clientHeight });
            nav.setSize({ width: width, height: NAV_HEIGHT });
        }
    }

    // ---------- accessible summary ----------

    function updateAria() {
        const i0 = Math.max(0, upperBound(D.x, state.min) - 1);
        const i1 = Math.max(i0, lastIndexAt(state.max));
        let lo = Infinity;
        let hi = -Infinity;
        for (let i = i0; i <= i1; i++) {
            lo = Math.min(lo, D.c[i]);
            hi = Math.max(hi, D.c[i]);
        }
        el.main.setAttribute('aria-label',
            'Type coverage and analysed expressions from ' + isoDate(state.min) + ' to ' + isoDate(state.max) + ' (UTC). '
            + 'At the end of the range type coverage is ' + fmtCov(D.c[i1]) + ' (low ' + fmtCov(lo) + ', high ' + fmtCov(hi) + ') '
            + 'and analysed expressions are ' + fmtCompact(D.e[i1]) + '. The commits table lists every data point.');
    }

    // ---------- commits table ----------

    function updatePagerLinks() {
        const hash = currentHash();
        pagerLinks.forEach(function (link) {
            link.href = tablePath + '?page=' + link.dataset.page + hash;
        });
    }

    // Pager links point at the range hash instead of #commits, so the browser cannot scroll to the table itself.
    // Scroll once the chart area has its final height; a reload keeps the browser's restored position.
    function revealTable() {
        const navigation = window.performance.getEntriesByType ? window.performance.getEntriesByType('navigation')[0] : null;
        if (new URLSearchParams(window.location.search).has('page') && !(navigation && navigation.type === 'reload')) {
            commits.scrollIntoView();
        }
    }

    // ---------- legend ----------

    function bindLegend() {
        const latest = { 1: fmtCov(D.c[D.c.length - 1]), 2: fmtCompact(D.e[D.e.length - 1]) };
        el.pills.forEach(function (pill) {
            const series = Number(pill.dataset.series);
            const value = pill.querySelector('.v');
            if (value) {
                value.textContent = latest[series];
            }
            pill.disabled = false;
            pill.addEventListener('click', function () {
                state.visible[series - 1] = !state.visible[series - 1];
                pill.setAttribute('aria-pressed', String(state.visible[series - 1]));
                main.setSeries(series, { show: state.visible[series - 1] });
            });
        });
    }

    // ---------- init ----------

    function showNote(text) {
        el.head.hidden = true;
        el.legend.hidden = true;
        el.body.hidden = true;
        el.hint.hidden = true;
        el.status.hidden = false;
        el.status.classList.add('is-short');
        el.status.textContent = text;
    }

    function init(data) {
        // The row count decides, not the number of distinct timestamps: rows of one second are still rows.
        if (!data || !Array.isArray(data.t) || data.t.length < 2) {
            showNote('Charts appear once there are at least two reports.');
            return;
        }

        D = data;
        D.x = strictlyIncreasing(D.t);
        first = D.x[0];
        last = D.x[D.x.length - 1];
        minSpan = Math.min(HOUR, (last - first) / 2);

        el.status.hidden = true;
        el.body.hidden = false;
        el.hint.hidden = false;

        const fromUrl = parseHash();
        let months = defaultMonths();
        if (fromUrl) {
            state.min = Math.max(first, Math.min(fromUrl.min, last));
            state.max = Math.max(first, Math.min(fromUrl.max, last));
            months = matchPreset(state.min, state.max, fromUrl.precise);
        } else {
            const range = presetRange(months);
            state.min = range[0];
            state.max = range[1];
        }

        buildCharts();
        bindLegend();
        setRange(state.min, state.max, { months: months, immediate: true, source: 'init' });

        el.presets.forEach(function (button) {
            button.addEventListener('click', function () {
                applyPreset(Number(button.dataset.months));
            });
        });
        el.reset.disabled = false;
        el.reset.addEventListener('click', function () {
            applyPreset(defaultMonths());
        });

        if (typeof ResizeObserver === 'function') {
            new ResizeObserver(onResize).observe(el.main);
        } else {
            window.addEventListener('resize', onResize);
        }

        window.matchMedia('(max-width: 640px)').addEventListener('change', rebuild);
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', rebuild);
        document.addEventListener('shepherd:themechange', rebuild);

        window.addEventListener('hashchange', function () {
            const parsed = parseHash();
            if (parsed) {
                const min = Math.max(first, Math.min(parsed.min, last));
                const max = Math.max(first, Math.min(parsed.max, last));
                setRange(min, max, { months: matchPreset(min, max, parsed.precise), immediate: true });
            }
        });

        document.addEventListener('pointerup', endInteraction);
        document.addEventListener('pointercancel', endInteraction);

        // Touch: a tapped tooltip stays open until the next tap elsewhere.
        document.addEventListener('pointerdown', function (event) {
            if (!el.wrap.contains(event.target)) {
                hideTip();
            }
        });

        // Canvas text only needs a repaint once the web fonts arrive; axis sizes are fixed.
        if (document.fonts && document.fonts.status !== 'loaded') {
            document.fonts.ready.then(function () {
                if (main) {
                    main.redraw();
                    nav.redraw();
                }
            });
        }
    }

    function fail(error) {
        window.console.error('Shepherd history chart:', error);
        showNote('Chart data could not be loaded. The table below has the full history.');
    }

    if (typeof uPlot === 'undefined') {
        showNote('The chart library could not be loaded. The table below has the full history.');
        revealTable();
        return;
    }

    window.fetch(card.dataset.src, { headers: { Accept: 'application/json' } })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
        .then(init)
        .catch(fail)
        .then(revealTable);
}());
