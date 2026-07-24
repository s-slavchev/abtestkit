// assets/js/frontend.js
(function () {
  void abTestConfig;
})();

const { restUrl, nonce, postId, index } = abTestConfig;

// Optional: current full-page (page/product) test assignment from PHP
const PAGE_TEST =
  abTestConfig.__pageTest &&
  typeof abTestConfig.__pageTest === "object" &&
  abTestConfig.__pageTest.id &&
  (abTestConfig.__pageTest.variant === "A" ||
    abTestConfig.__pageTest.variant === "B")
    ? {
        id: String(abTestConfig.__pageTest.id),
        variant: abTestConfig.__pageTest.variant,
        goal: abTestConfig.__pageTest.goal || "",
        protocol: abTestConfig.__pageTest.protocol || "",
        noTrack: !!abTestConfig.__pageTest.noTrack,
        httpExcluded: !!abTestConfig.__pageTest.httpExcluded,
      }
    : null;

// Optional: per-product test assignments on Woo listing pages (cards)
// Shape: { [productId: string]: { id, variant, goal } }
const PRODUCT_TESTS =
  abTestConfig.__productTests &&
  typeof abTestConfig.__productTests === "object"
    ? abTestConfig.__productTests
    : null;

// Subset of PRODUCT_TESTS that specifically use the add_to_cart goal
const PRODUCT_TESTS_ADD_TO_CART =
  PRODUCT_TESTS && Object.keys(PRODUCT_TESTS).length
    ? Object.entries(PRODUCT_TESTS).reduce((acc, [pid, info]) => {
        if (info && info.goal === "add_to_cart") {
          acc[pid] = {
            id: String(info.id),
            variant: info.variant === "B" ? "B" : "A",
            goal: "add_to_cart",
          };
        }
        return acc;
      }, {})
    : null;

// Treat WP preview tabs (and your ab_preview helper) as "no-track"
const __urlParams = new URLSearchParams(window.location.search);
const IS_PREVIEW =
  __urlParams.has("preview") ||
  __urlParams.has("ab_preview") ||
  __urlParams.get("abtestkit_preview") === "1";
const IS_HTTPS = window.location.protocol === "https:";
const REQUEST_PROTOCOL = IS_HTTPS ? "https" : "http";
const HTTP_NO_TRACK = !IS_HTTPS || !!(PAGE_TEST && PAGE_TEST.noTrack);

function abtestkitSetCookie(name, value, maxAgeSeconds = 2592000) {
  try {
    document.cookie = `${encodeURIComponent(name)}=${encodeURIComponent(value)}; path=/; max-age=${maxAgeSeconds}; SameSite=Lax`;
  } catch (_) {}
}

function abtestkitGetCookie(name) {
  try {
    const parts = document.cookie ? document.cookie.split("; ") : [];
    for (let i = 0; i < parts.length; i++) {
      const part = parts[i];
      const eq = part.indexOf("=");
      if (eq === -1) continue;
      const key = decodeURIComponent(part.slice(0, eq));
      if (key === name) {
        return decodeURIComponent(part.slice(eq + 1));
      }
    }
  } catch (_) {}
  return "";
}

function abtestkitGetStoredValue(key) {
  try {
    const ls = localStorage.getItem(key);
    if (ls) return ls;
  } catch (_) {}

  const cookieVal = abtestkitGetCookie(key);
  if (cookieVal) {
    try {
      localStorage.setItem(key, cookieVal);
    } catch (_) {}
    return cookieVal;
  }

  return "";
}

function abtestkitPersistValue(key, value, maxAgeSeconds = 2592000) {
  try {
    localStorage.setItem(key, value);
  } catch (_) {}
  abtestkitSetCookie(key, value, maxAgeSeconds);
}

function abtestkitGetStoredVariant(key) {
  const value = abtestkitGetStoredValue(key);
  return value === "A" || value === "B" ? value : "";
}

function abtestkitPersistVariant(key, variant) {
  if (variant !== "A" && variant !== "B") return;
  abtestkitPersistValue(key, variant, 2592000);
}

// Match sidebar normalisation so saved values === runtime clicks.
function abtestkitNormalizeUrl(u) {
  if (!u) return "";
  const raw = String(u).trim();
  if (raw === "#" || /^javascript:/i.test(raw)) return "";

  // mailto
  if (/^mailto:/i.test(raw)) {
    const addr = raw.slice(7).split("?")[0].trim().toLowerCase();
    return addr ? `mailto:${addr}` : "";
  }

  // tel
  if (/^tel:/i.test(raw)) {
    const numRaw = raw.slice(4).split("?")[0];
    const num = numRaw.replace(/[^\d+]/g, "");
    return num ? `tel:${num}` : "";
  }

  // http(s) & relatives
  try {
    const url = new URL(raw, window.location.href);
    if (!/^https?:$/i.test(url.protocol)) return "";
    url.hash = "";
    url.search = "";
    url.pathname = url.pathname.replace(/\/+$/, "") || "/";
    return url.origin + url.pathname;
  } catch {
    return "";
  }
}

// Keep the block's native href (Popup Maker). Only fill if href is empty/# and a variant explicitly provides a URL.
function ensureButtonHref(a, assignedVariant, variants) {
  if (!a) return;

  const currentHref = (a.getAttribute("href") || "").trim();
  const isMeaningful = (s) => !!s && s !== "#" && !/^javascript:/i.test(s);
  if (isMeaningful(currentHref)) return; // respect native popup attrs/href

  // Optionally allow a variant URL (if you ever set one)
  const v =
    (variants && (assignedVariant === "B" ? variants.B : variants.A)) || {};
  const variantUrl =
    (v.url || v.myButtonURL || v.href || "").trim() ||
    (
      a
        .querySelector(`[data-ab-variant="${assignedVariant}"]`)
        ?.getAttribute("data-href") || ""
    ).trim();

  if (variantUrl) {
    a.setAttribute("href", variantUrl);
  }
}

