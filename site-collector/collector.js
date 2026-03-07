(() => {
    const ENDPOINT = "https://collector.alessioyu.xyz/api/log.php";

    // session id
    const SESSION_KEY = "cse135_session_id";
    let sessionId = localStorage.getItem(SESSION_KEY);
    if (!sessionId) {
        sessionId = (crypto?.randomUUID?.() || String(Math.random()).slice(2)) + "-" + Date.now();
        localStorage.setItem(SESSION_KEY, sessionId);
    }

    // helpers
    const nowMs = () => Math.round(performance.timeOrigin + performance.now());

    function send(payload) {
        const body = JSON.stringify(payload);
        if (navigator.sendBeacon) {
            const blob = new Blob([body], { type: "text/plain" });
            navigator.sendBeacon(ENDPOINT, blob);
            return;
        }
        fetch(ENDPOINT, {
            method: "POST",
            mode: "no-cors",
            body,
            keepalive: true,
        }).catch(() => {});
    }

    // collect static data about browser and device on page load
    function collectStatic() {
        let allowsImages = true;
        try {
            const img = new Image();
            img.src = "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7";
            allowsImages = img.width !== 0 || img.height !== 0 || img.complete;
        } catch (e) { allowsImages = false; }

        let allowsCSS = false;
        try {
            allowsCSS = document.styleSheets.length > 0 ||
                getComputedStyle(document.documentElement).display !== "";
        } catch (e) { allowsCSS = false; }

        const conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection;

        return {
            userAgent: navigator.userAgent,
            language: navigator.language,
            cookiesEnabled: navigator.cookieEnabled,
            screen: { width: screen.width, height: screen.height },
            window: { width: window.innerWidth, height: window.innerHeight },
            pixelRatio: window.devicePixelRatio || 1,
            cores: navigator.hardwareConcurrency || null,
            memory: navigator.deviceMemory || null,
            connectionType: conn?.effectiveType ?? null,
            downlink: conn?.downlink ?? null,
            rtt: conn?.rtt ?? null,
            saveData: conn?.saveData ?? false,
            timezone: Intl?.DateTimeFormat?.()?.resolvedOptions?.()?.timeZone ?? null,
            colorScheme: window.matchMedia?.("(prefers-color-scheme: dark)")?.matches ? "dark" : "light",
            allowsJS: true,
            allowsImages,
            allowsCSS,
        };
    }

    // collect performance metrics after page load
    function collectPerformance() {
        const nav = performance.getEntriesByType("navigation")[0];
        const timing = nav ? {
            startTime: nav.startTime,
            domContentLoaded: nav.domContentLoadedEventEnd,
            loadEventEnd: nav.loadEventEnd,
            responseEnd: nav.responseEnd,
            type: nav.type,
            dns: Math.round(nav.domainLookupEnd - nav.domainLookupStart),
            tcp: Math.round(nav.connectEnd - nav.connectStart),
            tls: nav.secureConnectionStart > 0 ? Math.round(nav.connectEnd - nav.secureConnectionStart) : 0,
            ttfb: Math.round(nav.responseStart - nav.requestStart),
            download: Math.round(nav.responseEnd - nav.responseStart),
            domInteractive: Math.round(nav.domInteractive),
            domComplete: Math.round(nav.domComplete),
            transferSize: nav.transferSize,
            headerSize: nav.transferSize - nav.encodedBodySize,
        } : null;

        // resource timing (grouped by initiatorType)
        const resources = {};
        try {
            performance.getEntriesByType("resource").forEach(r => {
                const t = r.initiatorType || "other";
                if (!resources[t]) resources[t] = { count: 0, totalSize: 0, totalDuration: 0 };
                resources[t].count++;
                resources[t].totalSize += r.transferSize || 0;
                resources[t].totalDuration += r.duration || 0;
            });
        } catch (e) {}

        const pageStart = performance.timeOrigin;
        const pageEnd = nowMs();
        const totalLoadMs = Math.round(performance.now());

        return {
            timing,
            resources,
            pageStart,
            pageEnd,
            totalLoadMs,
            navigationEntry: nav ? nav.toJSON() : null,
        };
    }

    // web vitals 
    function vitalScore(name, val) {
        if (name === "lcp") return val <= 2500 ? "good" : val <= 4000 ? "needs-improvement" : "poor";
        if (name === "cls") return val <= 0.1  ? "good" : val <= 0.25 ? "needs-improvement" : "poor";
        if (name === "inp") return val <= 200  ? "good" : val <= 500  ? "needs-improvement" : "poor";
        return "unknown";
    }

    const vitals = { lcp: null, cls: 0, inp: null };
    let vitalsSent = false;

    function sendVitals() {
        if (vitalsSent) return;
        vitalsSent = true;
        const data = {};
        if (vitals.lcp !== null) data.lcp = { value: vitals.lcp, score: vitalScore("lcp", vitals.lcp) };
        data.cls = { value: Math.round(vitals.cls * 1000) / 1000, score: vitalScore("cls", vitals.cls) };
        if (vitals.inp !== null) data.inp = { value: vitals.inp, score: vitalScore("inp", vitals.inp) };

        if (data.lcp || data.cls || data.inp) {
            send({
                type: "vitals",
                sessionId,
                page: location.href,
                ts: nowMs(),
                data,
            });
            console.log("[collector] sent vitals", data);
        }
    }

    // LCP
    try {
        const lcpObs = new PerformanceObserver((list) => {
            const entries = list.getEntries();
            if (entries.length) vitals.lcp = Math.round(entries[entries.length - 1].startTime);
        });
        lcpObs.observe({ type: "largest-contentful-paint", buffered: true });
    } catch (e) {}

    // CLS
    try {
        const clsObs = new PerformanceObserver((list) => {
            for (const entry of list.getEntries()) {
                if (!entry.hadRecentInput) vitals.cls += entry.value;
            }
        });
        clsObs.observe({ type: "layout-shift", buffered: true });
    } catch (e) {}

    // INP
    try {
        let maxInp = 0;
        const inpObs = new PerformanceObserver((list) => {
            for (const entry of list.getEntries()) {
                const dur = entry.duration;
                if (dur > maxInp) {
                    maxInp = dur;
                    vitals.inp = Math.round(dur);
                }
            }
        });
        inpObs.observe({ type: "event", buffered: true, durationThreshold: 16 });
    } catch (e) {}

    // Send vitals on page leave
    document.addEventListener("visibilitychange", () => {
        if (document.visibilityState === "hidden") sendVitals();
    });
    window.addEventListener("pagehide", () => sendVitals());

    // error tracking
    let errorCount = 0;
    const MAX_ERRORS = 10; // rate limit per page

    function sendError(errorData) {
        if (errorCount >= MAX_ERRORS) return;
        errorCount++;
        send({
            type: "error",
            sessionId,
            page: location.href,
            ts: nowMs(),
            data: errorData,
        });
    }

    // JS runtime errors
    window.addEventListener("error", (e) => {
        // Check if it's a resource load failure (handled separately below)
        if (e.target && e.target !== window) return;
        sendError({
            errorType: "js_runtime",
            message: e.message || "Unknown error",
            file: e.filename || null,
            line: e.lineno || null,
            col: e.colno || null,
            stack: e.error?.stack?.substring(0, 500) || null,
        });
    });

    // resource load failures (img, script, link)
    window.addEventListener("error", (e) => {
        const t = e.target;
        if (!t || t === window) return;
        const tag = t.tagName;
        if (!tag) return;
        const upper = tag.toUpperCase();
        if (upper === "IMG" || upper === "SCRIPT" || upper === "LINK") {
            sendError({
                errorType: "resource_load",
                tag: upper,
                src: t.src || t.href || null,
            });
        }
    }, true); // capture phase

    // unhandled promise rejections
    window.addEventListener("unhandledrejection", (e) => {
        const reason = e.reason;
        sendError({
            errorType: "promise_rejection",
            message: reason?.message || String(reason) || "Unhandled rejection",
            stack: reason?.stack?.substring(0, 500) || null,
        });
    });

    // fire static and performance data on page load
    window.addEventListener("load", () => {
        send({
            type: "static",
            sessionId,
            page: location.href,
            ts: nowMs(),
            data: collectStatic(),
        });

        send({
            type: "performance",
            sessionId,
            page: location.href,
            ts: nowMs(),
            data: collectPerformance(),
        });

        console.log("[collector] sent static + performance", sessionId);
    });

    // activity tracking w/ buffering and idle detection
    const activityBuf = [];
    const MAX_BUF = 200;
    let lastActivityTs = nowMs();
    let idleStartTs = null;

    function pushRaw(evt) {
        activityBuf.push(evt);
        if (activityBuf.length > MAX_BUF) activityBuf.shift();
    }

    function pushActivity(evt) {
        const t = evt.ts ?? nowMs();
        if (idleStartTs !== null) {
            const idleEndTs = t;
            pushRaw({
                kind: "idle_end",
                ts: idleEndTs,
                idleStartTs,
                idleDurationMs: idleEndTs - idleStartTs,
            });
            idleStartTs = null;
        }
        lastActivityTs = t;
        pushRaw(evt);
    }

    // page enter
    pushRaw({ kind: "page_enter", ts: nowMs(), page: location.href });

    // page leave
    function onLeave(reason) {
        if (idleStartTs !== null) {
            const t = nowMs();
            pushRaw({
                kind: "idle_end",
                ts: t,
                idleStartTs,
                idleDurationMs: t - idleStartTs,
                reason: "page_leave",
            });
            idleStartTs = null;
        }
        const batch = activityBuf.splice(0, activityBuf.length);
        batch.push({ kind: "page_leave", ts: nowMs(), reason, page: location.href });
        send({
            type: "activity",
            sessionId,
            page: location.href,
            ts: nowMs(),
            data: { events: batch },
        });
    }

    window.addEventListener("pagehide", () => onLeave("pagehide"));
    document.addEventListener("visibilitychange", () => {
        if (document.visibilityState === "hidden") onLeave("hidden");
    });

    // mouse movement (throttled 200ms)
    let lastMoveTs = 0;
    window.addEventListener("mousemove", (e) => {
        const t = nowMs();
        if (t - lastMoveTs < 200) return;
        lastMoveTs = t;
        pushActivity({ kind: "mousemove", ts: t, x: e.clientX, y: e.clientY });
    });

    // clicks
    window.addEventListener("click", (e) => {
        const target = e.target?.closest("[id],[class],[data-action]");
        pushActivity({
            kind: "click",
            ts: nowMs(),
            x: e.clientX,
            y: e.clientY,
            button: e.button,
            targetTag: e.target?.tagName || null,
            targetId: e.target?.id || null,
            targetText: e.target?.textContent?.substring(0, 50)?.trim() || null,
            selector: target ? (target.id ? "#" + target.id : target.tagName.toLowerCase() + (target.className ? "." + target.className.split(" ")[0] : "")) : null,
        });
    });

    // scroll (rAF throttled)
    let scrollTicking = false;
    let maxScrollDepth = 0;
    window.addEventListener("scroll", () => {
        if (!scrollTicking) {
            scrollTicking = true;
            requestAnimationFrame(() => {
                const scrollY = window.scrollY;
                const docH = document.documentElement.scrollHeight - window.innerHeight;
                const pct = docH > 0 ? Math.round((scrollY / docH) * 100) : 0;
                if (pct > maxScrollDepth) maxScrollDepth = pct;
                pushActivity({ kind: "scroll", ts: nowMs(), x: window.scrollX, y: scrollY, depthPct: pct });
                scrollTicking = false;
            });
        }
    }, { passive: true });

    // keyboard
    window.addEventListener("keydown", (e) => {
        pushActivity({ kind: "keydown", ts: nowMs(), key: e.key });
    });
    window.addEventListener("keyup", (e) => {
        pushActivity({ kind: "keyup", ts: nowMs(), key: e.key });
    });

    // idle detection
    setInterval(() => {
        const t = nowMs();
        if (idleStartTs === null && t - lastActivityTs >= 2000) {
            idleStartTs = lastActivityTs + 2000;
            pushRaw({ kind: "idle_start", ts: idleStartTs });
        }
    }, 500);

    // periodic flush (every 2s)
    setInterval(() => {
        if (activityBuf.length === 0) return;
        const batch = activityBuf.splice(0, activityBuf.length);
        send({
            type: "activity",
            sessionId,
            page: location.href,
            ts: nowMs(),
            data: { events: batch },
        });
    }, 2000);
})();