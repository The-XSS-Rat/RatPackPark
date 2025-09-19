(function () {
    const isEmbedded = (function () {
        try {
            return window.self !== window.top;
        } catch (err) {
            return true;
        }
    })();

    if (isEmbedded || document.documentElement.classList.contains('is-embedded')) {
        return;
    }

    if (window.__ratScoreboardLoaded) {
        return;
    }
    window.__ratScoreboardLoaded = true;

    const meta = window.__ratTenantMeta || {};
    const rawTenantId = meta && Object.prototype.hasOwnProperty.call(meta, 'tenantId') ? meta.tenantId : null;
    const tenantId = rawTenantId === null || rawTenantId === undefined ? null : String(rawTenantId);
    const KEY_PREFIX = tenantId ? `ratpack:${tenantId}:` : 'ratpack:global:';
    const SCORE_KEY = `${KEY_PREFIX}score`;
    const MAX_KEY = `${KEY_PREFIX}max`;
    const HISTORY_KEY = `${KEY_PREFIX}history`;
    const SPEEDRUN_KEY = `${KEY_PREFIX}speedrun`;
    const RUN_KEY = `${KEY_PREFIX}runStart`;
    const pendingQueue = Array.isArray(window.__ratScoreboardQueue) ? window.__ratScoreboardQueue : [];
    const runStartMeta = typeof meta.startedAt === 'number' ? meta.startedAt : null;
    const speedrunSeed = meta && meta.speedrun && typeof meta.speedrun === 'object' ? meta.speedrun : {};
    const historySeed = Array.isArray(meta.history) ? meta.history : [];
    let container;
    let scoreValue;
    let maxValue;
    let lastEvent;
    let lastEventWrapper;
    let timerValue;
    let historyList;
    const speedrunNodes = {};

    function readStorage(key) {
        try {
            return window.localStorage.getItem(key);
        } catch (err) {
            return null;
        }
    }

    function writeStorage(key, value) {
        try {
            window.localStorage.setItem(key, String(value));
        } catch (err) {
            // ignore storage issues (e.g. Safari private mode)
        }
    }

    function parseJson(value) {
        if (typeof value !== 'string') {
            return null;
        }
        try {
            return JSON.parse(value);
        } catch (err) {
            return null;
        }
    }

    function formatElapsed(seconds) {
        if (!Number.isFinite(seconds) || seconds < 0) {
            return '--:--';
        }
        const whole = Math.floor(seconds);
        const hrs = Math.floor(whole / 3600);
        const mins = Math.floor((whole % 3600) / 60);
        const secs = whole % 60;
        if (hrs > 0) {
            return `${hrs}:${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }
        return `${mins}:${String(secs).padStart(2, '0')}`;
    }

    function sanitizeText(value) {
        if (value === null || value === undefined) {
            return '';
        }
        return String(value);
    }

    function injectStyles() {
        if (document.getElementById('rat-scoreboard-style')) {
            return;
        }
        const style = document.createElement('style');
        style.id = 'rat-scoreboard-style';
        style.textContent = `
            #rat-scoreboard {
                position: fixed;
                top: 16px;
                right: 16px;
                z-index: 2147483647;
                background: rgba(51, 16, 88, 0.92);
                color: #f9f5ff;
                border-radius: 12px;
                padding: 16px 18px;
                width: 240px;
                box-shadow: 0 12px 32px rgba(44, 0, 90, 0.35);
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                backdrop-filter: blur(6px);
            }
            #rat-scoreboard.rat-scoreboard-new-max {
                animation: ratScorePulse 1.2s ease;
            }
            #rat-scoreboard .rat-scoreboard-title {
                font-size: 16px;
                font-weight: 700;
                letter-spacing: 0.05em;
                margin-bottom: 12px;
                text-transform: uppercase;
                display: flex;
                align-items: center;
                gap: 6px;
            }
            #rat-scoreboard .rat-scoreboard-title span {
                font-size: 14px;
                font-weight: 600;
                background: rgba(255, 255, 255, 0.15);
                border-radius: 999px;
                padding: 4px 8px;
            }
            #rat-scoreboard .rat-scoreboard-metric {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 6px;
                font-size: 15px;
            }
            #rat-scoreboard .rat-scoreboard-metric strong {
                font-size: 20px;
            }
            #rat-scoreboard .rat-scoreboard-metric[data-rat-timer-row] strong {
                font-variant-numeric: tabular-nums;
            }
            #rat-scoreboard .rat-scoreboard-last {
                margin-top: 12px;
                font-size: 13px;
                line-height: 1.4;
                background: rgba(255, 255, 255, 0.12);
                padding: 10px;
                border-radius: 10px;
                min-height: 44px;
                transition: background 0.3s ease, transform 0.3s ease;
            }
            #rat-scoreboard .rat-scoreboard-last.rat-scoreboard-highlight {
                background: rgba(103, 58, 183, 0.55);
                transform: translateY(-2px);
            }
            #rat-scoreboard .rat-scoreboard-max-label {
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }
            #rat-scoreboard .rat-scoreboard-max-label span {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                font-size: 12px;
                padding: 2px 8px;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.18);
            }
            #rat-scoreboard .rat-scoreboard-challenge {
                margin-top: 10px;
                padding: 10px;
                border-radius: 10px;
                background: rgba(255, 255, 255, 0.08);
            }
            #rat-scoreboard .rat-scoreboard-challenge-title {
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: 0.06em;
                opacity: 0.85;
                margin-bottom: 8px;
            }
            #rat-scoreboard .rat-scoreboard-challenge-list {
                list-style: none;
                margin: 0;
                padding: 0;
                display: grid;
                gap: 6px;
            }
            #rat-scoreboard .rat-scoreboard-challenge-list li {
                display: flex;
                justify-content: space-between;
                font-size: 13px;
                align-items: center;
            }
            #rat-scoreboard .rat-scoreboard-challenge-list li .label {
                font-weight: 600;
            }
            #rat-scoreboard .rat-scoreboard-challenge-list li .value {
                font-variant-numeric: tabular-nums;
                padding: 2px 8px;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.15);
            }
            #rat-scoreboard .rat-scoreboard-challenge-list li.rat-scoreboard-speedrun-unlocked .value {
                background: rgba(76, 175, 80, 0.25);
                color: #c8f7c5;
            }
            #rat-scoreboard .rat-scoreboard-history {
                margin-top: 12px;
                background: rgba(255, 255, 255, 0.08);
                border-radius: 10px;
                padding: 10px;
            }
            #rat-scoreboard .rat-scoreboard-history-title {
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: 0.06em;
                margin-bottom: 6px;
                opacity: 0.75;
            }
            #rat-scoreboard .rat-scoreboard-history-list {
                list-style: none;
                margin: 0;
                padding: 0;
                display: grid;
                gap: 6px;
            }
            #rat-scoreboard .rat-scoreboard-history-list li {
                display: flex;
                flex-direction: column;
                gap: 2px;
                font-size: 12px;
                background: rgba(12, 0, 24, 0.35);
                border-radius: 8px;
                padding: 6px 8px;
            }
            #rat-scoreboard .rat-scoreboard-history-list li strong {
                font-size: 13px;
            }
            #rat-scoreboard .rat-scoreboard-history-list li .rat-scoreboard-history-meta {
                display: flex;
                justify-content: space-between;
                font-size: 11px;
                opacity: 0.8;
                font-variant-numeric: tabular-nums;
            }
            #rat-scoreboard .rat-scoreboard-footer {
                margin-top: 10px;
                font-size: 11px;
                opacity: 0.75;
                text-align: right;
            }
            @keyframes ratScorePulse {
                0% { box-shadow: 0 0 0 rgba(255, 255, 255, 0.0); }
                35% { box-shadow: 0 0 18px rgba(255, 193, 7, 0.55); }
                100% { box-shadow: 0 0 0 rgba(255, 255, 255, 0.0); }
            }
        `;
        document.head.appendChild(style);
    }

    function createContainer() {
        const el = document.createElement('div');
        el.id = 'rat-scoreboard';
        el.innerHTML = `
            <div class="rat-scoreboard-title">Scoreboard <span>Speedrun</span></div>
            <div class="rat-scoreboard-metric">
                <span>Current Score</span>
                <strong data-rat-score>0</strong>
            </div>
            <div class="rat-scoreboard-metric">
                <span class="rat-scoreboard-max-label">Best Run<span>🏆</span></span>
                <strong data-rat-max>0</strong>
            </div>
            <div class="rat-scoreboard-metric" data-rat-timer-row>
                <span>Run Timer</span>
                <strong data-rat-timer>--:--</strong>
            </div>
            <div class="rat-scoreboard-challenge">
                <div class="rat-scoreboard-challenge-title">BAC &amp; IDOR Speedrun</div>
                <ul class="rat-scoreboard-challenge-list">
                    <li data-speedrun-item="IDOR">
                        <span class="label">IDOR</span>
                        <span class="value" data-rat-speedrun="IDOR">Locked</span>
                    </li>
                    <li data-speedrun-item="BAC">
                        <span class="label">BAC</span>
                        <span class="value" data-rat-speedrun="BAC">Locked</span>
                    </li>
                </ul>
            </div>
            <div class="rat-scoreboard-last" data-rat-last>Explore the app to unlock findings.</div>
            <div class="rat-scoreboard-history">
                <div class="rat-scoreboard-history-title">Recent Findings</div>
                <ul class="rat-scoreboard-history-list" data-rat-history></ul>
            </div>
            <div class="rat-scoreboard-footer">Speedrun every BAC &amp; IDOR bug to beat your best time.</div>
        `;
        scoreValue = el.querySelector('[data-rat-score]');
        maxValue = el.querySelector('[data-rat-max]');
        lastEvent = el.querySelector('[data-rat-last]');
        lastEventWrapper = lastEvent;
        timerValue = el.querySelector('[data-rat-timer]');
        historyList = el.querySelector('[data-rat-history]');
        el.querySelectorAll('[data-rat-speedrun]').forEach((node) => {
            const key = sanitizeText(node.getAttribute('data-rat-speedrun')).toUpperCase();
            if (key) {
                speedrunNodes[key] = node;
            }
        });
        return el;
    }

    const scoreboard = {
        ready: false,
        score: 0,
        max: 0,
        queue: pendingQueue,
        history: [],
        speedrunTimes: {},
        runStart: runStartMeta || null,
        timerHandle: null,
        init() {
            injectStyles();
            container = createContainer();
            document.body.appendChild(container);
            const storedScore = parseInt(readStorage(SCORE_KEY), 10);
            const storedMax = parseInt(readStorage(MAX_KEY), 10);
            this.score = Number.isFinite(storedScore) ? storedScore : 0;
            this.max = Number.isFinite(storedMax) ? storedMax : 0;
            this.loadHistory();
            this.loadSpeedrun();
            this.ensureRunStart();
            this.ready = true;
            this.updateUI();
            if (Array.isArray(this.queue) && this.queue.length) {
                const copy = this.queue.slice();
                this.queue.length = 0;
                copy.forEach((event) => this.addEvent(event));
            }
            window.__ratScoreboardQueue = this.queue;
        },
        addEvent(event) {
            if (!event) {
                return;
            }
            if (!this.ready) {
                this.queue.push(event);
                return;
            }
            const numericPoints = Number(event.points);
            const points = Number.isFinite(numericPoints) && numericPoints > 0 ? numericPoints : 1;
            this.score += points;
            if (this.score > this.max) {
                this.max = this.score;
                writeStorage(MAX_KEY, this.max);
                if (container) {
                    container.classList.remove('rat-scoreboard-new-max');
                    void container.offsetWidth;
                    container.classList.add('rat-scoreboard-new-max');
                }
            }
            this.captureRunStart(event);
            this.recordHistory(event);
            this.updateSpeedrun(event);
            this.persistState();
            this.updateUI(event);
        },
        updateUI(lastEventData) {
            if (scoreValue) {
                scoreValue.textContent = String(this.score);
            }
            if (maxValue) {
                maxValue.textContent = String(this.max);
            }
            if (lastEventWrapper) {
                if (lastEventData) {
                    const baseSummary = lastEventData.type ? `${lastEventData.type}: ${lastEventData.message}` : lastEventData.message;
                    const elapsedLabel = Number.isFinite(lastEventData.elapsed)
                        ? ` (T+${formatElapsed(lastEventData.elapsed)})`
                        : '';
                    lastEventWrapper.textContent = sanitizeText(baseSummary || 'New activity recorded.') + elapsedLabel;
                    lastEventWrapper.classList.add('rat-scoreboard-highlight');
                    setTimeout(() => {
                        lastEventWrapper.classList.remove('rat-scoreboard-highlight');
                    }, 1200);
                    lastEventWrapper.dataset.hadEvent = '1';
                } else if (!lastEventWrapper.dataset.hadEvent) {
                    lastEventWrapper.textContent = 'Explore the app to unlock findings.';
                }
            }
            this.renderHistory();
            this.renderSpeedrun();
            this.ensureTimer();
        },
        loadHistory() {
            const stored = parseJson(readStorage(HISTORY_KEY));
            const combined = [];
            if (Array.isArray(stored)) {
                combined.push(...stored);
            }
            if (historySeed.length) {
                combined.push(...historySeed);
            }
            const seen = new Set();
            const normalized = combined.filter((entry) => {
                if (!entry || typeof entry !== 'object') {
                    return false;
                }
                const key = `${entry.type || ''}|${entry.message || ''}|${entry.ts || ''}`;
                if (seen.has(key)) {
                    return false;
                }
                seen.add(key);
                return true;
            });
            normalized.sort((a, b) => {
                const aTs = Number.isFinite(Number(a.ts)) ? Number(a.ts) : 0;
                const bTs = Number.isFinite(Number(b.ts)) ? Number(b.ts) : 0;
                return aTs - bTs;
            });
            this.history = normalized.slice(-20).map((entry) => ({
                type: sanitizeText(entry.type || 'Event'),
                message: sanitizeText(entry.message || ''),
                points: Number.isFinite(Number(entry.points)) ? Number(entry.points) : 1,
                ts: Number.isFinite(Number(entry.ts)) ? Number(entry.ts) : null,
                elapsed: Number.isFinite(Number(entry.elapsed)) ? Number(entry.elapsed) : null,
            }));
        },
        loadSpeedrun() {
            const stored = parseJson(readStorage(SPEEDRUN_KEY));
            this.speedrunTimes = stored && typeof stored === 'object' ? stored : {};
            Object.keys(this.speedrunTimes).forEach((key) => {
                const record = this.speedrunTimes[key];
                if (!record || typeof record !== 'object') {
                    delete this.speedrunTimes[key];
                    return;
                }
                const elapsed = Number(record.elapsed);
                if (!Number.isFinite(elapsed)) {
                    delete this.speedrunTimes[key];
                    return;
                }
                this.speedrunTimes[key] = {
                    elapsed,
                    ts: Number.isFinite(Number(record.ts)) ? Number(record.ts) : null,
                    message: sanitizeText(record.message || ''),
                };
            });
            if (speedrunSeed && typeof speedrunSeed === 'object') {
                Object.keys(speedrunSeed).forEach((key) => {
                    const seedRecord = speedrunSeed[key];
                    if (!seedRecord || typeof seedRecord !== 'object') {
                        return;
                    }
                    const seedElapsed = Number(seedRecord.elapsed);
                    if (!Number.isFinite(seedElapsed)) {
                        return;
                    }
                    const current = this.speedrunTimes[key];
                    const currentElapsed = current && Number.isFinite(Number(current.elapsed)) ? Number(current.elapsed) : null;
                    if (currentElapsed === null || seedElapsed < currentElapsed) {
                        this.speedrunTimes[key] = {
                            elapsed: seedElapsed,
                            ts: Number.isFinite(Number(seedRecord.ts)) ? Number(seedRecord.ts) : null,
                            message: sanitizeText(seedRecord.message || ''),
                        };
                    }
                });
            }
        },
        ensureRunStart() {
            const stored = parseInt(readStorage(RUN_KEY), 10);
            if (Number.isFinite(stored)) {
                this.runStart = stored;
            } else if (Number.isFinite(runStartMeta)) {
                this.runStart = runStartMeta;
            } else if (!this.runStart && this.history.length) {
                const first = this.history[0];
                if (first) {
                    const firstTs = Number(first.ts);
                    const firstElapsed = Number(first.elapsed);
                    if (Number.isFinite(firstTs) && Number.isFinite(firstElapsed)) {
                        this.runStart = Math.max(0, Math.floor(firstTs - firstElapsed));
                    }
                }
            }
            if (Number.isFinite(this.runStart) && this.runStart > 0) {
                writeStorage(RUN_KEY, this.runStart);
            }
        },
        captureRunStart(event) {
            if (this.runStart && Number.isFinite(this.runStart)) {
                return;
            }
            if (event) {
                const elapsed = Number(event.elapsed);
                if (!Number.isFinite(elapsed)) {
                    return;
                }
                const eventTs = Number(event.ts);
                const timestamp = Number.isFinite(eventTs) ? eventTs : Math.floor(Date.now() / 1000);
                this.runStart = Math.max(0, Math.floor(timestamp - elapsed));
            }
        },
        recordHistory(event) {
            if (!event) {
                return;
            }
            const entry = {
                type: sanitizeText(event.type || 'Event'),
                message: sanitizeText(event.message || ''),
                points: Number.isFinite(Number(event.points)) ? Number(event.points) : 1,
                ts: Number.isFinite(Number(event.ts)) ? Number(event.ts) : Math.floor(Date.now() / 1000),
                elapsed: Number.isFinite(Number(event.elapsed)) ? Number(event.elapsed) : null,
            };
            this.history.push(entry);
            if (this.history.length > 20) {
                this.history = this.history.slice(-20);
            }
        },
        renderHistory() {
            if (!historyList) {
                return;
            }
            historyList.innerHTML = '';
            if (!this.history.length) {
                const empty = document.createElement('li');
                empty.textContent = 'No findings recorded yet.';
                historyList.appendChild(empty);
                return;
            }
            const recent = this.history.slice(-4).reverse();
            recent.forEach((entry) => {
                const item = document.createElement('li');
                const title = document.createElement('strong');
                title.textContent = `${entry.type}: ${entry.message}`;
                const metaLine = document.createElement('div');
                metaLine.className = 'rat-scoreboard-history-meta';
                const elapsedLabel = Number.isFinite(entry.elapsed) ? `T+${formatElapsed(entry.elapsed)}` : 'Time unknown';
                metaLine.innerHTML = `<span>+${entry.points}</span><span>${elapsedLabel}</span>`;
                item.appendChild(title);
                item.appendChild(metaLine);
                historyList.appendChild(item);
            });
        },
        updateSpeedrun(event) {
            if (!event || !event.type) {
                return;
            }
            const key = String(event.type).toUpperCase();
            const elapsed = Number(event.elapsed);
            if (!Number.isFinite(elapsed)) {
                return;
            }
            const existing = this.speedrunTimes[key];
            const shouldUpdate = !existing || !Number.isFinite(Number(existing.elapsed)) || elapsed < Number(existing.elapsed);
            if (shouldUpdate) {
                this.speedrunTimes[key] = {
                    elapsed,
                    ts: Number.isFinite(Number(event.ts)) ? Number(event.ts) : Math.floor(Date.now() / 1000),
                    message: sanitizeText(event.message || ''),
                };
            }
        },
        renderSpeedrun() {
            Object.keys(speedrunNodes).forEach((key) => {
                const node = speedrunNodes[key];
                if (!node || !node.parentElement) {
                    return;
                }
                const parent = node.parentElement;
                const record = this.speedrunTimes[key];
                const elapsed = record ? Number(record.elapsed) : null;
                if (record && Number.isFinite(elapsed)) {
                    node.textContent = `T+${formatElapsed(elapsed)}`;
                    parent.classList.add('rat-scoreboard-speedrun-unlocked');
                } else {
                    node.textContent = 'Locked';
                    parent.classList.remove('rat-scoreboard-speedrun-unlocked');
                }
            });
        },
        ensureTimer() {
            if (!timerValue) {
                return;
            }
            if (!Number.isFinite(this.runStart) || this.runStart <= 0) {
                timerValue.textContent = '--:--';
                if (this.timerHandle) {
                    clearInterval(this.timerHandle);
                    this.timerHandle = null;
                }
                return;
            }
            if (!this.timerHandle) {
                this.timerHandle = window.setInterval(() => {
                    this.updateTimerDisplay();
                }, 1000);
            }
            this.updateTimerDisplay();
        },
        updateTimerDisplay() {
            if (!timerValue) {
                return;
            }
            if (!Number.isFinite(this.runStart) || this.runStart <= 0) {
                timerValue.textContent = '--:--';
                return;
            }
            const now = Math.floor(Date.now() / 1000);
            timerValue.textContent = formatElapsed(Math.max(0, now - this.runStart));
        },
        persistState() {
            writeStorage(SCORE_KEY, this.score);
            writeStorage(MAX_KEY, this.max);
            try {
                writeStorage(HISTORY_KEY, JSON.stringify(this.history));
            } catch (err) {
                // ignore serialization issues
            }
            try {
                writeStorage(SPEEDRUN_KEY, JSON.stringify(this.speedrunTimes));
            } catch (err) {
                // ignore serialization issues
            }
            if (Number.isFinite(this.runStart) && this.runStart > 0) {
                writeStorage(RUN_KEY, this.runStart);
            }
        },
    };

    document.addEventListener('DOMContentLoaded', () => {
        scoreboard.init();
    });

    window.ratScoreboard = {
        addEvent(event) {
            scoreboard.addEvent(event);
        },
        pushEvents(events) {
            if (!Array.isArray(events)) {
                return;
            }
            events.forEach((event) => scoreboard.addEvent(event));
        },
        getState() {
            return { score: scoreboard.score, max: scoreboard.max };
        },
    };

    window.__ratQueueScoreEvents = function (events) {
        if (!Array.isArray(events) || !events.length) {
            return;
        }
        if (window.ratScoreboard && typeof window.ratScoreboard.pushEvents === 'function') {
            window.ratScoreboard.pushEvents(events);
        } else {
            window.__ratScoreboardQueue = window.__ratScoreboardQueue || [];
            window.__ratScoreboardQueue = window.__ratScoreboardQueue.concat(events);
        }
    };

    window.__ratScoreboardQueue = pendingQueue;
})();