// Unified tracker: same-origin only; keepalive only for clicks
// NOTE: When previewing, this is a no-op to avoid polluting stats.
// You can optionally override postId for cross-page conversions.
function logExcludedHttpVisitOnce() {
  if (!PAGE_TEST) {
    return Promise.resolve();
  }

  if (IS_PREVIEW) {
    return Promise.resolve();
  }

  // Only log when this request was excluded because it is HTTP / no-track.
  if (!HTTP_NO_TRACK) {
    return Promise.resolve();
  }

  // Only once per browser session per test.
  const sessionKey = `ab-pt-http-excluded-${PAGE_TEST.id}`;
  try {
    if (sessionStorage.getItem(sessionKey) === "1") {
      return Promise.resolve();
    }
  } catch (_) {}

  try {
    sessionStorage.setItem(sessionKey, "1");
  } catch (_) {}

  const payload = {
    type: "protocol_warning",
    abTestId: PAGE_TEST.id,
    postId: postId,
    index: typeof index === "number" ? index : 0,
    variant: PAGE_TEST.variant === "B" ? "B" : "A",
    protocol: "http",
    excluded_reason: "http",
  };

  return fetch(`${restUrl}/track`, {
    method: "POST",
    credentials: "same-origin",
    keepalive: true,
    headers: {
      "Content-Type": "application/json",
      ...(nonce ? { "X-WP-Nonce": nonce } : {}),
    },
    body: JSON.stringify(payload),
  })
    .then(async (res) => {
      if (!res.ok) {
        let msg = `HTTP ${res.status}`;
        try {
          const data = await res.json();
          if (data && data.error) msg += ` - ${data.error}`;
        } catch (_) {}
        throw new Error(msg);
      }

      try {
        const data = await res.json();
        if (data && data.success === false && data.error) {
          throw new Error(data.error);
        }
      } catch (_) {
        // ignore non-json/empty body
      }
    })
    .catch((err) => {
      try {
        console.warn("[abtestkit] HTTP exclusion diagnostic failed", err, payload);
      } catch (_) {}
    });
}

function trackPageTestImpressionOnce() {
  if (!PAGE_TEST) {
    return Promise.resolve();
  }

  if (IS_PREVIEW) {
    return Promise.resolve();
  }

  if (HTTP_NO_TRACK) {
    return Promise.resolve();
  }

  const impressionKey = `ab-pt-impression-${PAGE_TEST.id}`;
  const seenCookieKey = `abtestkit_pt_seen_${PAGE_TEST.id}`;

  try {
    if (sessionStorage.getItem(impressionKey) === "1") {
      return Promise.resolve();
    }
  } catch (_) {}

  // sessionStorage is per-tab. A session cookie prevents duplicate impressions
  // across new tabs in the same browser/private browsing session.
  if (abtestkitGetCookie(seenCookieKey) === "1") {
    try {
      sessionStorage.setItem(impressionKey, "1");
    } catch (_) {}

    return Promise.resolve();
  }

  try {
    sessionStorage.setItem(impressionKey, "1");
  } catch (_) {}

  try {
    document.cookie = `${encodeURIComponent(seenCookieKey)}=1; path=/; SameSite=Lax`;
  } catch (_) {}

  return trackEvent({
    type: "impression",
    abTestId: PAGE_TEST.id,
    variant: PAGE_TEST.variant,
  });
}

function trackEvent({ type, abTestId, variant, index: idx, postId: overridePostId }) {
  if (IS_PREVIEW) {
    return Promise.resolve();
  }

  const effectivePostId =
    typeof overridePostId === "number" || typeof overridePostId === "string"
      ? overridePostId
      : postId;

  if (HTTP_NO_TRACK) {
    return Promise.resolve();
  }

  const payload = {
    type,
    abTestId,
    postId: effectivePostId,
    index: typeof idx === "number" ? idx : index || 0,
    variant,
    protocol: REQUEST_PROTOCOL,
  };

  return fetch(`${restUrl}/track`, {
    method: "POST",
    credentials: "same-origin",
    keepalive: type === "click",
    headers: {
      "Content-Type": "application/json",
      ...(nonce ? { "X-WP-Nonce": nonce } : {}),
    },
    body: JSON.stringify(payload),
  })
    .then(async (res) => {
      if (!res.ok) {
        let msg = `HTTP ${res.status}`;
        try {
          const data = await res.json();
          if (data && data.error) msg += ` - ${data.error}`;
        } catch {}
        throw new Error(msg);
      }
    })
    .catch(() => {});
}

// Reusable Section Test impressions.
// PHP renders the chosen A/B shortcode section, then frontend.js logs the
// impression against the current host product/page using the existing /track path.
function trackReusableSectionImpressions() {
  if (IS_PREVIEW || HTTP_NO_TRACK) {
    return;
  }

  const nodes = document.querySelectorAll(
    '[data-abtestkit-reusable-section="1"][data-ab-test-id][data-ab-variant]'
  );

  if (!nodes.length) {
    return;
  }

  const sentThisPage = new Set();

  nodes.forEach((node) => {
    const testId = String(node.getAttribute("data-ab-test-id") || "").trim();
    const variant = String(node.getAttribute("data-ab-variant") || "").trim();

    if (!testId || (variant !== "A" && variant !== "B")) {
      return;
    }

    // One impression per reusable-section test per browser/private session.
    // sessionStorage is per-tab, so use a session cookie to prevent duplicate
    // impressions across new tabs in the same incognito/private window.
    const seenCookieKey = `abtestkit_rs_seen_${testId}`;

    if (sentThisPage.has(testId)) {
      return;
    }

    if (abtestkitGetCookie(seenCookieKey) === "1") {
      return;
    }

    sentThisPage.add(testId);

    try {
      document.cookie = `${encodeURIComponent(seenCookieKey)}=1; path=/; SameSite=Lax`;
    } catch (_) {}

    trackEvent({
      type: "impression",
      abTestId: testId,
      variant,
      postId,
    });
  });
}

// Specialised tracker for Woo PRODUCT tests from listing cards.
// Uses the *product ID* as postId so the server can associate the test correctly.
function trackProductTestEventFromCard({ type, testId, variant, productId }) {
  if (IS_PREVIEW) {
    return Promise.resolve();
  }

  const pidNum = parseInt(productId, 10);
  if (!pidNum || !testId || !variant) {
    return Promise.resolve();
  }

  if (HTTP_NO_TRACK) {
    return Promise.resolve();
  }

  const payload = {
    type,
    abTestId: testId,
    postId: pidNum,
    index: 0,
    variant,
    protocol: REQUEST_PROTOCOL,
  };

  return fetch(`${restUrl}/track`, {
    method: "POST",
    credentials: "same-origin",
    keepalive: type === "click",
    headers: {
      "Content-Type": "application/json",
      ...(nonce ? { "X-WP-Nonce": nonce } : {}),
    },
    body: JSON.stringify(payload),
  }).catch(() => {});
}

// ──────────────────────────────────────────────
// Cross-page page-test sessions (store after seeing the test page)
// ─────────────────────────────────────────────-
const ACTIVE_TEST_KEY = "abtestkit_active_tests_v1";

