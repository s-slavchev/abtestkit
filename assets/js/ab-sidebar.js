// assets/js/ab-sidebar.js
(function (wp) {
  const {
    editPost: { PluginSidebar, PluginSidebarMoreMenuItem },
    components: {
      PanelBody,
      ToggleControl,
      TextControl,
      TextareaControl,
      Button,
      RadioControl,
      Notice,
      Dashicon,
      Tooltip,
      Modal,
    },
    data: { useSelect },
    element: { Fragment, useState, useEffect, useRef, createElement: el },
    apiFetch,
  } = wp;
  const { MediaUpload, MediaUploadCheck } = wp.blockEditor;

  // ─────────────────────────────────────────────
  // Helper to display readable button labels
  // ─────────────────────────────────────────────
  const stripHtml = (s = "") => s.replace(/<[^>]*>/g, "");

  const getButtonTextByClientId = (clientId) => {
    const { select } = wp.data;
    const block = select("core/block-editor").getBlock(clientId);
    if (!block) return "";

    // Prefer .text (WP 6.x button attr) then .content as fallback
    let txt = block?.attributes?.text ?? "";
    if (!txt && typeof block?.attributes?.content === "string") {
      txt = stripHtml(block.attributes.content);
    }

    txt = String(txt).replace(/\s+/g, " ").trim();
    if (!txt) txt = "(empty button)";

    // Truncate to keep labels tidy in sidebar
    const MAX = 48;
    if (txt.length > MAX) txt = txt.slice(0, MAX - 1) + "…";
    return txt;
  };

  // Normalize URLs exactly like frontend.js so saved links match runtime clicks
  // Supports: http/https (strip query/hash, normalise trailing slash),
  //           mailto (keep address only, lowercased), tel (digits & + only).
  function abtestkitNormalizeUrlSidebar(u) {
    if (!u) return "";
    const raw = String(u).trim();

    // Ignore empty/hash/javascript links
    if (raw === "#" || /^javascript:/i.test(raw)) return "";

    // MAILTO
    if (/^mailto:/i.test(raw)) {
      // keep only the email address, drop ?subject=...
      const addr = raw.slice(7).split("?")[0].trim().toLowerCase();
      return addr ? `mailto:${addr}` : "";
    }

    // TEL
    if (/^tel:/i.test(raw)) {
      // normalise: keep leading + and digits only
      const numRaw = raw.slice(4).split("?")[0];
      const num = numRaw.replace(/[^\d+]/g, "");
      return num ? `tel:${num}` : "";
    }

    // HTTP(S) & relatives
    try {
      const url = new URL(raw, window.location.origin);
      if (!/^https?:$/i.test(url.protocol)) return ""; // still skip other schemes
      url.hash = "";
      url.search = "";
      url.pathname = url.pathname.replace(/\/+$/, "") || "/";
      return url.origin + url.pathname;
    } catch {
      return "";
    }
  }

  // ─────────────────────────────────────────────
  // Telemetry helper (respects admin opt-in)
  // ─────────────────────────────────────────────
  function sendTelemetry(event, payload = {}) {
    try {
      if (!window.abTestConfig?.telemetry?.optedIn) return;
      return wp
        .apiFetch({
          path: "/abtestkit/v1/telemetry",
          method: "POST",
          headers: { "X-WP-Nonce": window.abTestConfig?.nonce || "" },
          data: { event, payload },
        })
        .catch(() => {});
    } catch (_) {}
  }

  // Returns the document where Gutenberg renders blocks (iframe-friendly)
  function getEditorDoc() {
    // Try the known iframe first
    const ifr = document.querySelector('iframe[name="editor-canvas"]');
    if (ifr && ifr.contentDocument) return ifr.contentDocument;

    // Fallback: common wrapper in recent WP builds
    const alt = document.querySelector(
      ".block-editor-iframe__container iframe",
    );
    if (alt && alt.contentDocument) return alt.contentDocument;

    // Last resort for non-iframe editors
    return document;
  }

  // Smooth-scroll the target block into view and briefly highlight it — without changing selection
  function pingBlockWithoutSelecting(targetClientId) {
    const doc = getEditorDoc();
    const el = doc.querySelector(`[data-block="${targetClientId}"]`);
    if (!el) return;

    // Scroll into view
    if (typeof el.scrollIntoView === "function") {
      el.scrollIntoView({ behavior: "smooth", block: "center" });
    }

    // One-time CSS for the ping highlight
    const STYLE_ID = "abtestkit-ping-css";
    if (!document.getElementById(STYLE_ID)) {
      const tag = document.createElement("style");
      tag.id = STYLE_ID;
      tag.appendChild(
        document.createTextNode(`
        .abtestkit-ping-ring {
          position: relative;
          outline: 2px solid #007cba !important;
          outline-offset: 2px;
          transition: outline-color .2s ease;
        }
      `),
      );
      document.head.appendChild(tag);
    }

    // Add the ring briefly
    el.classList.add("abtestkit-ping-ring");
    setTimeout(() => el.classList.remove("abtestkit-ping-ring"), 750);
  }

  // Scroll + highlight a DOM link node (used in link lists)
  function pingLinkNode(el) {
    if (!el) return;
    const doc = getEditorDoc();
    const target = el.nodeType ? el : doc.querySelector(el);
    if (!target) return;

    target.scrollIntoView({ behavior: "smooth", block: "center" });

    // Reuse the same highlight CSS
    const STYLE_ID = "abtestkit-ping-css";
    if (!document.getElementById(STYLE_ID)) {
      const tag = document.createElement("style");
      tag.id = STYLE_ID;
      tag.textContent = `
      .abtestkit-ping-ring {
        position: relative;
        outline: 2px solid #007cba !important;
        outline-offset: 2px;
        transition: outline-color .2s ease;
      }
    `;
      document.head.appendChild(tag);
    }

    target.classList.add("abtestkit-ping-ring");
    setTimeout(() => target.classList.remove("abtestkit-ping-ring"), 750);
  }

  // Select a block in the editor and scroll it into view (inside iframe-safe)
  function selectBlockAndScroll(targetClientId) {
    try {
      // Select the block in Gutenberg
      wp.data.dispatch("core/block-editor").selectBlock(targetClientId);
    } catch (_) {}

    // Now scroll to the element in the editor canvas
    const doc = (function () {
      const ifr = document.querySelector('iframe[name="editor-canvas"]');
      if (ifr && ifr.contentDocument) return ifr.contentDocument;
      const alt = document.querySelector(
        ".block-editor-iframe__container iframe",
      );
      if (alt && alt.contentDocument) return alt.contentDocument;
      return document;
    })();

    const el = doc.querySelector(`[data-block="${targetClientId}"]`);
    if (el && typeof el.scrollIntoView === "function") {
      el.scrollIntoView({ behavior: "smooth", block: "center" });

      // Optional: brief highlight so it's obvious
      const old = el.style.boxShadow;
      el.style.boxShadow = "0 0 0 3px rgba(0,123,255,.5)";
      setTimeout(() => (el.style.boxShadow = old), 700);
    }
  }

  // ─────────────────────────────────────────────
  // Email Capture (post-first-launch) — UI + post to GAS
  // ─────────────────────────────────────────────
  const AB_EMAIL_LS_KEY = "abtest_email_prompt_done_v1";

  function shouldShowEmailModal() {
    const cfg = window.abTestConfig?.emailCapture;
    if (!cfg) return false;
    if (cfg.enabled !== "yes") return false;

    // One-time only via localStorage
    try {
      if (localStorage.getItem(AB_EMAIL_LS_KEY) === "yes") return false;
    } catch (_) {}
    return true;
  }

  function showEmailModal() {
    if (!shouldShowEmailModal()) return;

    const overlay = document.createElement("div");
    overlay.style.position = "fixed";
    overlay.style.inset = "0";
    overlay.style.background = "rgba(0,0,0,0.45)";
    overlay.style.zIndex = "999999";

    const modal = document.createElement("div");
    Object.assign(modal.style, {
      position: "fixed",
      top: "50%",
      left: "50%",
      transform: "translate(-50%, -50%)",
      width: "min(520px, 92vw)",
      background: "#111",
      color: "#fff",
      borderRadius: "16px",
      boxShadow: "0 10px 40px rgba(0,0,0,.35)",
      padding: "22px 22px 18px",
      fontFamily: "system-ui,-apple-system,Segoe UI,Roboto,sans-serif",
    });

    const title = document.createElement("div");
    title.textContent = "You’re one of our first users — thank you!";
    title.style.fontSize = "18px";
    title.style.fontWeight = "700";
    title.style.marginBottom = "10px";

    const body = document.createElement("div");
    body.style.fontSize = "14px";
    body.style.lineHeight = "1.5";
    body.style.opacity = "0.95";
    body.style.marginBottom = "14px";
    body.innerHTML = `We’d love to keep you in the loop about <strong>early access</strong> to upcoming features and occasionally ask for feedback.<br/>
    No spam.`;

    const form = document.createElement("form");
    form.noValidate = true;
    form.style.display = "flex";
    form.style.gap = "8px";
    form.style.marginTop = "6px";

    const input = document.createElement("input");
    input.type = "email";
    input.placeholder = "you@domain.com";
    // input.required = true; // disable native validation
    input.autocomplete = "email";
    Object.assign(input.style, {
      flex: "1",
      padding: "10px 12px",
      borderRadius: "10px",
      border: "1px solid #333",
      background: "#0e0e0e",
      color: "#fff",
    });

    const submit = document.createElement("button");
    submit.type = "submit";
    submit.textContent = "Share my email";
    Object.assign(submit.style, {
      padding: "10px 14px",
      borderRadius: "10px",
      border: 0,
      background: "#4caf50",
      color: "#fff",
      fontWeight: 600,
      cursor: "pointer",
    });

    const footer = document.createElement("div");
    footer.style.display = "flex";
    footer.style.justifyContent = "space-between";
    footer.style.alignItems = "center";
    footer.style.marginTop = "10px";

    const privacy = document.createElement("div");
    privacy.style.fontSize = "12px";
    privacy.style.opacity = "0.8";
    privacy.innerHTML = `Used only for feedback & product updates.`;

    const skip = document.createElement("button");
    skip.type = "button";
    skip.textContent = "Skip";
    Object.assign(skip.style, {
      background: "transparent",
      border: 0,
      color: "#bbb",
      fontSize: "13px",
      cursor: "pointer",
    });

    const close = () => {
      try {
        localStorage.setItem(AB_EMAIL_LS_KEY, "yes");
      } catch (_) {}
      document.body.removeChild(overlay);
    };

    skip.addEventListener("click", close);

    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      submit.disabled = true;

      const email = (input.value || "").trim();
      const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
      if (!ok) {
        submit.disabled = false;
        input.focus();
        return;
      }

      try {
        const cfg = window.abTestConfig?.emailCapture || {};
        if (!cfg.appsScriptUrl) throw new Error("Missing Apps Script URL");

        const payload = {
          event: "user_email_signup",
          ts: Math.floor(Date.now() / 1000),

          // top-level fields your GAS expects
          plugin: cfg.plugin || "abtestkit",
          version: cfg.version || "1.0.0",
          site: cfg.site || "",
          wp: cfg.wp || "",
          php: cfg.php || "",
          env: cfg.env || "",

          // event-specific data goes under "data"
          data: { email },
        };

        // CORS-friendly: simple request + opaque response (we don't need to read it)
        await fetch(cfg.appsScriptUrl, {
          method: "POST",
          mode: "no-cors",
          keepalive: true,
          headers: { "Content-Type": "text/plain;charset=utf-8" },
          body: JSON.stringify(payload),
        });

        // Assume success if no network exception was thrown
        body.innerHTML = `<strong>Thanks!</strong> We’ll reach out occasionally with early access and feedback invites.`;
        form.remove();
        footer.remove();
        setTimeout(close, 1200);
      } catch (err) {
        submit.disabled = false;
        const errBox = document.createElement("div");
        errBox.style.color = "#ffb4b4";
        errBox.style.fontSize = "12px";
        errBox.style.marginTop = "8px";
        errBox.textContent =
          "Sorry — something went wrong. Please try again later.";
        modal.appendChild(errBox);
      }
    });

    form.appendChild(input);
    form.appendChild(submit);
    footer.appendChild(privacy);
    footer.appendChild(skip);

    modal.appendChild(title);
    modal.appendChild(body);
    modal.appendChild(form);
    modal.appendChild(footer);

    overlay.appendChild(modal);
    document.body.appendChild(overlay);
  }

  function maybePromptForEmail() {
    try {
      showEmailModal();
    } catch (e) {
      // fail silently
    }
  }

  function getAttributeOrDOMValue(attributes, blockClientId, fieldName) {
    // 1) Try attribute tree first (supports nested keys like "foo.bar")
    const parts = String(fieldName || "").split(".");
    let val = attributes;
    for (let p of parts) {
      if (val == null) {
        val = undefined;
        break;
      }
      val = val[p];
    }
    if (typeof val === "string" && val.trim() !== "") return val.trim();

    // 2) Fallback: query the editor DOM (inside the iframe if present)
    if (!blockClientId) return undefined;
    const doc = getEditorDoc();
    const blockEl = doc.querySelector(`[data-block="${blockClientId}"]`);
    if (!blockEl) return undefined;

    // Gutenberg mirrors attributes in nodes with data-wp-block-attribute-key
    const domField = blockEl.querySelector(
      `[data-wp-block-attribute-key="${fieldName}"]`,
    );
    if (domField && typeof domField.innerText === "string") {
      const t = domField.innerText.trim();
      if (t) return t;
    }
    return undefined;
  }

  function setNestedAttribute(patchObj, attributes, fieldName, value) {
    const parts = fieldName.split(".");
    if (parts.length === 1) {
      patchObj[fieldName] = value;
      return;
    }
    const [head, tail] = parts;
    const nestedObj = attributes[head] || {};
    patchObj[head] = {
      ...nestedObj,
      [tail]: value,
    };
  }

  function AbSidebarComponent() {
    const controls = [];
    // ─── Select block & attrs ───────────────────────────────────────────
    const block = useSelect((select) =>
      select("core/block-editor").getSelectedBlock(),
    );
    const attributes = block ? block.attributes : {};
    const clientId = block ? block.clientId : null;
    const blockName = block ? block.name : "";

    // Call custom hooks unconditionally so hook order stays stable across renders
    const existingKeys = useGroupKeys();
    const membersMap = useGroupMembersMap();
    // ───────────────── Group utilities ─────────────────

    // One-time CSS: grow Variant textareas, then scroll after a limit
    useEffect(() => {
      const id = "abtest-variant-textareas-css";
      if (document.getElementById(id)) return;
      const css = `
      .abtest-textarea .components-textarea-control__input {
        min-height: 80px;     /* starts taller than a single line */
        max-height: 260px;    /* grow up to this */
        overflow: auto;       /* then scroll like the editor */
        resize: vertical;     /* user can drag taller if they want */
        line-height: 1.4;
      }
    `;
      const tag = document.createElement("style");
      tag.id = id;
      tag.appendChild(document.createTextNode(css));
      document.head.appendChild(tag);
    }, []);

    // One-time CSS: link-like label for toggle text
    useEffect(() => {
      const id = "abtestkit-linklike-css";
      if (document.getElementById(id)) return;
      const css = `
    .abtestkit-linklike {
      background: none;
      border: 0;
      padding: 0;
      font: inherit;
      color: #007cba;
      cursor: pointer;
      text-decoration: none;
    }
    .abtestkit-linklike:hover,
    .abtestkit-linklike:focus {
      text-decoration: underline;
      outline: none;
    }
  `;
      const tag = document.createElement("style");
      tag.id = id;
      tag.appendChild(document.createTextNode(css));
      document.head.appendChild(tag);
    }, []);

    // Helpers: find group members on this page and reset a list of test IDs
    function getGroupMembersFromAllBlocks(allBlocks, key) {
      const k = String(key || "").toLowerCase();
      if (!k) return [];
      return allBlocks.filter(
        (b) =>
          b?.attributes?.abSync &&
          String(b.attributes.abGroupKey || "").toLowerCase() === k &&
          b?.attributes?.abTestId,
      );
    }

    function resetManyTests(postId, abTestIds) {
      const headers = {
        "Content-Type": "application/json",
        "X-WP-Nonce": window.abTestConfig?.nonce || "",
      };
      const bodyFor = (id) => JSON.stringify({ post_id: postId, abTestId: id });
      return Promise.all(
        abTestIds.map((id) =>
          apiFetch({
            path: `/abtestkit/v1/reset`,
            method: "POST",
            headers,
            body: bodyFor(id),
          }).catch(() => null),
        ),
      );
    }

    function flattenBlocks(blocks, out = []) {
      blocks.forEach((b) => {
        out.push(b);
        if (b.innerBlocks?.length) flattenBlocks(b.innerBlocks, out);
      });
      return out;
    }

    // Read all group keys used on THIS post
    function useGroupKeys() {
      const { useSelect } = wp.data;
      return useSelect((select) => {
        const be = select("core/block-editor");
        if (!be) return [];
        const all = flattenBlocks(be.getBlocks());
        const keys = new Set();
        all.forEach((blk) => {
          const a = blk?.attributes || {};
          if (a.abSync && a.abGroupKey)
            keys.add(String(a.abGroupKey).toLowerCase());
        });
        return Array.from(keys).sort((a, b) => a.localeCompare(b));
      });
    }

    // Map groupKey -> [{ blockName, abTestId }]
    function useGroupMembersMap() {
      const { useSelect } = wp.data;
      return useSelect((select) => {
        const be = select("core/block-editor");
        if (!be) return {};
        const all = flattenBlocks(be.getBlocks());
        const map = {};
        all.forEach((blk) => {
          const a = blk?.attributes || {};
          const k = (a.abGroupKey || "").toLowerCase();
          if (!k || !a.abSync) return;
          (map[k] ||= []).push({
            blockName: blk.name || "unknown",
            abTestId: a.abTestId || "",
          });
        });
        return map;
      });
    }

    // Pretty names for core/* blocks
    function prettyBlock(name = "") {
      if (name.startsWith("core/")) name = name.slice(5);
      const map = {
        heading: "Heading",
        paragraph: "Paragraph",
        image: "Image",
        button: "Button",
        "media-text": "Media & Text",
        cover: "Cover",
      };
      return (
        map[name] ||
        name.replace(/-/g, " ").replace(/\b\w/g, (m) => m.toUpperCase())
      );
    }

    // Random site-wide friendly code: 2 letters + 3 digits (e.g., QZ137), lowercase for storage
    function randomGroupCode() {
      const L = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
      const a = L[Math.floor(Math.random() * 26)];
      const b = L[Math.floor(Math.random() * 26)];
      const n = Math.floor(Math.random() * 1000)
        .toString()
        .padStart(3, "0");
      return (a + b + n).toLowerCase();
    }

    // Ensure the code is unique on this page (extremely low collision anyway)
    function makeUniqueCode(existingKeys) {
      const set = new Set(existingKeys.map((k) => k.toLowerCase()));
      let code = randomGroupCode(),
        guard = 0;
      while (set.has(code) && guard++ < 50) code = randomGroupCode();
      return code;
    }

    // For display ("QZ137") from stored key ("qz137")
    function displayCode(key = "") {
      return String(key).toUpperCase();
    }

    // Tooltip text for a group (multiline)
    function membersTooltip(members = []) {
      if (!members.length) return "No blocks in this group yet.";
      return members
        .map((m) => `${prettyBlock(m.blockName)} • ${m.abTestId || "—"}`)
        .join("\n");
    }
    // ─── Generate or read A/B Test ID ──────────────────────────────────
    const generatedIdRef = useRef(null);
    if (!generatedIdRef.current) {
      generatedIdRef.current = "ab-" + Math.random().toString(36).slice(2, 11);
    }
    const abTestId = attributes.abTestId || generatedIdRef.current;
    const postId = wp.data.select("core/editor").getCurrentPostId();

    const {
      abTestEnabled = false,
      abTestVariants = {},
      abTestRunning = false,
      abTestWinner = "",
      abTestStartedAt = 0,
    } = attributes || {};

    const started = abTestStartedAt > 0;

    function flattenBlocks(blocks) {
      return blocks.flatMap((block) => [
        block,
        ...(block.innerBlocks ? flattenBlocks(block.innerBlocks) : []),
      ]);
    }

    const [errorMsg, setErrorMsg] = useState("");

    // Example: handle error in API fetch (running OR finished)
    useEffect(() => {
      if (!abTestId || !postId || !(abTestRunning || abTestWinner)) return;
      apiFetch({
        path: `/abtestkit/v1/stats?post_id=${postId}&abTestId=${abTestId}&t=${Date.now()}`,
        headers: { "X-WP-Nonce": window.abTestConfig?.nonce || "" },
      })
        .then((fresh) => {
          setStatsByTest((prev) => ({ ...prev, [abTestId]: fresh }));
        })
        .catch((err) => {
          setErrorMsg("Failed to load stats. Check your internet or reload.");
        });
    }, [abTestId, postId, abTestRunning, abTestWinner]);

    // ─── Find connected blocks ───────────────────────────────────────────
    const rootBlocks = wp.data.select("core/block-editor").getBlocks();
    const allBlocks = flattenBlocks(rootBlocks);

    // 🡅 Blocks that point to this one (if it's a CTA block)
    const incomingConnections = allBlocks.filter(
      (b) =>
        b.clientId !== clientId &&
        (b.attributes?.conversionFrom || []).includes(attributes.abTestId),
    );

    // 🡇 Blocks this one points to (if it's a heading or paragraph)
    const outgoingConnections = (attributes.conversionFrom || [])
      .map((id) => allBlocks.find((b) => b.attributes?.abTestId === id))
      .filter(Boolean);

    // ─── Config lookup ─────────────────────────────────────────────────
    const globalConfig = window.abTestConfig?.blockConfig || {};

    // Built-in fallback if block-config.json not present
    const fallbackConfig = {
      "core/heading": { fields: [{ name: "content", label: "Heading Text" }] },
      "core/paragraph": {
        fields: [{ name: "content", label: "Paragraph Text" }],
      },
      "core/button": {
        fields: [
          { name: "text", label: "Button Text" },
          { name: "url", label: "Button URL" },
        ],
      },
      "core/image": { fields: [{ name: "url", label: "Image URL" }] },
      // (No ACF on purpose)
    };

    const thisConfig =
      globalConfig[blockName] || fallbackConfig[blockName] || null;
    const isSupported = !!thisConfig;

    const setAttr = (key, val) => {
      if (clientId) {
        wp.data
          .dispatch("core/block-editor")
          .updateBlockAttributes(clientId, { [key]: val });
      }
    };
    // Defensive handling: ensure abTestVariants is always a safe object
    const safeVariants =
      typeof abTestVariants === "object" && abTestVariants !== null
        ? abTestVariants
        : {};
    const safeCurrent =
      typeof safeVariants[abTestId] === "object" &&
      safeVariants[abTestId] !== null
        ? safeVariants[abTestId]
        : {};

    // Defensive: fields and variants
    const fields = Array.isArray(thisConfig?.fields) ? thisConfig.fields : [];
    const safeFields = Array.isArray(fields) ? fields : [];
    const isConfigBroken = !isSupported || safeFields.length === 0;

    let notSupportedMsg = null;
    if (block && isConfigBroken) {
      notSupportedMsg =
        blockName === "core/buttons"
          ? "This block can contain multiple buttons. Click an individual button inside to get started."
          : `This block type (${blockName}) is not yet supported for A/B testing or is missing its field config.`;
      controls.push(
        el(Notice, { status: "info", isDismissible: false }, notSupportedMsg),
      );
    }

    useEffect(() => {
      if (clientId && !attributes.abTestId && generatedIdRef.current) {
        setAttr("abTestId", generatedIdRef.current);
      }
      activeTestIdRef.current = abTestId;
      setProbA(0);
      setProbB(0);
    }, [clientId, attributes.abTestId, abTestId]);

    // ─── React state & refs ────────────────────────────────────────────
    const [preview, setPreview] = useState("A");
    // null means “still loading”
    const [statsByTest, setStatsByTest] = useState({});

    const [probA, setProbA] = useState(0);
    const [probB, setProbB] = useState(0);
    const [abTestWinnerState, setAbTestWinnerState] = useState("");

    const [showResults, setShowResults] = useState(false);
    const activeTestIdRef = useRef(abTestId);
    const autoFillOnceById = useRef({});

    let currentStats = statsByTest[abTestId] || {
      A: { impressions: 0, clicks: 0 },
      B: { impressions: 0, clicks: 0 },
    };

    // Merge in grouped stats from button blocks if any
    const groupedIds = [];

    // Find all blocks that point to this one (reverse link)
    allBlocks.forEach((b) => {
      if (
        b.clientId !== clientId &&
        Array.isArray(b.attributes?.groupedAbTests) &&
        b.attributes.groupedAbTests.includes(abTestId)
      ) {
        groupedIds.push(b.attributes.abTestId);
      }
    });

    groupedIds.forEach((id) => {
      const extra = statsByTest[id];
      if (!extra) return;
      currentStats.A.impressions += extra.A?.impressions || 0;
      currentStats.B.impressions += extra.B?.impressions || 0;
      currentStats.A.clicks += extra.A?.clicks || 0;
      currentStats.B.clicks += extra.B?.clicks || 0;
    });

    const impressionsA = currentStats.A.impressions;
    const impressionsB = currentStats.B.impressions;
    const clicksA = currentStats.A.clicks;
    const clicksB = currentStats.B.clicks;

    const hasData = impressionsA + impressionsB > 0 || clicksA + clicksB > 0;
    // Only treat a real winner (A or B) as “locked”
    const locked = !!(
      abTestRunning ||
      abTestWinner === "A" ||
      abTestWinner === "B"
    );

    // ─── Auto‐detect Variant A (robust DOM + attribute fallbacks) ─────────
    function autoDetectAndSetVariantA() {
      const block = clientId
        ? wp.data.select("core/block-editor").getBlock(clientId)
        : null;
      if (!block) return false;

      const attrs = block.attributes || {};
      const type = block.name || "";
      const A = {};

      if (type === "core/heading" || type === "core/paragraph") {
        if (attrs.content) A.content = String(attrs.content).trim();
      }

      if (type === "core/button") {
        if (attrs.text || attrs.content) {
          A.text = String(attrs.text || attrs.content).trim();
        }
        if (attrs.url || attrs.href) {
          A.url = String(attrs.url || attrs.href).trim();
        }
      }

      if (type === "core/image") {
        if (attrs.url) {
          A.url = String(attrs.url).trim();
        } else if (attrs.id) {
          try {
            const media = wp.data.select("core").getMedia(attrs.id);
            if (media?.source_url) A.url = String(media.source_url);
          } catch (_) {}
        }
      }

      // If we didn’t find anything, stop
      if (!Object.keys(A).length) return false;

      // Merge into abTestVariants
      const all = attributes.abTestVariants || {};
      const thisIdData = all[abTestId] || {};
      const nextForId = {
        ...thisIdData,
        A: { ...(thisIdData.A || {}), ...A },
        B: { ...(thisIdData.B || {}) },
      };

      wp.data.dispatch("core/block-editor").updateBlockAttributes(clientId, {
        abTestVariants: { ...all, [abTestId]: nextForId },
      });

      return true;
    }

    useEffect(() => {
      if (!clientId || !abTestEnabled || !abTestId) return;
      if (autoFillOnceById.current[abTestId]) return;

      const hasA =
        !!abTestVariants?.[abTestId]?.A &&
        Object.keys(abTestVariants[abTestId].A).length > 0;

      if (!hasA) {
        const didSet = autoDetectAndSetVariantA();
        if (didSet) {
          autoFillOnceById.current[abTestId] = true;
        }
      }
      // IMPORTANT: Do NOT include abTestVariants in deps, or our own write will retrigger this.
    }, [clientId, abTestEnabled, abTestId, thisConfig, blockName]);

    const setSpecificVariantField = (variantKey, field, val) => {
      // Always start from the freshest attributes for this block & test ID
      const currentAll = attributes.abTestVariants || {};
      const currentThis = currentAll[abTestId] || {};
      const currentA = { ...(currentThis.A || {}) };
      const currentB = { ...(currentThis.B || {}) };

      if (variantKey === "A") {
        currentA[field] = val;
      } else {
        currentB[field] = val;
      }

      // Preserve any extra properties at this level (e.g. groupedAbTests)
      const nextForThisId = {
        ...currentThis,
        A: currentA,
        B: currentB,
      };

      setAttr("abTestVariants", {
        ...currentAll,
        [abTestId]: nextForThisId,
      });
    };

    const handleEnableToggle = (enabled) => {
      setAttr("abTestEnabled", enabled);
      if (!enabled) return;

      const postId = wp.data.select("core/editor").getCurrentPostId();

      // 👉 Immediately try to capture Variant A from the current block
      //    (run twice: once now, once after attributes tick)
      try {
        autoFillOnceById.current[abTestId] = false;
      } catch (_) {}
      setTimeout(() => {
        try {
          autoDetectAndSetVariantA();
        } catch (_) {}
      }, 0);
      setTimeout(() => {
        try {
          autoDetectAndSetVariantA();
        } catch (_) {}
      }, 80);

      // Only reset THIS test when enabling; leave group mates untouched.
      resetManyTests(postId, [abTestId]).then(() => {
        setAttr("abTestWinner", "");
        setAttr("abTestEval", null);
        setAttr("abTestResultsViewed", false);
        setAttr("abTestStartedAt", 0);

        setStatsByTest((prev) => ({
          ...prev,
          [abTestId]: {
            A: { impressions: 0, clicks: 0 },
            B: { impressions: 0, clicks: 0 },
          },
        }));

        // 🔔 milestone: first toggle enabled
        try {
          localStorage.setItem("abtest_first_toggle_at", String(Date.now()));
        } catch (_) {}
        sendTelemetry("first_toggle_enabled", {
          postId,
          blockName,
          abTestId,
        });
      });
    };

    const goAndSave = () => {
      const postId = wp.data.select("core/editor").getCurrentPostId();
      const groupKey = (attributes.abGroupKey || "").toLowerCase();
      const members = getGroupMembersFromAllBlocks(allBlocks, groupKey);

      // Keep: inject groupedAbTests for button CTAs
      if (blockName === "core/button") {
        const targets = allBlocks
          .filter(
            (b) =>
              b.attributes?.abTestEnabled &&
              (b.attributes?.conversionFrom || []).includes(abTestId),
          )
          .map((b) => b.attributes?.abTestId)
          .filter(Boolean);

        if (targets.length > 0) {
          const current = safeCurrent || {};
          const updatedVariants = {
            ...safeVariants,
            [abTestId]: {
              ...current,
              groupedAbTests: targets,
            },
          };
          setAttr("abTestVariants", updatedVariants);
        }
      }

      // Reset stats for the whole group (or just this test if no group)
      const idsToReset =
        groupKey && members.length
          ? members.map((m) => m.attributes.abTestId).filter(Boolean)
          : [abTestId];

      resetManyTests(postId, idsToReset).then(() => {
        const be = wp.data.dispatch("core/block-editor");
        const now = Date.now();

        if (groupKey && members.length) {
          // 1) Start ONLY this block
          setAttr("abTestWinner", "");
          setAttr("abTestEval", null);
          setAttr("abTestResultsViewed", false);
          setAttr("abTestStartedAt", now);
          setAttr("abTestRunning", true);
          // 🔔 milestone: first test launched
          let ttl = null;
          try {
            const t = parseInt(
              localStorage.getItem("abtest_first_toggle_at") || "0",
              10,
            );
            if (t > 0) ttl = Date.now() - t;
          } catch (_) {}
          sendTelemetry("first_test_launched", {
            postId,
            blockName,
            abTestId,
            groupKey: attributes.abGroupKey || "" || null,
            grouped: !!(attributes.abSync && attributes.abGroupKey),
            timeToLaunchMs: ttl,
          });
          maybePromptForEmail();

          // 2) Leave other members UNLOCKED, but clear any stale winner/eval flags
          members
            .filter((m) => m.clientId !== clientId)
            .forEach((m) => {
              be.updateBlockAttributes(m.clientId, {
                abTestWinner: "",
                abTestEval: null,
                abTestResultsViewed: false,
                abTestStartedAt: 0,
                abTestRunning: false,
              });
            });
        } else {
          // Not in a group — just start this one
          setAttr("abTestWinner", "");
          setAttr("abTestEval", null);
          setAbTestWinnerState("");
          setAttr("abTestResultsViewed", false);
          setAttr("abTestStartedAt", now);
          setAttr("abTestRunning", true);
          // 🔔 milestone: first test launched
          let ttl = null;
          try {
            const t = parseInt(
              localStorage.getItem("abtest_first_toggle_at") || "0",
              10,
            );
            if (t > 0) ttl = Date.now() - t;
          } catch (_) {}
          sendTelemetry("first_test_launched", {
            postId,
            blockName,
            abTestId,
            groupKey: attributes.abGroupKey || "" || null,
            grouped: !!(attributes.abSync && attributes.abGroupKey),
            timeToLaunchMs: ttl,
          });
          maybePromptForEmail();
        }

        // Zero visible stats for every test we reset, so UI = DB
        setStatsByTest((prev) => {
          const next = { ...prev };
          idsToReset.forEach((id) => {
            next[id] = {
              A: { impressions: 0, clicks: 0 },
              B: { impressions: 0, clicks: 0 },
            };
          });
          return next;
        });

        try {
          window.localStorage.setItem(
            `abTestRunning_${postId}_${clientId}`,
            "true",
          );
        } catch (e) {}
        wp.data.dispatch("core/editor").savePost();
      });
    };

    function evaluate(abTestIdOverride = null) {
      const postId = wp.data.select("core/editor").getCurrentPostId();
      const currentTestId = abTestIdOverride || activeTestIdRef.current;

      return apiFetch({
        path: `/abtestkit/v1/evaluate?post_id=${postId}&abTestId=${currentTestId}&t=${Date.now()}`,
        headers: { "X-WP-Nonce": window.abTestConfig?.nonce || "" },
      })
        .then((res) => {
          if (activeTestIdRef.current !== currentTestId) return res;

          // show probs
          if (typeof res.probA === "number") setProbA(res.probA);
          if (typeof res.probB === "number") setProbB(res.probB);

          // thresholds
          const confidenceThreshold = 0.95;
          const minimumImpressions = 50;
          const maxImpressions = 300;
          const maxDays = 21;

          const totalImpressions =
            (currentStats.A.impressions || 0) +
            (currentStats.B.impressions || 0);
          const startedAt = attributes.abTestStartedAt || 0;
          const daysElapsed =
            startedAt > 0
              ? (Date.now() - startedAt) / (1000 * 60 * 60 * 24)
              : 0;

          const confident =
            res.probA >= confidenceThreshold ||
            res.probB >= confidenceThreshold;
          const haveEnoughData =
            totalImpressions >= minimumImpressions &&
            currentStats.A.clicks + currentStats.B.clicks > 0;
          const isStale =
            totalImpressions >= maxImpressions || daysElapsed >= maxDays;

          // If STALE and we haven't marked finish yet → mark + log once
          if (isStale && !attributes.abTestFinishedAt) {
            const finishedAt = Date.now();
            setAttr("abTestWinner", ""); // no winner
            setAttr("abTestRunning", false);
            setAttr("abTestFinishedAt", finishedAt);
            setAbTestWinnerState("inconclusive");

            // Log "stale" as a decision row
            apiFetch({
              path: `/abtestkit/v1/track`,
              method: "POST",
              headers: { "X-WP-Nonce": window.abTestConfig?.nonce || "" },
              data: {
                type: "stale",
                postId,
                abTestId: currentTestId,
                variant: "", // none
                ts: window.abTestConfig?._ts, // HMAC fallback
                sig: window.abTestConfig?._sig,
              },
            }).catch(() => {});

            // Telemetry: first_test_finished (stale)
            (() => {
              const impressionsA = currentStats?.A?.impressions ?? 0;
              const impressionsB = currentStats?.B?.impressions ?? 0;
              const clicksA = currentStats?.A?.clicks ?? 0;
              const clicksB = currentStats?.B?.clicks ?? 0;

              sendTelemetry("first_test_finished", {
                postId,
                abTestId: currentTestId,
                status: "stale",
                impressionsA,
                impressionsB,
                clicksA,
                clicksB,
                probA: res.probA,
                probB: res.probB,
                ciLower: res.ciLower,
                ciUpper: res.ciUpper,
              });
            })();
          }

          // If a WINNER just crossed threshold → mark + log once
          if (
            res.winner &&
            (res.winner === "A" || res.winner === "B") &&
            confident &&
            haveEnoughData &&
            !attributes.abTestFinishedAt
          ) {
            const finishedAt = Date.now();
            setAttr("abTestWinner", res.winner);
            setAttr("abTestRunning", false);
            setAttr("abTestFinishedAt", finishedAt);
            setAbTestWinnerState(res.winner);

            // Log "decision" (winner declared)
            apiFetch({
              path: `/abtestkit/v1/track`,
              method: "POST",
              headers: { "X-WP-Nonce": window.abTestConfig?.nonce || "" },
              data: {
                type: "decision",
                postId,
                abTestId: currentTestId,
                variant: res.winner, // 'A' or 'B'
                ts: window.abTestConfig?._ts,
                sig: window.abTestConfig?._sig,
              },
            }).catch(() => {});
            // Telemetry: first_test_finished (winner)
            (() => {
              const impressionsA = currentStats?.A?.impressions ?? 0;
              const impressionsB = currentStats?.B?.impressions ?? 0;
              const clicksA = currentStats?.A?.clicks ?? 0;
              const clicksB = currentStats?.B?.clicks ?? 0;

              sendTelemetry("first_test_finished", {
                postId,
                abTestId: currentTestId,
                status: `winner_${res.winner}`, // 'winner_A' or 'winner_B'
                impressionsA,
                impressionsB,
                clicksA,
                clicksB,
                probA: res.probA,
                probB: res.probB,
                ciLower: res.ciLower,
                ciUpper: res.ciUpper,
              });
            })();
          }

          // refresh stats for this and any grouped tests
          const groupedIds =
            window.abTestConfig?.[currentTestId]?.groupedAbTests || [];
          const allIds = [currentTestId, ...groupedIds];

          return Promise.all(
            allIds.map((id) =>
              apiFetch({
                path: `/abtestkit/v1/stats?post_id=${postId}&abTestId=${id}&t=${Date.now()}`,
                headers: { "X-WP-Nonce": window.abTestConfig?.nonce || "" },
              })
                .then((fresh) =>
                  setStatsByTest((prev) => ({ ...prev, [id]: fresh })),
                )
                .catch(() => {}),
            ),
          ).then(() => res);
        })
        .catch(() => {
          // Always resolve a safe object so callers don't crash on res.probA
          return {
            probA: 0.5,
            probB: 0.5,
            winner: "",
            message: "evaluate_failed",
          };
        });
    }

    useEffect(() => {
      if (!abTestId || typeof evaluate !== "function") return;

      if (!window.abTestEvaluate) window.abTestEvaluate = {};
      window.abTestEvaluate[abTestId] = evaluate;

      return () => {
        delete window.abTestEvaluate[abTestId];
      };
    }, [abTestId]);

    useEffect(() => {
      if (
        !abTestId ||
        !abTestRunning ||
        typeof window.abTestEvaluate?.[abTestId] !== "function"
      )
        return;

      // Run once to hydrate probA/probB even if not running
      window.abTestEvaluate[abTestId]();
    }, [abTestId]);

    const reset = () => {
      // Confirm before unlocking
      if (
        abTestRunning &&
        !abTestWinner &&
        !window.confirm(
          "This test is not complete! Unlocking will reset all data. Continue?",
        )
      ) {
        return;
      }
      const postId = wp.data.select("core/editor").getCurrentPostId();
      apiFetch({
        path: `/abtestkit/v1/reset`,
        method: "POST",
        body: JSON.stringify({ post_id: postId, abTestId }),
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": window.abTestConfig?.nonce || "",
        },
      }).then(() => {
        try {
          window.localStorage.removeItem(`abTestRunning_${postId}_${clientId}`);
        } catch (e) {}
        setAttr("abTestWinner", "");
        setAttr("abTestRunning", false);
        setAttr("abTestVariants", {});

        setStatsByTest((prev) => ({
          ...prev,
          [abTestId]: {
            A: { impressions: 0, clicks: 0 },
            B: { impressions: 0, clicks: 0 },
          },
        }));
      });
    };

    // ─── Locked state & stats polling (unchanged) ──────────────────────
    useEffect(() => {
      if (!abTestRunning || abTestWinner) return;
      let isMounted = true;
      const postId = wp.data.select("core/editor").getCurrentPostId();

      const groupedIds = window.abTestConfig?.[abTestId]?.groupedAbTests || [];
      const allIds = [abTestId, ...groupedIds];

      const fetchStats = () => {
        apiFetch({
          path: `/abtestkit/v1/stats?post_id=${postId}&abTestIds=${allIds.join(",")}&t=${Date.now()}`,
          headers: { "X-WP-Nonce": window.abTestConfig?.nonce || "" },
        }).then((res) => {
          if (!isMounted) return;

          // 🛡️ Ensure only updating stats for the currently viewed test group
          const currentIds = [
            abTestId,
            ...(window.abTestConfig?.[abTestId]?.groupedAbTests || []),
          ];

          let filteredRes = {};
          for (const id of currentIds) {
            if (res?.[id]?.A || res?.[id]?.B) {
              filteredRes[id] = res[id];
            }
          }

          setStatsByTest((prev) => ({
            ...prev,
            ...filteredRes,
          }));
        });
      };

      fetchStats();
      const intervalId = setInterval(fetchStats, 5000);

      return () => {
        isMounted = false;
        clearInterval(intervalId);
      };
    }, [abTestRunning, started, abTestId, abTestWinner]);

    // ─── Auto-evaluate confidence ───────────────────────────────────────
    // Always fetch once (even if winner/locked), and poll only while running.
    useEffect(() => {
      const postId = wp.data.select("core/editor").getCurrentPostId();
      if (!postId || !abTestId || !abTestRunning) return;

      let isMounted = true;
      let intervalId = null;

      const fetchOnce = () => {
        const evalFn = window.abTestEvaluate?.[abTestId];
        if (typeof evalFn !== "function") return;

        return evalFn()
          .then((res) => {
            if (!isMounted) return;

            // ✅ Ignore stale results if the user has clicked into a different test
            if (activeTestIdRef.current !== abTestId) return;

            setProbA((prevA) =>
              Math.abs(prevA - res.probA) >= 0.01 ? res.probA : prevA,
            );
            setProbB((prevB) =>
              Math.abs(prevB - res.probB) >= 0.01 ? res.probB : prevB,
            );

            // 🛠 Update attributes if missing
            if (res.winner && !abTestWinner) {
              setAttr("abTestWinner", res.winner);
              setAttr("abTestRunning", false);
            }
          })
          .catch(() => {
            //
          });
      };

      // Fetch immediately so "Loading..." is replaced
      fetchOnce();

      // Only poll if still running and no winner yet
      if (abTestRunning && !abTestWinner) {
        intervalId = setInterval(fetchOnce, 5000);
      }

      return () => {
        isMounted = false;
        if (intervalId) clearInterval(intervalId);
      };
    }, [abTestId, abTestRunning, abTestWinner, started]);

    // ─── Ensure stats exist for the active test (running OR finished) ─
    useEffect(() => {
      const postId = wp.data.select("core/editor").getCurrentPostId();
      if (!abTestId || !postId || !(abTestRunning || abTestWinner)) return;

      apiFetch({
        path: `/abtestkit/v1/stats?post_id=${postId}&abTestId=${abTestId}&t=${Date.now()}`,
        headers: { "X-WP-Nonce": window.abTestConfig?.nonce || "" },
      })
        .then((fresh) => {
          setStatsByTest((prev) => ({ ...prev, [abTestId]: fresh }));
        })
        .catch(() => {
          // Fail silently
        });
    }, [abTestId, abTestRunning, abTestWinner]);

    if (errorMsg) {
      controls.push(
        el(
          Notice,
          {
            status: "error",
            isDismissible: true,
            onRemove: () => setErrorMsg(""),
          },
          errorMsg,
        ),
      );
    }

    if (!block) {
      // No block selected → show list of supported blocks
      const flattenBlocks = (blocks) =>
        blocks.flatMap((b) => [
          b,
          ...(b.innerBlocks ? flattenBlocks(b.innerBlocks) : []),
        ]);

      const rootBlocks = wp.data.select("core/block-editor").getBlocks();
      const allBlocks = flattenBlocks(rootBlocks);
      const supportedTypes = Object.keys(
        window.abTestConfig?.blockConfig || {},
      );
      const supportedBlocks = allBlocks.filter((b) =>
        supportedTypes.includes(b.name),
      );

      const getPreview = (b) => {
        const attrs = b.attributes || {};
        const blockEl = document.querySelector(`[data-block="${b.clientId}"]`);

        const getText = (selector) => {
          const el = blockEl?.querySelector(selector);
          return el?.innerText?.trim() || "";
        };

        const truncate = (text, limit = 50) => {
          return text.length > limit
            ? text.slice(0, limit - 1).trim() + "…"
            : text;
        };

        switch (b.name) {
          case "core/heading":
          case "core/paragraph": {
            const text =
              attrs.content?.trim() ||
              getText(b.name === "core/heading" ? "h1,h2,h3,h4,h5,h6" : "p");
            return truncate(text || "(empty)");
          }

          case "core/image": {
            const url = attrs.url || "";
            try {
              const filename = new URL(url, window.location.origin).pathname
                .split("/")
                .pop();
              return `🖼️ ${filename}`;
            } catch {
              return "🖼️ [invalid image URL]";
            }
          }

          case "core/button": {
            const text =
              attrs.text?.trim() ||
              getText("a, .wp-block-button__link") ||
              "(no button text)";
            return el(
              "span",
              {
                style: {
                  backgroundColor: "#007cba",
                  color: "#fff",
                  padding: "2px 6px",
                  borderRadius: "4px",
                  fontSize: "12px",
                },
              },
              truncate(text),
            );
          }

          case "acf/bv-panel": {
            const title =
              attrs.myTitle?.trim() || getText(".panel__title") || "(no title)";
            return truncate(title);
          }

          default:
            return "(preview unavailable)";
        }
      };

      return el(
        PanelBody,
        { title: "Available blocks", initialOpen: true },
        el(
          Fragment,
          null,
          el(
            Notice,
            { status: "info", isDismissible: false },
            "Select a supported block to begin A/B testing:",
          ),
          supportedBlocks.length === 0
            ? el(
                "p",
                {
                  style: {
                    marginTop: "10px",
                    fontStyle: "italic",
                    color: "#666",
                  },
                },
                "No supported blocks found on this page.",
              )
            : el(
                "ul",
                {
                  style: {
                    marginTop: "10px",
                    paddingLeft: "20px",
                    listStyleType: "none",
                  },
                },
                supportedBlocks.map((b) => {
                  const preview = getPreview(b);
                  const testId = b.attributes?.abTestId || "–";

                  return el(
                    "li",
                    {
                      key: b.clientId,
                      onClick: () => {
                        wp.data
                          .dispatch("core/block-editor")
                          .selectBlock(b.clientId);
                        const elToScroll = document.querySelector(
                          `[data-block="${b.clientId}"]`,
                        );
                        if (
                          elToScroll &&
                          typeof elToScroll.scrollIntoView === "function"
                        ) {
                          elToScroll.scrollIntoView({
                            behavior: "smooth",
                            block: "center",
                          });
                        }
                      },
                      style: {
                        position: "relative",
                        marginBottom: "12px",
                        padding: "8px",
                        border: "1px solid #ddd",
                        borderRadius: "4px",
                        cursor: "pointer",
                        backgroundColor: "#f9f9f9",
                        overflow: "hidden",
                      },
                    },
                    el(
                      Fragment,
                      null,
                      // Block name
                      el(
                        "div",
                        { style: { fontWeight: "bold", color: "#007cba" } },
                        b.name,
                      ),

                      // Preview
                      el(
                        "div",
                        {
                          style: {
                            marginTop: "4px",
                            fontSize: "13px",
                            color: "#333",
                          },
                        },
                        preview,
                      ),

                      // A/B Test ID
                      el(
                        "div",
                        {
                          style: {
                            fontSize: "12px",
                            color: "#888",
                            marginTop: "2px",
                          },
                        },
                        `ID: ${testId}`,
                      ),

                      // Status badge in top-right
                      el(
                        "div",
                        {
                          style: {
                            position: "absolute",
                            top: "8px",
                            right: "10px",
                            fontSize: "12px",
                            color: "#333",
                            fontWeight: "500",
                            display: "flex",
                            alignItems: "center",
                            gap: "6px",
                          },
                        },
                        [
                          el("span", {
                            style: {
                              display: "inline-block",
                              width: "10px",
                              height: "10px",
                              borderRadius: "50%",
                              backgroundColor: b.attributes.abTestRunning
                                ? "#91d5aa"
                                : b.attributes.abTestEnabled
                                  ? "#fcd975"
                                  : "#ccc",
                            },
                          }),
                          b.attributes.abTestRunning
                            ? "Running"
                            : b.attributes.abTestEnabled
                              ? "Ready"
                              : "Idle",
                        ],
                      ),
                    ),
                  );
                }),
              ),
        ),
      );
    }

    // If user *has selected* an unsupported block, show original message
    if (!isSupported) {
      const message =
        blockName === "core/buttons"
          ? "This block can contain multiple buttons. Click an individual button inside to get started."
          : `This block type (${blockName}) is not yet supported for A/B testing.`;

      return el(
        PanelBody,
        { title: "A/B Test (Not Supported)", initialOpen: true },
        el(Notice, { status: "info", isDismissible: false }, message),
      );
    }

    if (abTestWinner === "A" || abTestWinner === "B") {
      controls.push(
        el(
          Notice,
          { status: "success", isDismissible: false },
          `🎉 Winner: Variant '${abTestWinner}'`,
        ),
      );
    }

    if (abTestWinner === "inconclusive") {
      controls.push(
        el(
          Notice,
          {
            status: "info",
            isDismissible: false,
          },
          "⚠️ This test ended without a clear winner. You can tweak the variants and try again.",
        ),
      );
    }

    // Greyed-out overlay + See results button
    if (abTestRunning && !showResults && abTestWinner) {
      controls.push(
        el(
          Fragment,
          {},
          // translucent overlay
          el("div", {
            style: {
              position: "absolute",
              top: 0,
              left: 0,
              right: 0,
              bottom: 0,
              background: "rgba(255,255,255,0.7)",
              zIndex: 10,
            },
          }),

          // See results button
          el(
            "div",
            {
              style: {
                position: "absolute",
                top: "8px",
                right: "8px",
                zIndex: 11,
              },
            },
            el(
              Button,
              {
                isPrimary: true,
                onClick: () => {
                  const postId = wp.data
                    .select("core/editor")
                    .getCurrentPostId();
                  evaluate().then((res) => {
                    if (!res) return; // safety
                    apiFetch({
                      path: `/abtestkit/v1/stats?post_id=${postId}&abTestId=${abTestId}&t=${Date.now()}`,
                      headers: {
                        "X-WP-Nonce": window.abTestConfig?.nonce || "",
                      },
                    }).then((freshStats) => {
                      setStatsByTest((prev) => ({
                        ...prev,
                        [abTestId]: freshStats,
                      }));
                    });

                    const { probA, probB, ciLower, ciUpper, winner, message } =
                      res || {};
                    setProbA(probA);
                    setProbB(probB);
                    setShowResults(true);
                    setAttr("abTestResultsViewed", true);
                  });
                },
              },
              "See results",
            ),
          ),
        ),
      );
    }

    // enable toggle
    controls.push(
      el(ToggleControl, {
        label: "Enable A/B Test",
        checked: abTestEnabled,
        onChange: handleEnableToggle,
        disabled: locked,
      }),
    );

    // ───────────────── Group Test (UI) ─────────────────
    // Build the panel but defer pushing
    let groupPanel = null;

    // Only show once the A/B test is enabled
    if (abTestEnabled) {
      const currentKey = (attributes.abGroupKey || "").toLowerCase();
      const currentMembers = membersMap[currentKey] || [];

      // Build dropdown options. Include the count.
      const groupOptions = existingKeys.map((k) => {
        const count = (membersMap[k] || []).length;
        const suffix = count
          ? ` (${count} ${count === 1 ? "block" : "blocks"})`
          : "";
        return { label: `${displayCode(k)}${suffix}`, value: k };
      });

      function createNewGroup() {
        const newKey = makeUniqueCode(existingKeys); // e.g., 'qz137'
        setAttr("abSync", true);
        setAttr("abGroupKey", newKey);
      }

      function handleGroupChange(val) {
        if (val === "__new__") return createNewGroup();
        if (!val) {
          setAttr("abSync", false);
          setAttr("abGroupKey", "");
          return;
        }
        setAttr("abSync", true);
        setAttr("abGroupKey", String(val).toLowerCase());
      }

      groupPanel = el(
        PanelBody,
        {
          title: "Group Test",
          initialOpen: !!currentKey, // collapsed by default unless a group is selected
        },
        // Dropdown
        el(wp.components.SelectControl, {
          // "Group  (?)" label with Tooltip
          label: el(
            "span",
            {
              style: {
                display: "inline-flex",
                alignItems: "center",
                gap: "6px",
              },
            },
            [
              "Group",
              el(
                Tooltip,
                {
                  text: "Groups allow you to test different blocks together, showing A or B in uniform across all blocks in the group",
                },
                el(Dashicon, {
                  icon: "editor-help",
                  style: { cursor: "help" },
                }),
              ),
            ],
          ),
          value: currentKey,
          options: [
            { label: "— None —", value: "" }, // clears group membership
            ...groupOptions, // e.g., "QM609 (3 blocks)"
            { label: "Create new Group +", value: "__new__" },
          ],
          onChange: handleGroupChange,
          disabled: locked,
        }),
        // Inline code chip with tooltip of members for the currently selected group
        currentKey &&
          el(
            "div",
            {
              style: {
                marginTop: "6px",
                display: "flex",
                alignItems: "center",
                gap: "8px",
              },
            },
            el(
              wp.components.Tooltip,
              {
                text: membersTooltip(currentMembers),
                position: "middle left",
              },
              el(
                "span",
                {
                  style: {
                    fontFamily:
                      'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace',
                    fontSize: "12px",
                    background:
                      "var(--wp-admin-theme-color-darker-10, #f0f0f0)",
                    padding: "2px 6px",
                    borderRadius: "6px",
                    display: "inline-flex",
                    alignItems: "center",
                    cursor: "help",
                  },
                },
                `${displayCode(currentKey)}`,
              ),
            ),
            el(
              wp.components.Button,
              {
                isDestructive: true,
                variant: "secondary",
                onClick: () => {
                  setAttr("abSync", false);
                  setAttr("abGroupKey", "");
                },
                disabled: locked,
              },
              "Remove from group",
            ),
          ),
      );
    }

    controls.push(
      el(
        "div",
        {
          style: {
            margin: "10px 0",
            padding: "8px 12px",
            background: "#f0f0f0",
            borderRadius: "4px",
            fontSize: "13px",
            color: "#333",
            lineHeight: "1.4",
          },
        },
        [
          el("div", { style: { fontWeight: "bold" } }, blockName),
          el("div", {}, `ID: ${abTestId}`),
        ],
      ),
    );

    if (abTestEnabled) {
      // preview selector
      controls.push(
        el(
          "div",
          {
            style: {
              display: "flex",
              alignItems: "center",
              justifyContent: "space-between",
              gap: "12px",
              padding: "8px 0", 
              marginTop: "10px",
            },
          },
          [
            // Left side: heading + radios on ONE line
            el(
              "div",
              { style: { display: "flex", alignItems: "center", gap: "10px" } },
              [
                el(
                  "div",
                  {
                    style: {
                      fontSize: "12px",
                      fontWeight: 600,
                      textTransform: "uppercase",
                      letterSpacing: "0.02em",
                    },
                  },
                  "Preview Variant",
                ),
                el(RadioControl, {
                  label: "", // keep radios on the same line as the heading
                  selected: preview,
                  options: [
                    { label: "A", value: "A" },
                    { label: "B", value: "B" },
                  ],
                  onChange: (val) => setPreview(val),
                }),
              ],
            ),

            // Right side: center-aligned action
            el(
              Button,
              {
                isSecondary: true,
                onClick: () => {
                  const postId = wp.data
                    .select("core/editor")
                    .getCurrentPostId();
                  const variantToPreview = preview;

                  const flatten = (blocks) =>
                    blocks.flatMap((b) => [
                      b,
                      ...(b.innerBlocks ? flatten(b.innerBlocks) : []),
                    ]);

                  const rootBlocks = wp.data
                    .select("core/block-editor")
                    .getBlocks();
                  const allBlocks = flatten(rootBlocks);

                  const abPreviewParam = allBlocks
                    .filter(
                      (b) =>
                        b.attributes?.abTestEnabled && b.attributes?.abTestId,
                    )
                    .map((b) => {
                      const id = b.attributes.abTestId;
                      const isThisBlock = b.clientId === clientId;
                      const variant = isThisBlock ? variantToPreview : "A";
                      const safeId =
                        typeof id === "string"
                          ? id.replace(/[^a-zA-Z0-9_-]/g, "")
                          : "";
                      const safeVar =
                        variant === "A" || variant === "B" ? variant : "A";
                      return `${safeId}:${safeVar}`;
                    })
                    .join(",");

                  wp.data
                    .dispatch("core/editor")
                    .savePost()
                    .then(() => {
                      const base = new URL(
                        `/?p=${postId}&preview=true`,
                        window.location.origin,
                      );
                      base.searchParams.set("ab_preview", abPreviewParam);
                      if (attributes.abSync && attributes.abGroupKey) {
                        base.searchParams.set(
                          `abgroup__${attributes.abGroupKey}`,
                          variantToPreview,
                        );
                      }
                      window.open(base.toString(), "_blank");
                    });
                },
              },
              "Preview in New Tab",
            ),
          ],
        ),
      );

      if (
        isSupported &&
        ["core/paragraph", "core/heading", "core/image"].includes(blockName)
      ) {
        // Flatten all nested blocks
        function flattenAllBlocks(blocks) {
          return blocks.flatMap((block) => [
            block,
            ...(block.innerBlocks ? flattenAllBlocks(block.innerBlocks) : []),
          ]);
        }

        const rootBlocks = wp.data.select("core/block-editor").getBlocks();
        const allBlocks = flattenAllBlocks(rootBlocks);

        // ───────────────── Conversion goal (dropdown + conditional panels) ─────────────────

        // 1) Available CTA *button tests* (A/B-enabled buttons on this page)
        const potentialButtonSources = allBlocks
          .filter(
            (b) =>
              b.clientId !== clientId &&
              b.attributes?.abTestId &&
              b.name === "core/button",
          )
          .map((b) => ({
            clientId: b.clientId,
            abTestId: b.attributes.abTestId,
            blockName: b.name,
          }));

        // 2) “Other buttons” and links (anything styled as button or a[href]) from the editor canvas
        function getEditorDoc() {
          const ifr = document.querySelector('iframe[name="editor-canvas"]');
          if (ifr?.contentDocument) return ifr.contentDocument;
          const alt = document.querySelector(
            ".block-editor-iframe__container iframe",
          );
          if (alt?.contentDocument) return alt.contentDocument;
          return document;
        }

        const edoc = getEditorDoc();
        // limit to this post’s content area
        const postRoot =
          edoc.querySelector(".is-root-container") ||
          edoc.querySelector(".editor-styles-wrapper") ||
          edoc;

        // Links on this page (http/https/mailto/tel), then de-dupe by our *normalized* href
        const linkNodes = Array.from(
          postRoot.querySelectorAll("a[href]"),
        ).filter((a) => {
          const h = (a.getAttribute("href") || "").trim();
          if (!h || h === "#" || /^javascript:/i.test(h)) return false;
          // Defer protocol rules to the normaliser — if it returns "", we’ll drop it later
          return true;
        });

        // De-dupe by normalized href so saved values match frontend click normalization
        const uniqueLinks = Array.from(
          new Map(
            linkNodes
              .map((a) => {
                const norm = abtestkitNormalizeUrlSidebar(
                  a.getAttribute("href") || "",
                );
                return [norm, a];
              })
              .filter(([norm]) => !!norm),
          ).entries(),
        ).map(([absHref, el]) => ({ absHref, node: el }));

        // “Other buttons”: anchors styled as buttons or <button> elements
        const buttonLike = Array.from(
          postRoot.querySelectorAll(
            'a.wp-block-button__link, a.wp-element-button, a.button, a[class*="btn"], button, [role="button"]',
          ),
        );
        const uniqueButtonLike = Array.from(
          new Map(
            buttonLike
              .filter((el) => el !== null)
              .map((el, i) => {
                // prefer href if anchor, else a stable-ish label
                const href =
                  el.tagName.toLowerCase() === "a"
                    ? new URL(
                        el.getAttribute("href") || "#",
                        window.location.origin,
                      ).toString()
                    : "";
                const label =
                  (el.textContent || "").trim() ||
                  el.getAttribute("aria-label") ||
                  `Button ${i + 1}`;
                const key = href || `btn:${label}:${i}`;
                return [key, { el, href, label, key }];
              }),
          ).values(),
        );

        // Current settings (prefer what's persisted inside this test's bucket; fall back to top-level)
        const storedForThisTest =
          (attributes.abTestVariants && attributes.abTestVariants[abTestId]) ||
          {};

        const goal =
          storedForThisTest.conversionGoalType ||
          attributes.conversionGoalType ||
          "button";

        const selectedLinks =
          Array.isArray(attributes.conversionLinks) &&
          attributes.conversionLinks.length
            ? attributes.conversionLinks
            : Array.isArray(storedForThisTest.conversionLinks)
              ? storedForThisTest.conversionLinks
              : [];

        const selectedFromButtons =
          Array.isArray(attributes.conversionFrom) &&
          attributes.conversionFrom.length
            ? attributes.conversionFrom
            : Array.isArray(storedForThisTest.conversionFrom)
              ? storedForThisTest.conversionFrom
              : [];

        // Normalised set for comparisons in the UI (matches frontend normalisation)
        const selectedLinksNormalized = selectedLinks
          .map((u) => abtestkitNormalizeUrlSidebar(u))
          .filter(Boolean);

        // Dropdown
        controls.push(
          el("div", { style: { marginTop: "10px" } }, [
            el("strong", null, "Conversion goal"),
            el(wp.components.SelectControl, {
              value: goal,
              options: [
                { label: "Button click", value: "button" },
                { label: "Link click", value: "link" },
                { label: "Form submission", value: "form" },
              ],
              onChange: (val) => {
                // Persist in both places: top-level for UI, nested for actual storage
                const all = attributes.abTestVariants || {};
                const cur = all[abTestId] || {};
                const next = { ...cur, conversionGoalType: val }; // also supports 'form'

                wp.data
                  .dispatch("core/block-editor")
                  .updateBlockAttributes(clientId, {
                    conversionGoalType: val,
                    abTestVariants: { ...all, [abTestId]: next },
                  });
              },

              disabled: locked,
            }),

            // ── When Button click: show (A) A/B buttons and (B) "other buttons (links styled as buttons)"
            goal === "button" &&
              el(
                "div",
                { style: { marginTop: "8px" } },
                [
                  // ── Unified: Buttons on this page (A/B + other button-like links together)
                  el(
                    "div",
                    { style: { fontWeight: 600, marginBottom: "6px" } },
                    "Buttons on this page", // CHANGED: removed trailing comma
                  ),

                  (() => {
                    // Build one combined array with the right handlers for each item type.

                    // A/B-enabled Gutenberg Button blocks
                    const abButtonItems = potentialButtonSources.map((b) => ({
                      type: "ab",
                      key: `ab:${b.abTestId}`,
                      label: getButtonTextByClientId(b.clientId),
                      ping: () => pingBlockWithoutSelecting(b.clientId),
                      isChecked: selectedFromButtons.includes(b.abTestId),
                      onToggle: (checked) => {
                        const prev = attributes.conversionFrom || [];
                        const next = checked
                          ? [...prev, b.abTestId]
                          : prev.filter((id) => id !== b.abTestId);

                        wp.data
                          .dispatch("core/block-editor")
                          .updateBlockAttributes(clientId, {
                            conversionFrom: next,
                          });
                      },
                    }));

                    // “Other” button-like anchors (with href) found in the editor canvas
                    const otherButtonItems = uniqueButtonLike
                      .filter((it) => !!it.href)
                      .slice(0, 80)
                      .map((it) => ({
                        type: "href",
                        key: `href:${it.href}`,
                        label: it.label || it.href,
                        ping: () => pingLinkNode(it.el),
                        isChecked: selectedLinksNormalized.includes(
                          abtestkitNormalizeUrlSidebar(it.href),
                        ),
                        onToggle: (checked) => {
                          const prev = Array.isArray(attributes.conversionLinks)
                            ? attributes.conversionLinks
                            : [];
                          const norm = abtestkitNormalizeUrlSidebar(it.href);
                          if (!norm) return;

                          const next = checked
                            ? Array.from(new Set([...prev, norm]))
                            : prev.filter((h) => h !== norm);

                          const all = attributes.abTestVariants || {};
                          const cur = all[abTestId] || {};
                          const ncur = { ...cur, conversionLinks: next };

                          wp.data
                            .dispatch("core/block-editor")
                            .updateBlockAttributes(clientId, {
                              conversionLinks: next,
                              abTestVariants: { ...all, [abTestId]: ncur },
                            });
                        },

                        title: it.href,
                      }));

                    const combinedButtons = [
                      ...abButtonItems,
                      ...otherButtonItems,
                    ];

                    if (combinedButtons.length === 0) {
                      return el(
                        "div",
                        {
                          style: {
                            fontStyle: "italic",
                            fontSize: "13px",
                            color: "#888",
                          },
                        },
                        "No buttons found on this page.", // CHANGED: removed trailing comma
                      );
                    }

                    return combinedButtons.map((item) =>
                      el(wp.components.ToggleControl, {
                        key: item.key,
                        label: el(
                          "button",
                          {
                            type: "button",
                            className: "abtestkit-linklike",
                            title: item.title || "", // shows full href on hover for non-AB items
                            onClick: (e) => {
                              e.preventDefault();
                              e.stopPropagation();
                              item.ping(); // smooth scroll + highlight (no selection change)
                            },
                            onMouseDown: (e) => {
                              e.preventDefault();
                              e.stopPropagation(); // don't toggle the checkbox when clicking label
                            },
                          },
                          item.label,
                        ), // OK: comma here separates object properties (label → checked)
                        checked: item.isChecked,
                        onChange: (checked) => item.onToggle(checked),
                        disabled: locked,
                      }),
                    ); // CHANGED: removed stray comma after callback
                  })(),
                ].filter(Boolean),
              ),

            // ── When Link click: show list of links on page
            goal === "link" &&
              el("div", { style: { marginTop: "8px" } }, [
                el(
                  "div",
                  { style: { fontWeight: 600, marginBottom: "6px" } },
                  "Links on this page", // CHANGED: removed trailing comma
                ),
                uniqueLinks.length === 0
                  ? el(
                      "div",
                      {
                        style: {
                          fontStyle: "italic",
                          fontSize: "13px",
                          color: "#888",
                        },
                      },
                      "No links found on this page.", // CHANGED: removed trailing comma
                    )
                  : uniqueLinks.slice(0, 120).map(({ absHref, node }) =>
                      el(wp.components.ToggleControl, {
                        key: `lnk-${absHref}`,
                        label: el(
                          "button",
                          {
                            type: "button",
                            className: "abtestkit-linklike",
                            title: absHref, // show full URL on hover
                            onClick: (e) => {
                              e.preventDefault();
                              e.stopPropagation(); // don’t toggle the checkbox
                              pingLinkNode(node); // smooth scroll + highlight, no selection change
                            },
                            onMouseDown: (e) => {
                              e.preventDefault();
                              e.stopPropagation();
                            },
                          },
                          (() => {
                            const getText = () => {
                              if (!node) return "";
                              const t1 = (node.innerText || "").trim();
                              if (t1) return t1;
                              const t2 = (node.textContent || "").trim();
                              if (t2) return t2;
                              const t3 = (
                                node.getAttribute("aria-label") ||
                                node.getAttribute("title") ||
                                ""
                              ).trim();
                              return t3;
                            };
                            let text = getText();
                            if (!text) {
                              try {
                                const u = new URL(absHref);
                                const last =
                                  u.pathname.split("/").filter(Boolean).pop() ||
                                  u.hostname;
                                text = decodeURIComponent(
                                  (last || "").replace(/[-_]/g, " "),
                                ).trim();
                              } catch (_) {
                                text = absHref;
                              }
                            }
                            return text || absHref;
                          })(),
                        ), // OK: comma here separates object properties (label → checked)
                        checked: selectedLinksNormalized.includes(
                          abtestkitNormalizeUrlSidebar(absHref),
                        ),
                        onChange: (checked) => {
                          const prev = Array.isArray(attributes.conversionLinks)
                            ? attributes.conversionLinks
                            : [];
                          const norm = abtestkitNormalizeUrlSidebar(absHref);
                          if (!norm) return;

                          const next = checked
                            ? Array.from(new Set([...prev, norm]))
                            : prev.filter((h) => h !== norm);

                          const all = attributes.abTestVariants || {};
                          const cur = all[abTestId] || {};
                          const ncur = { ...cur, conversionLinks: next };

                          wp.data
                            .dispatch("core/block-editor")
                            .updateBlockAttributes(clientId, {
                              conversionLinks: next,
                              abTestVariants: { ...all, [abTestId]: ncur },
                            });
                        },

                        disabled: locked,
                      }),
                    ),
              ]),

            // ── When Form submission: nothing else to show (we’ll treat *any* form submit on page as conversion)
            goal === "form" &&
              el(
                "div",
                {
                  style: { marginTop: "8px", fontSize: "13px", color: "#555" },
                },
                "Any form submission on this page will count as a conversion.",
              ),
          ]),
        );
      }

      if (
        abTestEnabled &&
        ["core/paragraph", "core/heading", "core/image"].includes(blockName)
      ) {
        const hasAnyConnections = outgoingConnections.length > 0;

        if (hasAnyConnections) {
          controls.push(
            el(
              "div",
              {
                style: {
                  marginTop: "12px",
                  padding: "8px",
                  background: "#eef6ff",
                  borderRadius: "4px",
                },
              },
              [
                el(
                  "strong",
                  {
                    style: { display: "block", marginBottom: "4px" },
                  },
                  "🔗 Connected Blocks",
                ),

                // Only show the destination list (Sends to)
                el("div", null, [
                  el(
                    "div",
                    {
                      style: {
                        fontSize: "13px",
                        marginBottom: "4px",
                        color: "#444",
                      },
                    },
                    "🡇 Sends to:",
                  ),
                  ...outgoingConnections.map((b) =>
                    el(
                      "div",
                      {
                        key: b.clientId,
                        style: {
                          fontSize: "13px",
                          marginLeft: "8px",
                          color: "#666",
                        },
                      },
                      `${b.name} (${b.attributes.abTestId})`,
                    ),
                  ),
                ]),
              ],
            ),
          );
        }
      }

      if (
        abTestEnabled &&
        blockName === "core/button" &&
        incomingConnections.length > 0
      ) {
        controls.push(
          el(
            "div",
            {
              style: {
                marginTop: "12px",
                padding: "8px",
                background: "#eef6ff",
                borderRadius: "4px",
              },
            },
            [
              el(
                "strong",
                {
                  style: { display: "block", marginBottom: "4px" },
                },
                "🔗 Connected Blocks",
              ),

              el("div", null, [
                el(
                  "div",
                  {
                    style: {
                      fontSize: "13px",
                      marginBottom: "4px",
                      color: "#444",
                    },
                  },
                  "🔁 Connected with:",
                ),
                ...incomingConnections.map((b) =>
                  el(
                    "div",
                    {
                      key: b.clientId,
                      style: {
                        fontSize: "13px",
                        marginLeft: "8px",
                        color: "#666",
                      },
                    },
                    `${b.name} (${b.attributes.abTestId})`,
                  ),
                ),
              ]),
            ],
          ),
        );
      }

      // ─── Divider before Variant A (separates Preview from inputs) ────────
      controls.push(
        el("hr", {
          key: "ab-variant-divider-top",
          style: {
            width: "90%",
            border: 0,
            borderTop: "1px solid #c7c7c7",
            margin: "16px auto",
          },
        }),
      );

      // ─── Variant A ───────────────────────────────────────────────
      const safeVariantA =
        typeof safeCurrent.A === "object" && safeCurrent.A !== null
          ? safeCurrent.A
          : {};
      const safeVariantB =
        typeof safeCurrent.B === "object" && safeCurrent.B !== null
          ? safeCurrent.B
          : {};

      // Instead of pushing an array (which can break React), add one-by-one:
      fields.forEach((f) => {
        const value = safeVariantA?.[f.name] || "";

        if (blockName === "core/image" && f.name === "url") {
          const preview = value
            ? el("img", {
                src: value,
                style: {
                  maxWidth: "100%",
                  maxHeight: "150px",
                  borderRadius: "4px",
                  boxShadow: "0 0 2px rgba(0,0,0,0.2)",
                },
              })
            : null;

          controls.push(
            el(
              "div",
              {
                key: `A-${f.name}`,
                style: {
                  display: "flex",
                  flexDirection: "column",
                  alignItems: "flex-start",
                  gap: "8px",
                  marginBottom: "16px",
                },
              },
              [
                preview,
                el(
                  MediaUploadCheck,
                  {},
                  el(MediaUpload, {
                    onSelect: (media) =>
                      setSpecificVariantField("A", f.name, media.url),
                    allowedTypes: ["image"],
                    value,
                    render: ({ open }) =>
                      el(
                        Button,
                        {
                          isSecondary: true,
                          onClick: open,
                          disabled: locked,
                        },
                        "Select Image A",
                      ),
                  }),
                ),
              ],
            ),
          );
        } else {
          const isLongText = /^(content|text|html|myTitle|myContent)$/i.test(
            f.name,
          );
          controls.push(
            isLongText
              ? el(TextareaControl, {
                  key: `A-${f.name}`,
                  className: "abtest-textarea", // hook our CSS
                  label: `${f.label} A`,
                  value,
                  rows: 1, // initial height
                  onChange: (val) => setSpecificVariantField("A", f.name, val),
                  disabled: locked,
                })
              : el(TextControl, {
                  key: `A-${f.name}`,
                  label: `${f.label} A`,
                  value,
                  onChange: (val) => setSpecificVariantField("A", f.name, val),
                  disabled: locked,
                }),
          );
        }
      });

      // ─── Divider between Variant A and Variant B ─────────────────
      controls.push(
        el("hr", {
          key: "ab-variant-divider",
          style: {
            width: "90%",
            border: 0,
            borderTop: "1px solid #c7c7c7",
            margin: "16px auto",
          },
        }),
      );

      // ─── Variant B ───────────────────────────────────────────────
      fields.forEach((f) => {
        const value = safeVariantB?.[f.name] || "";

        if (blockName === "core/image" && f.name === "url") {
          const preview = value
            ? el("img", {
                src: value,
                style: {
                  maxWidth: "100%",
                  maxHeight: "150px",
                  borderRadius: "4px",
                  boxShadow: "0 0 2px rgba(0,0,0,0.2)",
                },
              })
            : null;

          controls.push(
            el(
              "div",
              {
                key: `B-${f.name}`,
                style: {
                  display: "flex",
                  flexDirection: "column",
                  alignItems: "flex-start",
                  gap: "8px",
                  marginBottom: "16px",
                },
              },
              [
                preview,
                el(
                  MediaUploadCheck,
                  {},
                  el(MediaUpload, {
                    onSelect: (media) =>
                      setSpecificVariantField("B", f.name, media.url),
                    allowedTypes: ["image"],
                    value,
                    render: ({ open }) =>
                      el(
                        Button,
                        {
                          isSecondary: true,
                          onClick: open,
                          disabled: locked,
                        },
                        "Select Image B",
                      ),
                  }),
                ),
              ],
            ),
          );
        } else {
          const isLongText = /^(content|text|html|myTitle|myContent)$/i.test(
            f.name,
          );
          controls.push(
            isLongText
              ? el(TextareaControl, {
                  key: `B-${f.name}`,
                  className: "abtest-textarea", // hook our CSS
                  label: `${f.label} B`,
                  value,
                  rows: 1, // initial height
                  onChange: (val) => setSpecificVariantField("B", f.name, val),
                  disabled: locked,
                })
              : el(TextControl, {
                  key: `B-${f.name}`,
                  label: `${f.label} B`,
                  value,
                  onChange: (val) => setSpecificVariantField("B", f.name, val),
                  disabled: locked,
                }),
          );
        }
      });

      if (abTestWinner && showResults) {
        const stats = statsByTest?.[abTestId] || {};
        const statsA = stats.A || { impressions: 0, clicks: 0 };
        const statsB = stats.B || { impressions: 0, clicks: 0 };
        const hasStats = !!statsByTest?.[abTestId];
        let safeProbA = typeof probA === "number" && !isNaN(probA) ? probA : 0;
        let safeProbB = typeof probB === "number" && !isNaN(probB) ? probB : 0;

        controls.push(
          el("div", { style: { marginTop: "10px" } }, [
            el("strong", null, "📊 Final Results:"),
            el(
              "div",
              null,
              hasStats
                ? `A: ${statsA.impressions} impressions, ${statsA.clicks} clicks`
                : "Loading…",
            ),
            el(
              "div",
              null,
              hasStats
                ? `B: ${statsB.impressions} impressions, ${statsB.clicks} clicks`
                : "",
            ),
            el("div", null, `P(A > B): ${(safeProbA * 100).toFixed(2)}%`),
            el("div", null, `P(B > A): ${(safeProbB * 100).toFixed(2)}%`),
          ]),
        );
      }

      const allFieldsFilled = (variant) =>
        fields.every((f) => (variant?.[f.name] || "").trim() !== "");

      // Require a click source for this block type (outside groups)
      const requiresClickSource = [
        "core/paragraph",
        "core/heading",
        "core/image",
      ].includes(blockName);
      const goal = attributes.conversionGoalType || "button";

      // “do we have a source?” depends on the goal:
      //  - button: either linked A/B button(s) OR selected button-like links
      //  - link  : selected links
      //  - form  : no extra selection needed
      const hasClickSource =
        goal === "button"
          ? (attributes.conversionFrom || []).length > 0 ||
            (attributes.conversionLinks || []).length > 0
          : goal === "link"
            ? (attributes.conversionLinks || []).length > 0
            : goal === "form";

      // Group context
      const inGroup = !!(attributes.abSync && attributes.abGroupKey);
      const groupKey = (attributes.abGroupKey || "").toLowerCase();
      const groupMembers = inGroup
        ? allBlocks.filter(
            (b) =>
              b?.attributes?.abSync &&
              String(b.attributes?.abGroupKey || "").toLowerCase() ===
                groupKey &&
              b?.attributes?.abTestId,
          )
        : [];

      // Count either: (a) any button in the group OR (b) any text/image block with a click source
      const groupHasButton = inGroup
        ? groupMembers.some((b) => b.name === "core/button")
        : false;

      const groupHasClickSource = inGroup
        ? groupMembers.some(
            (b) =>
              ["core/paragraph", "core/heading", "core/image"].includes(
                b.name,
              ) && (b.attributes?.conversionFrom || []).length > 0,
          )
        : false;

      // Group-level CTA presence (button OR click-source on any member)
      const groupHasCTA = groupHasButton || groupHasClickSource;

      const variantA =
        typeof safeCurrent.A === "object" && safeCurrent.A !== null
          ? safeCurrent.A
          : {};
      const variantB =
        typeof safeCurrent.B === "object" && safeCurrent.B !== null
          ? safeCurrent.B
          : {};

      // ✅ New rule:
      // - If NOT in a group: keep the old per-block CTA rule.
      // - If IN a group: only require the group to have a CTA (button or one member with click source).
      const canGo =
        allFieldsFilled(variantA) &&
        allFieldsFilled(variantB) &&
        ((!inGroup && (!requiresClickSource || hasClickSource)) ||
          (inGroup && groupHasCTA));

      // Tooltip reason
      const goTooltip = (() => {
        const missingFields =
          !allFieldsFilled(variantA) || !allFieldsFilled(variantB);
        const missingClick = requiresClickSource && !hasClickSource;
        if (missingFields) return "Fill in all variant fields to begin test";

        if (!inGroup) {
          if (missingClick) return "Select at least one Source of Clicks";
          return "";
        }

        if (!groupHasCTA)
          return "Your group needs a CTA (a button, or one block with a click source)";
        return "";
      })();

      // Place Group panel
      if (groupPanel) {
        controls.push(groupPanel);
      }

      if (!locked) {
        controls.push(
          el(
            Tooltip,
            {
              text: goTooltip,
            },
            el(
              "span",
              { style: { display: "inline-block" } },
              el(
                Button,
                {
                  isPrimary: true,
                  disabled: !canGo,
                  style: { marginTop: "10px" },
                  onClick: () => {
                    if (clientId) {
                      wp.data
                        .dispatch("core/block-editor")
                        .updateBlockAttributes(clientId, {
                          abTestVariants: {
                            ...(attributes.abTestVariants || {}),
                            [abTestId]: {
                              ...(attributes.abTestVariants?.[abTestId] || {}),
                              locked: false,
                            },
                          },
                        });
                      setAttr("abTestStartedAt", Date.now());
                    }

                    setTimeout(() => {
                      goAndSave();
                    }, 100);
                  },
                },
                "Go!",
              ),
            ),
          ),
        );
      }

      // Reset button (clears stats but keeps test & variants)
      if (locked) {
        controls.push(
          el(
            Button,
            {
              isSecondary: true,
              style: { marginTop: "10px" },
              onClick: () => {
                const confirmed = window.confirm(
                  "Are you sure you want to unlock this A/B Test? This will clear all stats.",
                );
                if (!confirmed) return;

                const postId = wp.data.select("core/editor").getCurrentPostId();

                apiFetch({
                  path: `/abtestkit/v1/reset`,
                  method: "POST",
                  body: JSON.stringify({ post_id: postId, abTestId }),
                  headers: {
                    "Content-Type": "application/json",
                    "X-WP-Nonce": window.abTestConfig?.nonce || "",
                  },
                }).then(() => {
                  try {
                    window.localStorage.removeItem(
                      `abTestRunning_${postId}_${clientId}`,
                    );
                  } catch (e) {}

                  // Clear control state
                  setAttr("abTestLastUnlocked", Date.now());
                  setAttr("abTestRunning", false);
                  setAttr("abTestWinner", "");
                  setAbTestWinnerState("");

                  // Also ensure any per-test flag is cleared
                  const _all = attributes.abTestVariants || {};
                  const _this = _all[abTestId] || {};
                  setAttr("abTestVariants", {
                    ..._all,
                    [abTestId]: {
                      ..._this,
                      locked: false,
                    },
                  });

                  setTimeout(() => {
                    const isDirty = wp.data
                      .select("core/editor")
                      .isEditedPostDirty();
                    wp.data.dispatch("core/editor").savePost();
                  }, 300);

                  // Wipe visible stats immediately
                  setStatsByTest((prev) => ({
                    ...prev,
                    [abTestId]: {
                      A: { impressions: 0, clicks: 0 },
                      B: { impressions: 0, clicks: 0 },
                    },
                  }));

                  // Force-refresh stats from backend
                  setTimeout(() => {
                    apiFetch({
                      path: `/abtestkit/v1/stats?post_id=${postId}&abTestId=${abTestId}&t=${Date.now()}`,
                      headers: {
                        "X-WP-Nonce": window.abTestConfig?.nonce || "",
                      },
                    }).then((fresh) => {
                      setStatsByTest((prev) => ({
                        ...prev,
                        [abTestId]: fresh,
                      }));
                    });
                  }, 300);
                });
              },
            },
            "🔒 Unlock Test",
          ),
        );
      }

      // Stats (show while running OR after a winner/inconclusive is declared)
      if (abTestRunning || abTestWinner) {
        const isLoading = !statsByTest.hasOwnProperty(abTestId);
        const statsDisplay = isLoading
          ? el("div", null, "Loading…")
          : [
              el(
                "div",
                null,
                `A: ${currentStats.A.impressions} impressions, ${currentStats.A.clicks} clicks`,
              ),
              el(
                "div",
                null,
                `B: ${currentStats.B.impressions} impressions, ${currentStats.B.clicks} clicks`,
              ),
            ];

        controls.push(
          el("div", { style: { marginTop: "10px" } }, [
            el("strong", null, "Stats:"),
            ...[].concat(statsDisplay),
          ]),
        );
      }

      const minimumImpressions = 50;
      const totalImpressions = impressionsA + impressionsB;
      const hasValidProbs =
        typeof probA === "number" && typeof probB === "number";
      const percentA = hasValidProbs ? Math.round(probA * 100) : 0;
      const percentB = hasValidProbs ? Math.round(probB * 100) : 0;
      const remainingImpressions = Math.max(
        0,
        minimumImpressions - totalImpressions,
      );
      const hasClicks = clicksA + clicksB > 0;

      // NEW: gate “Finish / Apply Winner” strictly by thresholds
      const confidenceThreshold = 0.95;
      const thresholdsMet =
        totalImpressions >= minimumImpressions &&
        hasValidProbs &&
        hasClicks &&
        (probA >= confidenceThreshold || probB >= confidenceThreshold);

      // ✅ Only show "Current Confidence" section if the test is unlocked (running) OR has data
      if (abTestRunning) {
        let confidenceContent = el(
          "div",
          {
            style: {
              height: "20px",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              fontSize: "12px",
              color: "#888",
              background: "#f9f9f9",
              borderRadius: "6px",
            },
          },
          "Loading...",
        );

        // 🟡 No impressions, no clicks yet
        if (totalImpressions === 0 && clicksA + clicksB === 0) {
          confidenceContent = el(
            "div",
            {
              style: {
                padding: "8px",
                fontSize: "12px",
                background: "#fff8e1",
                border: "1px solid #ffe58f",
                borderRadius: "6px",
                color: "#664d03",
              },
            },
            "Waiting for data…",
          );

          // 🟠 Impressions exist but no clicks yet
        } else if (totalImpressions > 0 && clicksA + clicksB === 0) {
          confidenceContent = el(
            "div",
            {
              style: {
                padding: "8px",
                fontSize: "12px",
                background: "#fff8e1",
                border: "1px solid #ffe58f",
                borderRadius: "6px",
                color: "#664d03",
              },
            },
            "Waiting for more data — no clicks recorded.",
          );

          // ✅ Show bar, percentages, and confidence messages
        } else if (hasValidProbs && hasClicks) {
          confidenceContent = el(Fragment, null, [
            el(
              "div",
              {
                style: {
                  position: "relative",
                  height: "20px",
                  borderRadius: "6px",
                  overflow: "hidden",
                },
              },
              [
                el(
                  "div",
                  {
                    style: {
                      display: "flex",
                      width: "100%",
                      height: "100%",
                    },
                  },
                  [
                    el("div", {
                      style: {
                        width: `${percentA}%`,
                        backgroundColor: "#cfe2f3",
                        transition: "width 0.5s ease",
                      },
                    }),
                    el("div", {
                      style: {
                        width: `${percentB}%`,
                        backgroundColor: "#f4cccc",
                        transition: "width 0.5s ease",
                      },
                    }),
                  ],
                ),
                el("div", {
                  style: {
                    position: "absolute",
                    top: 0,
                    bottom: 0,
                    left: `${percentA}%`,
                    width: "2px",
                    background: "#333",
                    zIndex: 2,
                    transition: "left 0.5s ease",
                  },
                }),
              ],
            ),

            // Percentages
            el(
              "div",
              {
                style: {
                  display: "flex",
                  justifyContent: "space-between",
                  marginTop: "4px",
                  fontSize: "12px",
                  fontWeight: "bold",
                },
              },
              [
                el("div", { style: { textAlign: "left" } }, [
                  el("div", null, "Variant A"),
                  el(
                    "div",
                    { style: { fontWeight: "normal", color: "#555" } },
                    `${percentA}%`,
                  ),
                ]),
                el("div", { style: { textAlign: "right" } }, [
                  el("div", null, "Variant B"),
                  el(
                    "div",
                    { style: { fontWeight: "normal", color: "#555" } },
                    `${percentB}%`,
                  ),
                ]),
              ],
            ),

            // Confidence warning if below minimum impressions
            totalImpressions < minimumImpressions &&
              (percentA >= 95 || percentB >= 95
                ? el(
                    "div",
                    {
                      style: {
                        marginTop: "8px",
                        padding: "8px",
                        fontSize: "12px",
                        background: "#fff8e1",
                        border: "1px solid #ffe58f",
                        borderRadius: "6px",
                        color: "#664d03",
                      },
                    },
                    `Test needs more data to declare a winner. ${remainingImpressions} more impression${remainingImpressions !== 1 ? "s" : ""} needed.`,
                  )
                : el(
                    "div",
                    {
                      style: {
                        marginTop: "8px",
                        padding: "8px",
                        fontSize: "12px",
                        background: "#eef2f6",
                        border: "1px solid #d0d7de",
                        borderRadius: "6px",
                        color: "#3a3f44",
                      },
                    },
                    "Winner declared at 95% confidence",
                  )),
          ]);
        }

        controls.push(
          el(
            "div",
            {
              style: {
                marginTop: "12px",
                padding: "4px 0",
              },
            },
            [
              el(
                "div",
                {
                  style: {
                    fontSize: "12px",
                    marginBottom: "4px",
                    fontWeight: "bold",
                  },
                },
                "Current Confidence",
              ),

              confidenceContent,
            ],
          ),
        );
      }

      // Apply winner
      if ((abTestWinner === "A" || abTestWinner === "B") && thresholdsMet) {
        controls.push(
          el(
            Button,
            {
              isPrimary: true,
              style: { marginTop: "10px" },
              onClick: async () => {
                const winner = abTestWinner; // 'A' or 'B'
                if (winner !== "A" && winner !== "B") return;

                const postId = wp.data.select("core/editor").getCurrentPostId();
                const be = wp.data.dispatch("core/block-editor");

                // Log that the winner was APPLIED (so you can compute delay later)
                try {
                  const postId = wp.data
                    .select("core/editor")
                    .getCurrentPostId();
                  const winner = abTestWinner; // or your local 'winner' var if you already have it
                  const finish = attributes.abTestFinishedAt || Date.now(); // when declared
                  // This event time (DB 'time' column) is "applied at"
                  await apiFetch({
                    path: `/abtestkit/v1/track`,
                    method: "POST",
                    headers: { "X-WP-Nonce": window.abTestConfig?.nonce || "" },
                    data: {
                      type: "decision_applied",
                      postId,
                      abTestId,
                      variant: winner === "A" || winner === "B" ? winner : "",
                      ts: window.abTestConfig?._ts,
                      sig: window.abTestConfig?._sig,
                    },
                  });
                } catch (e) {}

                // Group context
                const inGroup = !!(attributes.abSync && attributes.abGroupKey);
                const groupKey = (attributes.abGroupKey || "").toLowerCase();

                // Who should we apply to?
                // If in a group: every member; otherwise just this block.
                const rootBlocks = wp.data
                  .select("core/block-editor")
                  .getBlocks();
                const flatten = (blocks) =>
                  blocks.flatMap((b) => [
                    b,
                    ...(b.innerBlocks ? flatten(b.innerBlocks) : []),
                  ]);
                const allBlocksLocal = flatten(rootBlocks);

                const members = inGroup
                  ? allBlocksLocal.filter(
                      (b) =>
                        b?.attributes?.abSync &&
                        String(b.attributes.abGroupKey || "").toLowerCase() ===
                          groupKey &&
                        b?.attributes?.abTestId,
                    )
                  : [block]; // only current block as a fallback

                // For DB cleanup afterwards
                const idsToReset = [];

                // Apply winner to each member’s own fields, then disable A/B
                members.forEach((m) => {
                  const mAttrs = m.attributes || {};
                  const mId = mAttrs.abTestId || "";
                  const mAll = mAttrs.abTestVariants || {};
                  const mThis = mAll[mId] || {};
                  const winObj = mThis[winner] || {};

                  // Build a patch of real block attributes using your existing setter
                  const patch = {};
                  Object.keys(winObj).forEach((field) => {
                    setNestedAttribute(patch, mAttrs, field, winObj[field]);
                  });

                  be.updateBlockAttributes(m.clientId, {
                    ...patch,
                    // turn A/B off entirely on this member
                    abTestEnabled: false,
                    abTestRunning: false,
                    abTestWinner: "",
                    abTestVariants: {},
                    abTestEval: null,
                    abTestResultsViewed: false,
                    conversionFrom: [],
                    abTestStartedAt: 0,
                  });

                  if (mId) idsToReset.push(mId);
                });

                // Reset all member tests in the DB
                await resetManyTests(postId, Array.from(new Set(idsToReset)));

                // 🔔 Telemetry: winner applied + delay since declared
                const finishedAt = attributes.abTestFinishedAt || 0;
                const delayMs = finishedAt ? Date.now() - finishedAt : null;

                sendTelemetry("winner_applied", {
                  postId,
                  abTestId,
                  winner,
                  grouped: inGroup,
                  groupKey: groupKey || null,
                  appliedTo: members
                    .map((m) => m.attributes?.abTestId)
                    .filter(Boolean),
                  finishedAt: finishedAt || null,
                  appliedAt: Date.now(),
                  msSinceDeclared: delayMs,
                  hoursSinceDeclared: delayMs
                    ? +(delayMs / 36e5).toFixed(2)
                    : null,
                });

                // Zero UI stats for the affected tests
                setStatsByTest((prev) => {
                  const next = { ...prev };
                  idsToReset.forEach((id) => {
                    next[id] = {
                      A: { impressions: 0, clicks: 0 },
                      B: { impressions: 0, clicks: 0 },
                    };
                  });
                  return next;
                });

                // Persist changes
                setTimeout(
                  () => wp.data.dispatch("core/editor").savePost(),
                  300,
                );
              },
            },
            `Apply Winner (${abTestWinner})`,
          ),
        );
      }
    }

    if (typeof window.abTestEvaluate === "undefined") {
      window.abTestEvaluate = {};
    }
    window.abTestEvaluate[abTestId] = evaluate;

    const isValidChild = (c) =>
      c == null ||
      typeof c === "string" ||
      typeof c === "number" ||
      (typeof c === "object" && c.$$typeof); // React element

    return el(
      PanelBody,
      { title: "A/B Test", initialOpen: true },
      ...controls.filter(isValidChild),
    );
  }

  // register plugin
  wp.domReady(() => {
    const {
      plugins: { registerPlugin },
      element: { createElement: el, Fragment },
      editPost: { PluginSidebar, PluginSidebarMoreMenuItem },
      components: { Dashicon },
    } = wp;

    registerPlugin("abtest-sidebar", {
      icon: el(
        "span",
        {
          style: {
            fontWeight: "bold",
            fontSize: "12px",
            lineHeight: "1",
            display: "inline-block",
            padding: "2px 4px",
          },
        },
        "A/B",
      ),
      render: () =>
        el(Fragment, null, [
          el(
            PluginSidebarMoreMenuItem,
            { target: "abtest-sidebar" },
            "abtestkit",
          ),
          el(
            PluginSidebar,
            { name: "abtestkit", title: "abtestkit" },
            // Wrapper makes the sidebar a flex column so the footer can stick to the bottom
            el(
              "div",
              {
                style: {
                  display: "flex",
                  flexDirection: "column",
                  height: "100%",
                },
              },
              [
                // Main content scrolls if it gets long
                el(
                  "div",
                  {
                    style: {
                      flex: "1 1 auto",
                      overflowY: "auto",
                      paddingBottom: "8px",
                    },
                  },
                  el(AbSidebarComponent),
                ),

                // Footer with a divider
                el(
                  "div",
                  {
                    style: {
                      marginTop: "auto",
                      padding: "12px",
                      borderTop: "1px solid #ddd",
                      fontSize: "12px",
                      textAlign: "center",
                      background: "#fff",
                    },
                  },
                  el(
                    "a",
                    {
                      href: "mailto:abtestkit@gmail.com?subject=abtestkit%20Feedback",
                      style: { textDecoration: "none", color: "#007cba" },
                    },
                    "Request a feature or raise an issue",
                  ),
                ),
              ],
            ),
          ),
        ]),
    });
  });
})(window.wp);
