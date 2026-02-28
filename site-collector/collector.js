(() => {
    const ENDPOINT = "https://collector.alessioyu.xyz/api/log.php";

    // --- session id (ties all events together) ---
    const SESSION_KEY = "cse135_session_id";
    let sessionId = localStorage.getItem(SESSION_KEY);
    if (!sessionId) {
        sessionId = (crypto?.randomUUID?.() || String(Math.random()).slice(2)) + "-" + Date.now();
        localStorage.setItem(SESSION_KEY, sessionId);
    }

    // --- helpers ---
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

    // --- Static data ---
    function collectStatic() {
        // Test if images are enabled
        let allowsImages = true;
        try {
            const img = new Image();
            img.src = "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7";
            allowsImages = img.width !== 0 || img.height !== 0 || img.complete;
        } catch (e) {
            allowsImages = false;
        }

        // Test if CSS is enabled
        let allowsCSS = false;
        try {
            allowsCSS = document.styleSheets.length > 0 ||
                getComputedStyle(document.documentElement).display !== "";
        } catch (e) {
            allowsCSS = false;
        }

        return {
            userAgent: navigator.userAgent,
            language: navigator.language,
            cookiesEnabled: navigator.cookieEnabled,
            screen: { width: screen.width, height: screen.height },
            window: { width: window.innerWidth, height: window.innerHeight },
            connectionType: navigator.connection?.effectiveType ?? null,
            allowsJS: true,
            allowsImages,
            allowsCSS,
        };
    }

    // --- Performance data ---
    function collectPerformance() {
        const nav = performance.getEntriesByType("navigation")[0];
        const timing = nav ? {
            startTime: nav.startTime, // usually 0
            domContentLoaded: nav.domContentLoadedEventEnd,
            loadEventEnd: nav.loadEventEnd,
            responseEnd: nav.responseEnd,
            type: nav.type,
        } : null;

        const pageStart = performance.timeOrigin; // epoch ms-ish
        const pageEnd = nowMs(); // epoch ms
        const totalLoadMs = Math.round(performance.now()); // ms since timeOrigin

        return {
            timing,
            pageStart,
            pageEnd,
            totalLoadMs,
            navigationEntry: nav ? nav.toJSON() : null,
        };
    }

    // fire after page laod 
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

    // --- Activity buffering ---
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

        // end idle if currently idle
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

    // Page enter
    pushRaw({ kind: "page_enter", ts: nowMs(), page: location.href });

    // Page leave
    function onLeave(reason) {
        // If currently idle, close it on leave so duration is captured
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

        // flush whatever is left immediately
        const batch = activityBuf.splice(0, activityBuf.length);
        batch.push({ kind: "page_leave", ts: nowMs(), reason, page: location.href });

        send({
            type: "activity",
            sessionId,
            page: location.href,
            ts: nowMs(),
            data: { events: batch }
        });
    }

    window.addEventListener("pagehide", () => onLeave("pagehide"));
    document.addEventListener("visibilitychange", () => {
        if (document.visibilityState === "hidden") onLeave("hidden");
    });

    // Mouse movement (throttle to ~5/sec)
    let lastMoveTs = 0;
    window.addEventListener("mousemove", (e) => {
        const t = nowMs();
        if (t - lastMoveTs < 200) return;
        lastMoveTs = t;
        pushActivity({ kind: "mousemove", ts: t, x: e.clientX, y: e.clientY });
    });

    // Clicks + which button
    window.addEventListener("click", (e) => {
        pushActivity({ kind: "click", ts: nowMs(), x: e.clientX, y: e.clientY, button: e.button });
    });

    // scroll
    let scrollTicking = false;
    window.addEventListener("scroll", () => {
        if (!scrollTicking) {
            scrollTicking = true;
            requestAnimationFrame(() => {
                pushActivity({ kind: "scroll", ts: nowMs(), x: window.scrollX, y: window.scrollY });
                scrollTicking = false;
            });
        }
    }, { passive: true });

    // Keyboard
    window.addEventListener("keydown", (e) => {
        pushActivity({ kind: "keydown", ts: nowMs(), key: e.key });
    });
    window.addEventListener("keyup", (e) => {
        pushActivity({ kind: "keyup", ts: nowMs(), key: e.key });
    });

    // JS errors
    window.addEventListener("error", (e) => {
        pushActivity({
        kind: "error",
        ts: nowMs(),
        message: e.message,
        file: e.filename,
        line: e.lineno,
        col: e.colno
        });
    });

    setInterval(() => {
        const t = nowMs();
        if (idleStartTs === null && t - lastActivityTs >= 2000) {
            idleStartTs = lastActivityTs + 2000; // approximate “idle began”
            pushRaw({ kind: "idle_start", ts: idleStartTs });
        }
    }, 500);

    // Periodic flush (every 2s)
    setInterval(() => {
        if (activityBuf.length === 0) return;
        const batch = activityBuf.splice(0, activityBuf.length);
        send({
        type: "activity",
        sessionId,
        page: location.href,
        ts: nowMs(),
        data: { events: batch }
        });
    }, 2000);
})();