function loadActivePageTests() {
  try {
    const raw = abtestkitGetStoredValue(ACTIVE_TEST_KEY);
    if (!raw) return [];
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed) ? parsed : [];
  } catch (_) {
    return [];
  }
}

function saveActivePageTests(list) {
  try {
    const raw = JSON.stringify(list);
    abtestkitPersistValue(ACTIVE_TEST_KEY, raw, 604800);
  } catch (_) {}
}

function parseReusableSectionLinks(node) {
  const raw = String(node.getAttribute("data-ab-links") || "").trim();

  if (!raw) {
    return [];
  }

  try {
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed)
      ? parsed.map(String).map((s) => s.trim()).filter(Boolean)
      : [];
  } catch (_) {
    return [];
  }
}

function registerReusableSectionClickSessions() {
  if (IS_PREVIEW || HTTP_NO_TRACK) {
    return;
  }

  const nodes = document.querySelectorAll(
    '[data-abtestkit-reusable-section="1"][data-ab-test-id][data-ab-variant]'
  );

  if (!nodes.length) {
    return;
  }

  let existing = loadActivePageTests();
  let changed = false;

  nodes.forEach((node) => {
    const testId = String(node.getAttribute("data-ab-test-id") || "").trim();
    const variant = String(node.getAttribute("data-ab-variant") || "").trim();
    const goal = String(node.getAttribute("data-ab-goal") || "").trim();
    const links = parseReusableSectionLinks(node);

    if (!testId || (variant !== "A" && variant !== "B")) {
      return;
    }

    if (goal !== "clicks" || !links.length) {
      return;
    }

    existing = existing.filter((t) => String(t.id) !== String(testId));

    existing.push({
      id: testId,
      postId,
      variant,
      goal,
      links,
      converted: false,
      startedAt: Date.now(),
      source: "reusable_section",
    });

    changed = true;
  });

  if (changed) {
    saveActivePageTests(existing);
  }
}

function initialiseReusableSectionTracking() {
  trackReusableSectionImpressions();
  registerReusableSectionClickSessions();
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initialiseReusableSectionTracking, {
    once: true,
  });
} else {
  initialiseReusableSectionTracking();
}

// Called on every page load for full-page/product tests.
// 1) Log the impression once per session (HTTPS only)
// 2) Store cross-page click session info for page tests using goal=clicks
(function registerActivePageTestSession() {
  if (!PAGE_TEST) return;
  if (IS_PREVIEW) return;

  if (HTTP_NO_TRACK) {
    logExcludedHttpVisitOnce();
    return;
  }

  trackPageTestImpressionOnce();

  if (PAGE_TEST.goal !== "clicks") return;

  const src = abTestConfig.__pageTest || {};
  const links = Array.isArray(src.links) ? src.links.map(String) : [];
  if (!links.length) return;

  const existing = loadActivePageTests().filter(
    (t) => String(t.id) !== String(PAGE_TEST.id),
  );

  existing.push({
    id: String(PAGE_TEST.id),
    postId,
    variant: PAGE_TEST.variant === "B" ? "B" : "A",
    goal: PAGE_TEST.goal,
    links,
    converted: false,
    startedAt: Date.now(),
  });

  saveActivePageTests(existing);
})();

// Cross-page handler: given a clicked element + raw href, check any stored
// page tests and fire a conversion when a URL OR CSS selector matches on a
// different page (after the visitor has seen the test page).
function abtestkitPathAndQueryFromUrl(value) {
  const raw = String(value || "").trim();

  if (!raw || raw === "#" || /^javascript:/i.test(raw)) {
    return "";
  }

  try {
    const url = new URL(raw, window.location.href);

    if (!/^https?:$/i.test(url.protocol)) {
      return "";
    }

    const path = url.pathname.replace(/\/+$/, "") || "/";
    return path + (url.search || "");
  } catch (_) {
    return raw.replace(/\/+$/, "");
  }
}

