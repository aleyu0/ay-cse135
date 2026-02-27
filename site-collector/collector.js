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

    // sendBeacon
    if (navigator.sendBeacon) {
      const blob = new Blob([body], { type: "application/json" });
      navigator.sendBeacon(ENDPOINT, blob);
      return;
    }

    // fallback
    fetch(ENDPOINT, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body,
      keepalive: true,
    }).catch(() => {});
  }

  // --- Static data ---
  function collectStatic() {
    return {
      userAgent: navigator.userAgent,
      language: navigator.language,
      cookiesEnabled: navigator.cookieEnabled,
      screen: { width: screen.width, height: screen.height },
      window: { width: window.innerWidth, height: window.innerHeight },
      connectionType: navigator.connection?.effectiveType ?? null,
      // "allowsJS" must be true if this script runs at all
      allowsJS: true,
      // rough checks for images/css
      allowsImages: true,
      allowsCSS: true,
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
})();