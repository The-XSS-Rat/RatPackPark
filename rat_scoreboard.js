(function () {
    if (window.__ratScoreboardLoaded) {
        return;
    }
    window.__ratScoreboardLoaded = true;

    const SCORE_KEY = 'ratTrackScore';
    const MAX_KEY = 'ratTrackMaxScore';
    const pendingQueue = Array.isArray(window.__ratScoreboardQueue) ? window.__ratScoreboardQueue : [];
    let container;
    let scoreValue;
    let maxValue;
    let lastEvent;
    let lastEventWrapper;

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
            <div class="rat-scoreboard-title">Rat Track <span>Scoreboard</span></div>
            <div class="rat-scoreboard-metric">
                <span>Current Score</span>
                <strong data-rat-score>0</strong>
            </div>
            <div class="rat-scoreboard-metric">
                <span class="rat-scoreboard-max-label">Best Run<span>🏆</span></span>
                <strong data-rat-max>0</strong>
            </div>
            <div class="rat-scoreboard-last" data-rat-last>Explore the app to unlock findings.</div>
            <div class="rat-scoreboard-footer">Find IDORs &amp; BAC to climb the board.</div>
        `;
        scoreValue = el.querySelector('[data-rat-score]');
        maxValue = el.querySelector('[data-rat-max]');
        lastEvent = el.querySelector('[data-rat-last]');
        lastEventWrapper = lastEvent;
        return el;
    }

    const scoreboard = {
        ready: false,
        score: 0,
        max: 0,
        queue: pendingQueue,
        init() {
            injectStyles();
            container = createContainer();
            document.body.appendChild(container);
            const storedScore = parseInt(readStorage(SCORE_KEY), 10);
            const storedMax = parseInt(readStorage(MAX_KEY), 10);
            this.score = Number.isFinite(storedScore) ? storedScore : 0;
            this.max = Number.isFinite(storedMax) ? storedMax : 0;
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
            writeStorage(SCORE_KEY, this.score);
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
                    const summary = lastEventData.type ? `${lastEventData.type}: ${lastEventData.message}` : lastEventData.message;
                    lastEventWrapper.textContent = summary || 'New activity recorded.';
                    lastEventWrapper.classList.add('rat-scoreboard-highlight');
                    setTimeout(() => {
                        lastEventWrapper.classList.remove('rat-scoreboard-highlight');
                    }, 1200);
                    lastEventWrapper.dataset.hadEvent = '1';
                } else if (!lastEventWrapper.dataset.hadEvent) {
                    lastEventWrapper.textContent = 'Explore the app to unlock findings.';
                }
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