function abtestkitLooksLikeCssSelector(token) {
  const raw = String(token || "").trim();

  if (!raw) {
    return false;
  }

  // Clear URL-ish values should be handled as URLs, not selectors.
  if (/^(https?:|mailto:|tel:|\/)/i.test(raw)) {
    return false;
  }

  return (
    /^[.#\[]/.test(raw) ||
    /^(a|button|form|input|select|textarea|img|p|span|div|section|article|main|header|footer|nav)\b/i.test(raw) ||
    /[\s>+~:]/.test(raw)
  );
}

function abtestkitSelectorFallbacks(selector) {
  const token = String(selector || "");
  const fallbacks = [];

  const add = (sel) => {
    if (sel && !fallbacks.includes(sel)) {
      fallbacks.push(sel);
    }
  };

  // Common theme/header logo patterns, including Astra.
  if (token.indexOf(".custom-logo-link") !== -1) {
    add("a.custom-logo-link, .custom-logo-link");
  }

  if (token.indexOf(".site-title") !== -1) {
    add(".site-title a, a.site-title");
  }

  if (token.indexOf(".site-branding") !== -1) {
    add(".site-branding a[href], .site-branding a");
  }

  // Gutenberg button fallback.
  if (token.indexOf(".wp-block-button__link") !== -1) {
    add("a.wp-block-button__link, .wp-block-button__link");
  }

  // WordPress author/meta links.
  if (token.indexOf(".posted-by") !== -1 || token.indexOf("a.url.fn") !== -1) {
    add('.posted-by a, .entry-meta a[href*="/author/"], a.url.fn');
  }

  return fallbacks;
}

function abtestkitClickMatchesCssSelector(clickEl, selector) {
  if (!clickEl || !selector) {
    return false;
  }

  try {
    if (
      clickEl.matches?.(selector) ||
      clickEl.closest?.(selector)
    ) {
      return true;
    }
  } catch (_) {
    // Invalid/fragile selector: fall through to safer fallbacks.
  }

  const fallbacks = abtestkitSelectorFallbacks(selector);

  for (let i = 0; i < fallbacks.length; i++) {
    try {
      if (
        clickEl.matches?.(fallbacks[i]) ||
        clickEl.closest?.(fallbacks[i])
      ) {
        return true;
      }
    } catch (_) {}
  }

  return false;
}

function abtestkitClickTargetMatchesToken(clickEl, rawHref, rawToken) {
  const token = String(rawToken || "").trim();

  if (!token) {
    return false;
  }

  // CSS selector targets: support .class, #id, [attr], a[href...], button.foo, etc.
  if (abtestkitLooksLikeCssSelector(token)) {
    if (!clickEl) {
      return false;
    }

    return abtestkitClickMatchesCssSelector(clickEl, token);
  }

  if (!rawHref) {
    return false;
  }

  const tokenIsWildcard = token.endsWith("*");
  const cleanToken = tokenIsWildcard ? token.slice(0, -1) : token;

  // Never allow a blank wildcard target to match every click.
  if (tokenIsWildcard && !String(cleanToken || "").trim()) {
    return false;
  }

  const clickedPath = abtestkitPathAndQueryFromUrl(rawHref);
  const tokenPath = abtestkitPathAndQueryFromUrl(cleanToken);

  let clickedAbs = "";
  let tokenAbs = "";

  try {
    clickedAbs = abtestkitNormalizeUrl(rawHref);
  } catch (_) {
    clickedAbs = "";
  }

  try {
    tokenAbs = abtestkitNormalizeUrl(cleanToken);
  } catch (_) {
    tokenAbs = "";
  }

  const comparablePairs = [
    [clickedPath, tokenPath],
    [clickedAbs, tokenAbs],
  ];

  const cleanComparable = (value) => {
    const raw = String(value || "");
    if (!raw) return "";
    const cleaned = raw.replace(/\/+$/, "");
    return cleaned || "/";
  };

  for (let i = 0; i < comparablePairs.length; i++) {
    const clicked = cleanComparable(comparablePairs[i][0]);
    const target = cleanComparable(comparablePairs[i][1]);

    if (!clicked || !target) {
      continue;
    }

    if (tokenIsWildcard) {
      // Root wildcard would be too broad, so treat root as exact only.
      if (target === "/") {
        if (clicked === "/") {
          return true;
        }
      } else if (
        clicked === target ||
        clicked.startsWith(target + "/") ||
        clicked.startsWith(target + "?")
      ) {
        return true;
      }
    } else if (clicked === target) {
      return true;
    }
  }

  return false;
}

function handleCurrentPageTestClick(clickEl, rawHref) {
  if (!PAGE_TEST || PAGE_TEST.goal !== "clicks") {
    return;
  }

  if (IS_PREVIEW || HTTP_NO_TRACK) {
    return;
  }

  if (!clickEl) {
    return;
  }

  const src = abTestConfig.__pageTest || {};
  const links = Array.isArray(src.links)
    ? src.links.map(String).map((s) => s.trim()).filter(Boolean)
    : [];

  if (!links.length) {
    return;
  }

  let matched = false;

  for (let i = 0; i < links.length; i++) {
    if (abtestkitClickTargetMatchesToken(clickEl, rawHref, links[i])) {
      matched = true;
      break;
    }
  }

  if (!matched) {
    return;
  }

  const sessionKey = `ab-pt-clicked-${PAGE_TEST.id}`;

  try {
    if (sessionStorage.getItem(sessionKey) === "1") {
      return;
    }
    sessionStorage.setItem(sessionKey, "1");
  } catch (_) {}

  trackEvent({
    type: "click",
    abTestId: PAGE_TEST.id,
    variant: PAGE_TEST.variant === "B" ? "B" : "A",
    postId,
  });
}

function handleCurrentPageTestClickFromElementCandidates(candidates, rawHref) {
  if (!PAGE_TEST || PAGE_TEST.goal !== "clicks") {
    return;
  }

  if (IS_PREVIEW || HTTP_NO_TRACK) {
    return;
  }

  if (!Array.isArray(candidates) || !candidates.length) {
    return;
  }

  const sessionKey = `ab-pt-clicked-${PAGE_TEST.id}`;

  for (let i = 0; i < candidates.length; i++) {
    const candidate = candidates[i];
    if (!candidate || candidate.nodeType !== 1) {
      continue;
    }

    handleCurrentPageTestClick(candidate, rawHref || "");

    try {
      if (sessionStorage.getItem(sessionKey) === "1") {
        return;
      }
    } catch (_) {}
  }
}

function handleWooAddToCartClickTargetFallback(buttonEl) {
  if (!buttonEl || buttonEl.nodeType !== 1) {
    return;
  }

  const candidates = [];
  const addCandidate = (el) => {
    if (el && el.nodeType === 1 && !candidates.includes(el)) {
      candidates.push(el);
    }
  };

  addCandidate(buttonEl);
  addCandidate(
    buttonEl.closest?.(
      '.single_add_to_cart_button, .add_to_cart_button, button, input[type="submit"], [role="button"]'
    )
  );
  addCandidate(buttonEl.closest?.("form.cart"));

  const href =
    buttonEl.getAttribute?.("href") ||
    buttonEl.closest?.("a[href]")?.getAttribute("href") ||
    buttonEl.closest?.("form")?.getAttribute("action") ||
    "";

  handleCurrentPageTestClickFromElementCandidates(candidates, href);
}

function handleCrossPageClickForActiveTests(clickEl, rawHref) {
  const active = loadActivePageTests();
  if (!active.length) return;

  const now = Date.now();
  let changed = false;
  const updated = [];

  active.forEach((t) => {
    // Drop very old sessions (7 days)
    if (t.startedAt && now - t.startedAt > 7 * 24 * 60 * 60 * 1000) {
      changed = true;
      return;
    }

    if (!Array.isArray(t.links) || !t.links.length) {
      updated.push(t);
      return;
    }

    const isReusableSectionSession = t.source === "reusable_section";

    // For normal full-page tests, keep the old safeguard:
    // don't count clicks on the original test page.
    //
    // For reusable/shortcode tests, allow same-post clicks because the tested
    // shortcode may render on a host page and the chosen hyperlink may also live
    // inside that rendered section.
    if (!isReusableSectionSession && String(t.postId) === String(postId)) {
      updated.push(t);
      return;
    }

    const sessionKey = `ab-pt-clicked-${t.id}`;
    const alreadySessionConverted =
      sessionStorage.getItem(sessionKey) === "1";

    let matched = false;

    for (let i = 0; i < t.links.length; i++) {
      if (abtestkitClickTargetMatchesToken(clickEl, rawHref, t.links[i])) {
        matched = true;
        break;
      }
    }

    if (matched && !alreadySessionConverted) {
      // One conversion per test per browser session, attributed back to the original test.
      sessionStorage.setItem(sessionKey, "1");

      trackEvent({
        type: "click",
        abTestId: t.id,
        variant: t.variant === "B" ? "B" : "A",
        postId: t.postId,
      });

      t.converted = true;
      changed = true;
      updated.push(t);
    } else {
      updated.push(t);
    }
  });

  if (changed) {
    saveActivePageTests(updated);
  }
}

// === Group Sync bootstrap (A+A / B+B across blocks with the same data-ab-group) ===
(function () {
  function forced(groupKey) {
    const p = new URLSearchParams(window.location.search);
    const v = p.get("abgroup__" + groupKey);
    return v === "A" || v === "B" ? v : null;
  }
  function load(groupKey) {
    try {
      const v = localStorage.getItem("abg_" + groupKey);
      if (v === "A" || v === "B") return v;
    } catch (_) {}
    return null;
  }
  function save(groupKey, v) {
    try {
      localStorage.setItem("abg_" + groupKey, v);
    } catch (_) {}
    document.cookie = `abg_${groupKey}=${v};path=/;max-age=2592000`;
  }
  function apply(node, v) {
    node.querySelectorAll("[data-ab-variant]").forEach((el) => {
      el.style.display = "none";
    });
    node.querySelectorAll(`[data-ab-variant="${v}"]`).forEach((el) => {
      el.style.display = "";
    });
    node.setAttribute("data-ab-variant", v);
    node.setAttribute("data-ab-active", v);
  }

  const buckets = {};
  document.querySelectorAll("[data-ab-group]").forEach((node) => {
    const key = node.getAttribute("data-ab-group");
    if (!key) return;
    (buckets[key] ||= []).push(node);
  });

  Object.entries(buckets).forEach(([key, nodes]) => {
    // If this group contains the active full-page/product test block, always use PAGE_TEST.variant.
    let pageTestChosen = null;

    if (PAGE_TEST && PAGE_TEST.id) {
      const containsActivePageTest = nodes.some((node) => {
        if (String(node.getAttribute("data-ab-test-id") || "") === String(PAGE_TEST.id)) {
          return true;
        }
        return !!node.querySelector?.(
          `[data-ab-test-id="${String(PAGE_TEST.id)}"]`
        );
      });

      if (containsActivePageTest) {
        pageTestChosen = PAGE_TEST.variant === "B" ? "B" : "A";
      }
    }

    const chosen =
      pageTestChosen ||
      forced(key) ||
      load(key) ||
      (Math.random() < 0.5 ? "A" : "B");

    save(key, chosen);
    nodes.forEach((n) => apply(n, chosen));
  });
})();

const sentImpressions = new Set();
Object.keys(abTestConfig).forEach(function (key) {
  if (
    [
      "postId",
      "index",
      "nonce",
      "restUrl",
      "_ts",
      "_sig",
      "__pageTest",
      "__productTests",
    ].includes(key)
  )
    return;

  const variants = abTestConfig[key];
  if (typeof variants !== "object" || !variants || !variants.A || !variants.B)
    return;

  const abTestId = key;
  const urlParams = new URLSearchParams(window.location.search);
  const previewParam = urlParams.get("ab_preview");
  const blockEls = document.querySelectorAll(`[data-ab-test-id="${abTestId}"]`);
  // Helper: reveal only the assigned variant child within a test wrapper
  const showVariantChild = (wrapper, assigned, variants) => {
    // Hide all variant children first
    wrapper.querySelectorAll("[data-ab-variant]").forEach((el) => {
      el.style.display = "none";
    });

    // Show the assigned one
    const child = wrapper.querySelector(`[data-ab-variant="${assigned}"]`);
    if (child) child.style.display = "";

    // If wrapper is an <a>, route to the single helper that decides the href.
    if (wrapper.tagName && wrapper.tagName.toLowerCase() === "a") {
      ensureButtonHref(wrapper, assigned, variants);
    }
  };

  blockEls.forEach(function (blockEl) {
    const isActivePageTestBlock =
      PAGE_TEST &&
      PAGE_TEST.id &&
      String(PAGE_TEST.id) === String(abTestId);

    // For the active full-page/product test, always trust PHP's resolved assignment.
    // Do not let group state, localStorage, or random assignment override it.
    const groupNode = blockEl.closest("[data-ab-group]");
    let assigned = null;

    if (isActivePageTestBlock) {
      assigned = PAGE_TEST.variant === "B" ? "B" : "A";
    }

    // For ordinary block tests, grouped nodes can still share a chosen variant.
    if (!assigned && groupNode) {
      assigned =
        groupNode.getAttribute("data-ab-variant") ||
        groupNode.getAttribute("data-ab-active");
    }

    if (!assigned) {
      if (previewParam) {
        const pairs = previewParam.split(",");
        for (let pair of pairs) {
          const [id, variant] = pair.split(":");
          if (id === abTestId && (variant === "A" || variant === "B")) {
            assigned = variant;
            break;
          }
        }
      }

      if (
        !assigned &&
        PAGE_TEST &&
        PAGE_TEST.id &&
        String(PAGE_TEST.id) === String(abTestId) &&
        HTTP_NO_TRACK
      ) {
        assigned = "A";
      }

      if (!assigned) {
        const storageKey = `ab-${abTestId}`;
        assigned =
          abtestkitGetStoredVariant(storageKey) ||
          (Math.random() < 0.5 ? "A" : "B");
        abtestkitPersistVariant(storageKey, assigned);
      }
    }

    blockEl.dataset.abVariant = assigned;
    blockEl.dataset.abIndex = index;

    // If not grouped, toggle visibility here; grouped nodes were handled in the bootstrap
    if (!groupNode) {
      showVariantChild(blockEl, assigned, variants);
      const link = blockEl.querySelector("a.wp-block-button__link");
      if (link) ensureButtonHref(link, assigned, variants);
    } else if (blockEl.matches("a.wp-block-button__link")) {
      // Still ensure href for buttons inside a group
      ensureButtonHref(blockEl, assigned, variants);
    }

    // Impression (once per test per session) for ordinary block tests only.
    // Full-page/product tests are handled separately by trackPageTestImpressionOnce().
    if (!IS_PREVIEW) {
      const isPageTestBlock =
        PAGE_TEST && String(PAGE_TEST.id) === String(abTestId);

      if (!isPageTestBlock) {
        const impKey = `ab-impression-${abTestId}`;

        const alreadySent =
          sentImpressions.has(impKey) ||
          sessionStorage.getItem(impKey) === "1";

        if (!alreadySent) {
          sentImpressions.add(impKey);
          sessionStorage.setItem(impKey, "1");

          trackEvent({
            type: "impression",
            abTestId,
            variant: assigned,
            index,
          });
        }
      }
    }

    // Clicks for non-buttons (skip during preview; late-binding covers buttons)
    if (!blockEl.matches("a.wp-block-button__link")) {
      blockEl.addEventListener(
        "click",
        () => {
          if (IS_PREVIEW) return;
          const key = `ab-clicked-${abTestId}`;
          if (sessionStorage.getItem(key) === "1") return;
          sessionStorage.setItem(key, "1");
          trackEvent({ type: "click", abTestId, variant: assigned, index });
        },
        { passive: true },
      );
    }
  });
});

// ──────────────────────────────────────────────────────────────
// Extra conversion sources: links (including "button-like" anchors) & forms
// (Top-level: bind ASAP rather than after window.load)
// ──────────────────────────────────────────────────────────────

// Build: href -> [testIds] for any test with goal=link,
// or goal=button that selected "other buttons" (conversionLinks)
const hrefToTests = {};
Object.entries(abTestConfig).forEach(([testId, variants]) => {
  if (!variants || typeof variants !== "object") return;
  const goal = variants.conversionGoalType || "button";
  const hrefs = Array.isArray(variants.conversionLinks)
    ? variants.conversionLinks
    : [];
  if (
    (goal === "link" || (goal === "button" && hrefs.length)) &&
    hrefs.length
  ) {
    hrefs.forEach((h) => {
      try {
        const abs = abtestkitNormalizeUrl(h);
        if (!abs) return;
        (hrefToTests[abs] ||= new Set()).add(testId);
      } catch (_) {}
    });
  }
});

// Helper: find assigned variant for a given testId from the DOM
function getAssignedVariant(testId) {
  const host =
    document.querySelector(`[data-ab-test-id="${testId}"]`) ||
    document.querySelector(`[data-block][data-ab-test-id="${testId}"]`);
  if (!host) return null;

  const v1 = host.getAttribute("data-ab-variant");
  if (v1 === "A" || v1 === "B") return v1;

  const group =
    (host.matches?.("[data-ab-group]") && host) ||
    host.closest?.("[data-ab-group]") ||
    host.querySelector?.("[data-ab-group]");
  if (group) {
    const vg =
      group.getAttribute("data-ab-variant") ||
      group.getAttribute("data-ab-active");
    if (vg === "A" || vg === "B") return vg;
  }
  return null;
}

// Global click listener for links/buttons (ALWAYS bound early)
document.addEventListener(
  "click",
  (ev) => {
    if (IS_PREVIEW) return;

    // Anything "button-like": links, <button>, inputs, role=button, etc.
    const clickable =
      ev.target.closest?.(
        'a[href], button, [role="button"], [onclick], input[type="submit"]'
      );
    if (!clickable) return;

    const href = clickable.getAttribute("href") || "";

    // 1) Block-level tests that use conversionLinks (URL-based)
    if (href) {
      let abs = "";
      try {
        abs = abtestkitNormalizeUrl(href);
      } catch (_) {
        abs = "";
      }

      if (abs) {
        const tests = hrefToTests[abs];
        if (tests && tests.size) {
          tests.forEach((testId) => {
            const key = `ab-clicked-${testId}`;
            if (sessionStorage.getItem(key) === "1") return;
            const variant = getAssignedVariant(testId);
            if (!variant) return;
            sessionStorage.setItem(key, "1");
            trackEvent({ type: "click", abTestId: testId, variant });
          });
        }
      }
    }

    // 2) Full-page/product tests on the current page (URL OR CSS selector).
    handleCurrentPageTestClick(clickable, href);

    // 3) Full-page tests with cross-page behaviour (URL OR CSS selector).
    handleCrossPageClickForActiveTests(clickable, href);
  },
  { passive: true, capture: true },
);

// Any form submit counts for:
// - block tests with goal=form or goal=add_to_cart
// - full-page tests (page/product) with goal=add_to_cart
const testsNeedingForm = Object.entries(abTestConfig)
  .filter(([, v]) => {
    const goal = v?.conversionGoalType || "button";
    return goal === "form" || goal === "add_to_cart";
  })
  .map(([id]) => id);

if (
  testsNeedingForm.length ||
  (PAGE_TEST && (PAGE_TEST.goal === "add_to_cart" || PAGE_TEST.goal === "clicks"))
) {
  document.addEventListener(
    "submit",
    (ev) => {
      const __urlParams = new URLSearchParams(window.location.search);
      const IS_PREVIEW =
        __urlParams.has("preview") || __urlParams.has("ab_preview");
      if (IS_PREVIEW) return;

      const form = ev.target;
      if (!form || form.tagName.toLowerCase() !== "form") return;

      // Block/inline tests with goal=form or add_to_cart
      testsNeedingForm.forEach((testId) => {
        const key = `ab-clicked-${testId}`;
        if (sessionStorage.getItem(key) === "1") return;
        const variant = getAssignedVariant(testId);
        if (!variant) return;
        sessionStorage.setItem(key, "1");
        trackEvent({ type: "click", abTestId: testId, variant });
      });

      // Full-page tests (page/product) with goal=add_to_cart
      if (PAGE_TEST && PAGE_TEST.goal === "add_to_cart") {
        const ptKey = `ab-pt-clicked-${PAGE_TEST.id}`;
        if (!sessionStorage.getItem(ptKey)) {
          sessionStorage.setItem(ptKey, "1");
          trackEvent({
            type: "click",
            abTestId: PAGE_TEST.id,
            variant: PAGE_TEST.variant,
          });
        }
      }

      // Full-page/product tests using a chosen click target on the submitted form.
      if (PAGE_TEST && PAGE_TEST.goal === "clicks") {
        handleCurrentPageTestClickFromElementCandidates(
          [form],
          form.getAttribute("action") || form.action || ""
        );
      }
    },
    { passive: true, capture: true },
  );
}

// WooCommerce "Add to cart" conversions (AJAX added_to_cart event)
const testsNeedingAddToCart = Object.entries(abTestConfig)
  .filter(([, v]) => (v?.conversionGoalType || "button") === "add_to_cart")
  .map(([id]) => id);

const hasProductTestsAddToCart =
  PRODUCT_TESTS_ADD_TO_CART &&
  Object.keys(PRODUCT_TESTS_ADD_TO_CART).length > 0;

function abtestkitHasVariantBCartContext() {
  if (PAGE_TEST && PAGE_TEST.variant === "B") {
    return true;
  }

  if (PRODUCT_TESTS && typeof PRODUCT_TESTS === "object") {
    return Object.values(PRODUCT_TESTS).some(
      (info) => info && info.variant === "B",
    );
  }

  return false;
}

function abtestkitClearWooFragmentStorage() {
  const clearFrom = (store) => {
    if (!store) return;

    try {
      const keys = [];
      for (let i = 0; i < store.length; i++) {
        const key = store.key(i);
        if (
          key &&
          (/^wc_fragments_/i.test(key) ||
            /^wc_cart_hash_/i.test(key) ||
            key === "wc_cart_created")
        ) {
          keys.push(key);
        }
      }

      keys.forEach((key) => store.removeItem(key));
    } catch (_) {}
  };

  clearFrom(window.localStorage);
  clearFrom(window.sessionStorage);
}

function abtestkitInstallWooCartFragmentGuard() {
  if (!abtestkitHasVariantBCartContext()) {
    return;
  }

  if (!window.jQuery || !window.jQuery(document).on) {
    return;
  }

  if (window.__abtestkitWooCartFragmentGuardInstalled) {
    return;
  }

  window.__abtestkitWooCartFragmentGuardInstalled = true;

  const $ = window.jQuery;

  // Compatibility guard only: make Woo/themes request fresh server fragments
  // for B-assigned product-test visitors. Server-side PHP must supply the
  // correct totals; this guard does not hide, rewrite, or fake cart values.
  $(document.body).on(
    "click",
    ".single_add_to_cart_button, .add_to_cart_button, form.cart button[type='submit'], form.cart input[type='submit']",
    abtestkitClearWooFragmentStorage,
  );

  $(document.body).on("submit adding_to_cart added_to_cart", function () {
    abtestkitClearWooFragmentStorage();
  });
}

if (abtestkitHasVariantBCartContext()) {
  window.addEventListener("load", abtestkitInstallWooCartFragmentGuard);
}

// Shared handler: take a Woo "add to cart" button (from a category/shop card)
// and send impression + click for any matching PRODUCT test with goal=add_to_cart.
function handleProductCardAddToCartButton($button) {
  const __urlParams = new URLSearchParams(window.location.search);
  const IS_PREVIEW_LOCAL =
    __urlParams.has("preview") || __urlParams.has("ab_preview");
  if (IS_PREVIEW_LOCAL) return;

  if (
    !PRODUCT_TESTS_ADD_TO_CART ||
    !$button ||
    typeof $button.attr !== "function"
  ) {
    return;
  }

  const productIdRaw =
    $button.data("product_id") || $button.attr("data-product_id");
  const productId =
    typeof productIdRaw === "number" || typeof productIdRaw === "string"
      ? String(productIdRaw)
      : "";

  if (!productId || !PRODUCT_TESTS_ADD_TO_CART[productId]) {
    return;
  }

  const info = PRODUCT_TESTS_ADD_TO_CART[productId];
  const testId = String(info.id || "");
  const variant = info.variant === "B" ? "B" : "A";

  if (!testId) return;

  // Debug aid – lets you see in the console that we ran this path
  try {
    console.debug("[abtestkit] product card add_to_cart", {
      productId,
      testId,
      variant,
    });
  } catch (_) {}

  // Ensure we have at least one "view" before the first conversion
  // (card view doesn't normally count as a view)
  const impKey = `ab-pt-impression-${testId}`;
  const seenCookieKey = `abtestkit_pt_seen_${testId}`;
  const alreadySeen =
    sessionStorage.getItem(impKey) === "1" ||
    abtestkitGetCookie(seenCookieKey) === "1";

  if (!alreadySeen) {
    sessionStorage.setItem(impKey, "1");

    // Mirror the page-test "seen" cookie so new tabs in the same
    // browser/private session do not log another impression.
    try {
      document.cookie = `${encodeURIComponent(seenCookieKey)}=1; path=/; SameSite=Lax`;
    } catch (_) {}

    trackProductTestEventFromCard({
      type: "impression",
      testId,
      variant,
      productId,
    });
  } else if (abtestkitGetCookie(seenCookieKey) === "1") {
    try {
      sessionStorage.setItem(impKey, "1");
    } catch (_) {}
  }

  // One add_to_cart conversion per test per session
  const ptKey = `ab-pt-clicked-${testId}`;
  if (!sessionStorage.getItem(ptKey)) {
    sessionStorage.setItem(ptKey, "1");
    trackProductTestEventFromCard({
      type: "click",
      testId,
      variant,
      productId,
    });
  }
}

if (
  testsNeedingAddToCart.length ||
  (PAGE_TEST && (PAGE_TEST.goal === "add_to_cart" || PAGE_TEST.goal === "clicks")) ||
  hasProductTestsAddToCart
) {
  window.addEventListener("load", () => {
    if (!window.jQuery || !window.jQuery(document).on) return;
    const $ = window.jQuery;

    $(document.body).on(
      "adding_to_cart added_to_cart",
      function (event, arg1, arg2, arg3) {
        const __urlParams = new URLSearchParams(window.location.search);
        const IS_PREVIEW =
          __urlParams.has("preview") || __urlParams.has("ab_preview");
        if (IS_PREVIEW) return;

        const $button =
          arg3 && arg3.jquery
            ? arg3
            : arg1 && arg1.jquery
              ? arg1
              : arg2 && arg2.jquery
                ? arg2
                : arg3 && arg3.nodeType === 1
                  ? $(arg3)
                  : arg1 && arg1.nodeType === 1
                    ? $(arg1)
                    : arg2 && arg2.nodeType === 1
                      ? $(arg2)
                      : null;

        // Block/inline tests with goal=add_to_cart
        testsNeedingAddToCart.forEach((testId) => {
          const sessionKey = `ab-clicked-${testId}`;
          if (sessionStorage.getItem(sessionKey) === "1") return;

          const variant = getAssignedVariant(testId);
          if (!variant) return;

          sessionStorage.setItem(sessionKey, "1");
          trackEvent({
            type: "click", // reuse existing 'click' event_type for Bayesian maths
            abTestId: testId,
            variant,
          });
        });

        // Full-page/product tests using a chosen click target on a Woo add-to-cart button.
        if (PAGE_TEST && PAGE_TEST.goal === "clicks" && $button && $button.length) {
          handleWooAddToCartClickTargetFallback($button[0]);
        }

        // Full-page tests (page/product) with goal=add_to_cart on the current page
        if (PAGE_TEST && PAGE_TEST.goal === "add_to_cart") {
          // Ensure we have at least one "view" before the first conversion
          const impKey = `ab-pt-impression-${PAGE_TEST.id}`;
          if (!sessionStorage.getItem(impKey)) {
            sessionStorage.setItem(impKey, "1");

            // Mirror the server-side "seen" cookie so template_redirect
            // doesn't double-log impressions in this session.
            try {
              document.cookie = `abtestkit_pt_seen_${PAGE_TEST.id}=1; path=/`;
            } catch (_) {}

            trackEvent({
              type: "impression",
              abTestId: PAGE_TEST.id,
              variant: PAGE_TEST.variant,
            });
          }

          const ptKey = `ab-pt-clicked-${PAGE_TEST.id}`;
          if (!sessionStorage.getItem(ptKey)) {
            sessionStorage.setItem(ptKey, "1");
            trackEvent({
              type: "click",
              abTestId: PAGE_TEST.id,
              variant: PAGE_TEST.variant,
            });
          }
        }

        // PRODUCT tests on Woo listing pages (cards) with goal=add_to_cart
        if ($button) {
          handleProductCardAddToCartButton($button);
        }
      },
    );

    // Fallback: some themes / setups don't fire "added_to_cart" for archive cards
    // (e.g. non-AJAX add-to-cart). In that case, also hook the button click itself.
    if (hasProductTestsAddToCart) {
      $(document.body).on("click", ".add_to_cart_button", function () {
        const $btn = $(this);
        handleProductCardAddToCartButton($btn);
      });
    }
  });
}


window.addEventListener("load", () => {
  setTimeout(() => {
    const buttons = document.querySelectorAll(".wp-block-button__link");

    const { restUrl, nonce, postId } = window.abTestConfig || {};

    // Build: buttonId -> [targetTestIds]
    const buttonTargetsMap = {};
    Object.entries(abTestConfig).forEach(([testId, variants]) => {
      const sources = variants?.conversionFrom || [];
      sources.forEach((buttonId) => {
        (buttonTargetsMap[buttonId] ||= new Set()).add(testId); // testId is the TARGET (heading/paragraph/image)
      });
    });

    // Augment with group membership (even if no explicit conversionFrom is set)
    const domButtonIds = Array.from(
      document.querySelectorAll("a.wp-block-button__link[data-ab-test-id]"),
    )
      .map((a) => a.getAttribute("data-ab-test-id"))
      .filter(Boolean);

    const uniqueButtonIds = Array.from(
      new Set([...Object.keys(buttonTargetsMap), ...domButtonIds]),
    );

    uniqueButtonIds.forEach((buttonId) => {
      const wrapper =
        document.querySelector(`[data-ab-test-id="${buttonId}"]`) ||
        document.querySelector(`[data-block][data-ab-test-id="${buttonId}"]`);
      if (!wrapper) return;

      // Seed with any groupedAbTests already present in the config (if you store them there)
      const seedFromConfig = abTestConfig[buttonId]?.groupedAbTests || [];
      buttonTargetsMap[buttonId] ||= new Set(seedFromConfig);
      seedFromConfig.forEach((id) => buttonTargetsMap[buttonId].add(id));

      // Find group marker on self, descendants, or ancestors
      let groupHost =
        (wrapper.matches("[data-ab-group]") && wrapper) ||
        wrapper.querySelector("[data-ab-group]") ||
        wrapper.closest("[data-ab-group]");

      // Fallback: if the button isn't inside a group, try any seeded testId's group
      if (!groupHost && seedFromConfig.length) {
        for (const seedId of seedFromConfig) {
          const seedNode = document.querySelector(
            `[data-ab-test-id="${seedId}"]`,
          );
          if (!seedNode) continue;
          const found =
            (seedNode.matches?.("[data-ab-group]") && seedNode) ||
            seedNode.querySelector?.("[data-ab-group]") ||
            seedNode.closest?.("[data-ab-group]");
          if (found) {
            groupHost = found;
            break;
          }
        }
      }

      if (!groupHost) return;

      const groupKey = groupHost.getAttribute("data-ab-group");
      if (!groupKey) return;

      // Add every member in the same group (self + descendants across all group nodes)
      document
        .querySelectorAll(`[data-ab-group="${groupKey}"]`)
        .forEach((groupNode) => {
          const selfId = groupNode.getAttribute("data-ab-test-id");
          if (selfId && selfId !== buttonId)
            buttonTargetsMap[buttonId].add(selfId);

          groupNode.querySelectorAll("[data-ab-test-id]").forEach((el) => {
            const id = el.getAttribute("data-ab-test-id");
            if (id && id !== buttonId) buttonTargetsMap[buttonId].add(id);
          });
        });
    });

    // Write targets to the actual <a.wp-block-button__link> the click handler reads from
    Object.entries(buttonTargetsMap).forEach(([buttonId, targetSet]) => {
      const wrapper = document.querySelector(`[data-ab-test-id="${buttonId}"]`);
      if (!wrapper) return;

      const link = wrapper.matches(".wp-block-button__link")
        ? wrapper
        : wrapper.querySelector(".wp-block-button__link");
      if (!link) return;

      link.setAttribute("data-ab-conversion-targets", [...targetSet].join(","));
    });

    // Bind button clicks (if any exist)
    buttons.forEach((btn) => {
      btn.dataset.abClickBound = "true";
      btn.addEventListener(
        "click",
        () => {
          if (IS_PREVIEW) return;

          const sentTo = new Set();

          // 🎯 Conversion targets (e.g., headings/images this button converts)
          const rawTargets =
            btn.dataset.abConversionTargets ||
            btn.getAttribute("data-ab-conversion-targets") ||
            btn.getAttribute("data-ab-conversion-from") ||
            "";
          const conversionTargets = rawTargets
            .split(",")
            .map((s) => s.trim())
            .filter(Boolean);

          conversionTargets.forEach((targetId) => {
            const sessionKey = `ab-clicked-${targetId}`;
            if (sessionStorage.getItem(sessionKey) === "1") return; // once per session

            const el = document.querySelector(
              `[data-ab-test-id="${targetId}"], [data-block][data-ab-test-id="${targetId}"]`,
            );
            let variant = el?.getAttribute("data-ab-variant");
            if (!variant && el) {
              const inner = el.querySelector(
                "[data-ab-test-id][data-ab-variant]",
              );
              variant = inner?.getAttribute("data-ab-variant");
            }

            if (el && variant && !sentTo.has(targetId)) {
              trackEvent({ type: "click", abTestId: targetId, variant });
              sessionStorage.setItem(sessionKey, "1");
              sentTo.add(targetId);
            }
          });

          // 🟢 Also send click to the button’s own block, if it’s a test block
          const selfTestId = btn.dataset.abTestId;
          const selfVariant = btn.dataset.abVariant;
          if (selfTestId && selfVariant && !sentTo.has(selfTestId)) {
            const selfKey = `ab-clicked-${selfTestId}`;
            if (sessionStorage.getItem(selfKey) !== "1") {
              trackEvent({
                type: "click",
                abTestId: selfTestId,
                variant: selfVariant,
              });
              sessionStorage.setItem(selfKey, "1");
              sentTo.add(selfTestId);
            }
          }
        },
        { passive: true },
      );
    });
  }, 250);
});
