/* assets/js/pt-wizard.js */
(function (wp) {
  const { createElement: h, Fragment, useState, useEffect, useRef } = wp.element;
  const {
    Button,
    Card,
    CardBody,
    TextControl,
    TextareaControl,
    RadioControl,
    Spinner,
    Notice,
    SelectControl,
    SearchControl,
    Tooltip,
    ToggleControl,
  } = wp.components;

const apiFetch = wp.apiFetch;

// Decode HTML entities in titles (e.g. &amp; -> &)
const decodeEntities =
  (wp.htmlEntities && wp.htmlEntities.decodeEntities)
    ? wp.htmlEntities.decodeEntities
    : (s) => {
        // fallback
        const el = document.createElement("textarea");
        el.innerHTML = String(s || "");
        return el.value;
      };
  const cfg = window.abtestkit_PT || {};

  const getPreviewBase = (kind) => {
    return String(kind || "") === "page"
      ? (cfg.pageViewBase || cfg.viewBase || "")
      : (cfg.postViewBase || cfg.viewBase || "");
  };

  const getEntityPreviewUrl = (entity, kind) => {
    if (
      entity &&
      typeof entity.preview_url === "string" &&
      entity.preview_url.trim() !== ""
    ) {
      return entity.preview_url.trim();
    }

    const id = entity && entity.id ? entity.id : 0;
    return id ? `${getPreviewBase(kind)}${id}&abtestkit_preview=1` : "";
  };

  const getProductPreviewUrl = (entity, forcedVariant = "", extraParams = {}) => {
    if (!entity) return "";

    let base = "";

    // WooCommerce products are safest in the iframe when loaded through
    // their real product URL. Some preview URLs can be unreliable inside
    // the admin iframe.
    if (
      typeof entity.permalink === "string" &&
      entity.permalink.trim() !== ""
    ) {
      base = entity.permalink.trim();
    } else if (
      typeof entity.preview_url === "string" &&
      entity.preview_url.trim() !== ""
    ) {
      base = entity.preview_url.trim();
    } else {
      const id = entity && entity.id ? parseInt(entity.id, 10) : 0;

      if (id) {
        const fallbackBase = String(
          cfg.postViewBase ||
          cfg.viewBase ||
          window.location.origin + "/?p="
        ).trim();

        try {
          const parsed = new URL(fallbackBase, window.location.href);
          parsed.searchParams.delete("p");
          parsed.searchParams.delete("page_id");
          parsed.searchParams.set("post_type", "product");
          parsed.searchParams.set("p", String(id));
          base = parsed.toString();
        } catch (_) {
          base =
            fallbackBase +
            (fallbackBase.indexOf("?") === -1 ? "?" : "&") +
            "post_type=product&p=" +
            encodeURIComponent(String(id));
        }
      }
    }

    if (!base) return "";

    const appendParam = (key, value) => {
      if (value === undefined || value === null || value === "") return;
      if (base.indexOf(`${encodeURIComponent(key)}=`) !== -1) return;

      base +=
        (base.indexOf("?") === -1 ? "?" : "&") +
        `${encodeURIComponent(key)}=${encodeURIComponent(String(value))}`;
    };

    appendParam("abtestkit_preview", "1");

    if (forcedVariant === "A" || forcedVariant === "B") {
      appendParam("abtestkit_force", forcedVariant);
    }

    Object.keys(extraParams || {}).forEach((key) => {
      appendParam(key, extraParams[key]);
    });

    return base;
  };

  const ABTK_PREVIEW_LOAD_TIMEOUT_MS = 10000;

  const abtkSetPreviewQueryParam = (url, key, value) => {
    const raw = String(url || "").trim();

    if (!raw || !key || value === undefined || value === null || value === "") {
      return raw;
    }

    try {
      const parsed = new URL(raw, window.location.href);
      parsed.searchParams.set(String(key), String(value));
      return parsed.toString();
    } catch (_) {
      const encodedKey = encodeURIComponent(String(key));
      const encodedValue = encodeURIComponent(String(value));

      if (raw.indexOf(encodedKey + "=") !== -1) {
        return raw.replace(
          new RegExp("([?&])" + encodedKey + "=[^&]*", "g"),
          "$1" + encodedKey + "=" + encodedValue
        );
      }

      return raw + (raw.indexOf("?") === -1 ? "?" : "&") + encodedKey + "=" + encodedValue;
    }
  };

  const abtkOpenPreviewInNewTab = (url) => {
    const safeUrl = String(url || "").trim();

    if (!safeUrl) {
      return;
    }

    window.open(safeUrl, "_blank", "noopener,noreferrer");
  };

  const abtkRecordPreviewLoadFailure = ({ postId = 0, url = "", label = "", context = "" } = {}) => {
    if (!cfg || !cfg.nonce || !url) {
      return;
    }

    apiFetch({
      path: "/abtestkit/v1/pt/preview-health",
      method: "POST",
      headers: { "X-WP-Nonce": cfg.nonce, "Content-Type": "application/json" },
      data: {
        post_id: parseInt(postId, 10) || 0,
        url: String(url || ""),
        label: String(label || ""),
        context: String(context || ""),
      },
    }).catch(() => {});
  };

  const PreviewLoadFailureCard = ({ previewUrl = "", onRetry }) =>
    h(
      "div",
      {
        style: {
          width: "100%",
          maxWidth: 420,
          padding: "18px 20px",
          background: "#ffffff",
          border: "1px solid #dcdcde",
          borderRadius: 8,
          boxShadow: "0 1px 2px rgba(0,0,0,0.04)",
          textAlign: "left",
          boxSizing: "border-box",
        },
      },
      [
        h(
          "div",
          {
            style: {
              fontSize: 15,
              fontWeight: 600,
              color: "#1d2327",
              marginBottom: 6,
            },
          },
          "Preview couldn't load"
        ),
        h(
          "p",
          {
            style: {
              margin: "0 0 14px",
              color: "#50575e",
              fontSize: 13,
              lineHeight: 1.5,
            },
          },
          "The selected page couldn't be previewed."
        ),
        h(
          "div",
          {
            style: {
              display: "flex",
              gap: 8,
              flexWrap: "wrap",
            },
          },
          [
            h(
              Button,
              {
                isPrimary: true,
                onClick: () => {
                  if (typeof onRetry === "function") {
                    onRetry();
                  }
                },
              },
              "Retry Preview"
            ),
            h(
              Button,
              {
                isSecondary: true,
                onClick: () => abtkOpenPreviewInNewTab(previewUrl),
              },
              "Open Preview in New Tab"
            ),
          ]
        ),
      ]
    );



  // ─────────────────────────────────────────────────────────────
  // Telemetry helpers (opt-in gated server-side)
  // Sends to WP REST: /abtestkit/v1/telemetry
  // ─────────────────────────────────────────────────────────────
  const ABTK_TLM_PATH = "/abtestkit/v1/telemetry";

  const abtkMakeSessionId = () => {
    try {
      if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
    } catch (_) {}
    return (
      "ws_" +
      Math.random().toString(16).slice(2) +
      "_" +
      Date.now().toString(16)
    );
  };

  const abtkSafeInt = (n, min = 0, max = 86400000) => {
    const x = parseInt(n, 10);
    if (!Number.isFinite(x)) return min;
    return Math.max(min, Math.min(max, x));
  };

  const abtkSendTelemetry = (event, payload) => {
    // If cfg.nonce is missing, do nothing (never break UX)
    if (!cfg || !cfg.nonce) return Promise.resolve();

    return apiFetch({
      path: ABTK_TLM_PATH,
      method: "POST",
      headers: { "X-WP-Nonce": cfg.nonce, "Content-Type": "application/json" },
      data: {
        event,
        payload: payload && typeof payload === "object" ? payload : {},
      },
    }).catch(() => {});
  };

  // Simple wrapper around the WordPress media frame so we can pick images
  const openMediaFrame = ({ multiple = false, title, buttonText, onSelect }) => {
    if (!window.wp || !wp.media || typeof onSelect !== "function") return;

    const frame = wp.media({
      title: title || "Select image",
      library: { type: "image" },
      multiple,
      button: {
        text: buttonText || "Use image",
      },
    });

    frame.on("select", () => {
      const selection = frame.state().get("selection");
      if (!selection) return;

      const items = [];
      selection.each((attachment) => {
        if (!attachment || !attachment.get) return;
        const id = attachment.get("id");
        const url =
          (attachment.get("url") ||
            (attachment.attributes && attachment.attributes.url)) ||
          "";
        if (id && url) {
          items.push({ id, url });
        }
      });

      if (!items.length) return;

      if (multiple) {
        onSelect(items);
      } else {
        onSelect(items[0]);
      }
    });

    frame.open();
  };

  // TinyMCE-based classic editor field used for Version B product descriptions
  const ClassicEditorField = ({ id, value, onChange, help, readOnly = false }) => {
    const textareaId = id;

    // Initialise editor once
    useEffect(() => {
      if (!window.wp || !wp.editor || !wp.editor.initialize) return;

      const $ = window.jQuery || window.$;
      if (!$) return;

      // Set initial value in the textarea before turning it into an editor
      if (typeof value === "string") {
        $("#" + textareaId).val(value);
      }

      // Clean up any previous editor instance on this ID
      if (wp.editor.remove) {
        try {
          wp.editor.remove(textareaId);
        } catch (e) {}
      }

      const onInit = (event, editor) => {
        if (!editor || editor.id !== textareaId) return;
        editor.on("change keyup", () => {
          const content = editor.getContent();
          if (typeof onChange === "function") {
            onChange(content);
          }
        });
      };

      $(document).on(
        "tinymce-editor-init.abtestkit-" + textareaId,
        onInit
      );

      wp.editor.initialize(textareaId, {
        tinymce: {
          wpautop: true,
          toolbar1: readOnly ? false : "formatselect,bold,italic,bullist,numlist,link,unlink,blockquote,undo,redo",
          toolbar2: "",
          readonly: readOnly,
        },
        quicktags: true,
      });

      return () => {
        $(document).off(
          "tinymce-editor-init.abtestkit-" + textareaId,
          onInit
        );
        if (wp.editor.remove) {
          try {
            wp.editor.remove(textareaId);
          } catch (e) {}
        }
      };
    }, [textareaId]);

    // Keep programmatic value changes (like prefill from Version A) in sync
    useEffect(() => {
      if (!window.tinymce) return;
      const ed = window.tinymce.get(textareaId);
      if (!ed) return;
      if (typeof value === "string" && value !== ed.getContent()) {
        ed.setContent(value);
      }
    }, [textareaId, value]);

    return h(
      "div",
      null,
      [
        h("textarea", {
          id: textareaId,
          defaultValue: value || "",
          style: { width: "100%", minHeight: 200 },
        }),
        help
          ? h(
              "p",
              { style: { marginTop: 4, color: "#6c7781", fontSize: 12 } },
              help
            )
          : null,
      ]
    );
  };

  // Utility: strip HTML tags for plain-text placeholders, etc.
  const stripHtml = (html) =>
    typeof html === "string"
      ? html.replace(/<\/?[^>]+(>|$)/g, "").replace(/\s+/g, " ").trim()
      : "";

  /* ──────────────────────────────────────────────────────────────
   * Vertical stepper (wizard step navigation) on the left
   * ────────────────────────────────────────────────────────────── */
  const Step = ({ title, index, current }) => {
    const done = index < current;
    const active = index === current;

    return h(
      "div",
      {
        style: {
          display: "flex",
          gap: "8px",
          alignItems: "center",
          opacity: 1,
          margin: "8px 0",
        },
      },
      [
        h(
          "div",
          {
            style: {
              width: 18,
              height: 18,
              borderRadius: 18,
              border: "2px solid #2271b1",
              background: active ? "#2271b1" : done ? "#e5f1fa" : "white",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              boxSizing: "border-box",
              flex: "0 0 18px",
            },
          },
          done
            ? h(
                "svg",
                {
                  width: 10,
                  height: 10,
                  viewBox: "0 0 20 20",
                  "aria-hidden": true,
                  style: { display: "block" },
                },
                h("path", {
                  d: "M7.5 13.5 4.5 10.5 3.1 11.9 7.5 16.3 16.9 6.9 15.5 5.5Z",
                  fill: "#2271b1",
                })
              )
            : null
        ),
        h(
          "div",
          {
            style: {
              fontWeight: active ? 600 : done ? 500 : 400,
              color: active ? "#1d2327" : done ? "#50575e" : "#1d2327",
            },
          },
          title
        ),
      ]
    );
  };

    // ──────────────────────────────────────────────────────────────
  // Tips panel shown in the grey area under the main wizard card (Step 0)
  // ──────────────────────────────────────────────────────────────
  const TipsPanel = ({ postType, step }) => {
    if (step !== 0) return null;

    const tipsByType = {
      product: {
        title: "Tips: WooCommerce product tests",
        items: [
          "Only one real product exists (one URL) shoppers see Version A or B on the same product.",
          "SKU, stock, inventory from version A is always used and updates in real time.",
          "Version B is never indexed for SEO safety.",
          "The version presented is kept consistent across the website experience (product gallery + common product cards + checkout where product is shown).",
        ],
      },
    };

    const data = tipsByType[String(postType || "")];

    // Completely hide until a valid test type is selected
    if (!data) return null;

    return h(
      "div",
      {
        style: {
          marginTop: 14,
          padding: "14px 16px",
          background: "#ffffff",
          border: "1px solid #dcdcde",
          borderLeft: "4px solid #2271b1",
          borderRadius: 8,
          boxShadow: "0 1px 2px rgba(0,0,0,0.04)",
        },
      },
      [
        h(
          "div",
          { style: { display: "flex", alignItems: "flex-start", gap: 10 } },
          [
            h(
              "span",
              {
                style: {
                  width: 28,
                  height: 28,
                  borderRadius: 999,
                  background: "#e5f1fa",
                  display: "inline-flex",
                  alignItems: "center",
                  justifyContent: "center",
                  flex: "0 0 auto",
                  marginTop: 1,
                },
              },
              h("span", {
                className: "dashicons dashicons-info-outline",
                style: { fontSize: 18, color: "#2271b1" },
              })
            ),

            h("div", { style: { minWidth: 0, width: "100%" } }, [
              h(
                "div",
                { style: { fontWeight: 600, fontSize: 14, lineHeight: 1.3 } },
                data.title
              ),

              h(
                "ul",
                {
                  style: {
                    margin: "10px 0 0",
                    paddingLeft: 18,
                    listStyleType: "disc",
                    listStylePosition: "outside",
                    color: "#50575e",
                    fontSize: 13,
                    lineHeight: 1.5,
                  },
                },
                data.items.map((t, i) =>
                  h("li", { key: i, style: { margin: "6px 0" } }, t)
                )
              ),
            ]),
          ]
        ),
      ]
    );
  };

  const GoodToKnowCarousel = ({ postType, embedded = false }) => {
    const [activeIndex, setActiveIndex] = useState(0);

    const itemsByType = {
      product: [
        {
          title: "SKU and stock stay tied to Version A",
          body: "Inventory handling stays safe during the test because Version A remains the only real product behind the scenes.",
        },
        {
          title: "The journey stays consistent",
          body: "Visitors keep seeing their assigned version across the product page, thumbnails, cart, checkout, invoice and all other product/catalogue views.",
        },
        {
          title: "Orders still follow your normal checkout process",
          body: "Version A or B persist across the checkout, without altering your usual WooCommerce order flow.",
        },
       {
          title: "Apply the winner in one click",
          body: "If Version B wins, you can update Version A with the winning contents in one click.",
        }, 
        {
          title: "Version B stays safely in draft",
          body: "Version B is never published, so it can’t appear as a second live version.",
        },
        {
          title: "Consistency across visits",
          body: "Visitors keep their assigned version on the same browser through cookies, so their test experience stays consistent.",
        },
      ],
      page: [
        {
          title: "Version B stays safely in draft",
          body: "Version B is never published, so it can’t appear as a second live version.",
        },
        {
          title: "Apply the winner in one click",
          body: "If Version B wins, you can update Version A with the winning contents in one click.",
        },
        {
          title: "Draft variations are kept out of normal browsing",
          body: "Visitors and search engines will not reach the draft version through normal site navigation.",
        },
        {
          title: "Consistency across visits",
          body: "Visitors keep their assigned version on the same browser through cookies, so their test experience stays consistent.",
        },
      ],
      post: [
        {
          title: "Version B stays safely in draft",
          body: "Version B is never published, so it can’t appear as a second live version.",
        },
        {
          title: "Apply the winner in one click",
          body: "If Version B wins, you can update Version A with the winning contents in one click.",
        },
        {
          title: "Draft variations are kept out of normal browsing",
          body: "Visitors and search engines will not reach the draft version through normal site navigation.",
        },
        {
          title: "Consistency across visits",
          body: "Visitors keep their assigned version on the same browser through cookies, so their test experience stays consistent.",
        },
      ],
    };

    const items = itemsByType[String(postType || "")] || [];

    useEffect(() => {
      setActiveIndex(0);
    }, [postType]);

    if (!items.length) return null;

    const currentItem = items[activeIndex];
    const atStart = activeIndex === 0;
    const atEnd = activeIndex === items.length - 1;

    const navButtonStyle = (disabled) => ({
      width: 30,
      height: 30,
      borderRadius: 999,
      border: "1px solid #dcdcde",
      background: disabled ? "#f6f7f7" : "#ffffff",
      color: disabled ? "#a7aaad" : "#2271b1",
      display: "inline-flex",
      alignItems: "center",
      justifyContent: "center",
      cursor: disabled ? "default" : "pointer",
      padding: 0,
      boxSizing: "border-box",
    });

    return h(
      "div",
      {
        style: embedded
          ? {
              padding: 0,
              background: "transparent",
              border: 0,
              borderRadius: 0,
            }
          : {
              padding: "14px 16px",
              background: "#f6f7f7",
              border: "1px solid #dcdcde",
              borderRadius: 8,
            },
      },
      [
        h(
          "div",
          {
            style: {
              display: "flex",
              alignItems: "center",
              justifyContent: "space-between",
              gap: 12,
              marginBottom: 12,
            },
          },
          [
            h("div", null, [
              h(
                "div",
                {
                  style: {
                    fontSize: 12,
                    fontWeight: 600,
                    color: "#1d2327",
                    textTransform: "uppercase",
                    letterSpacing: "0.04em",
                    marginBottom: 4,
                  },
                },
                "Good to know"
              ),
              h(
                "div",
                {
                  style: {
                    fontSize: 12,
                    color: "#6c7781",
                  },
                },
                `${activeIndex + 1} of ${items.length}`
              ),
            ]),

            h(
              "div",
              { style: { display: "flex", gap: 8, alignItems: "center" } },
              [
                h(
                  "button",
                  {
                    type: "button",
                    onClick: atStart
                      ? undefined
                      : () => setActiveIndex((i) => Math.max(0, i - 1)),
                    disabled: atStart,
                    "aria-label": "Previous item",
                    style: navButtonStyle(atStart),
                  },
                  h("span", {
                    className: "dashicons dashicons-arrow-left-alt2",
                    style: {
                      fontSize: 14,
                      width: 14,
                      height: 14,
                    },
                  })
                ),
                h(
                  "button",
                  {
                    type: "button",
                    onClick: atEnd
                      ? undefined
                      : () =>
                          setActiveIndex((i) =>
                            Math.min(items.length - 1, i + 1)
                          ),
                    disabled: atEnd,
                    "aria-label": "Next item",
                    style: navButtonStyle(atEnd),
                  },
                  h("span", {
                    className: "dashicons dashicons-arrow-right-alt2",
                    style: {
                      fontSize: 14,
                      width: 14,
                      height: 14,
                    },
                  })
                ),
              ]
            ),
          ]
        ),

        h(
          "div",
          {
            style: {
              minHeight: 84,
              padding: "12px 14px",
              background: "#ffffff",
              border: "1px solid #e0e0e0",
              borderRadius: 8,
            },
          },
          [
            h(
              "div",
              {
                style: {
                  fontSize: 14,
                  fontWeight: 600,
                  color: "#1d2327",
                  marginBottom: 6,
                },
              },
              currentItem.title
            ),
            h(
              "p",
              {
                style: {
                  margin: 0,
                  color: "#50575e",
                  fontSize: 13,
                  lineHeight: 1.6,
                },
              },
              currentItem.body
            ),
          ]
        ),

        h(
          "div",
          {
            style: {
              display: "flex",
              justifyContent: "center",
              gap: 8,
              marginTop: 12,
            },
          },
          items.map((item, i) =>
            h("button", {
              key: `${item.title}-${i}`,
              type: "button",
              onClick: () => setActiveIndex(i),
              "aria-label": `Go to item ${i + 1}`,
              style: {
                width: 8,
                height: 8,
                borderRadius: 999,
                border: 0,
                padding: 0,
                background: i === activeIndex ? "#2271b1" : "#c3c4c7",
                cursor: "pointer",
              },
            })
          )
        ),
      ]
    );
  };

// ─────────────────────────────────────────────────────────────
// ElementPicker: iframe preview + guarded picking
// goal === "clicks" → any clickable element (links, buttons, etc.)
// onPick({ selector, href, label }) receives a stable selector or URL
// ─────────────────────────────────────────────────────────────
const ElementPicker = ({
  pageId,
  viewBase,
  rawUrl = "",
  goal = "clicks",
  selected = [],
  onPick,
  onWarn,
  label = "Version A",
  previewMode = "mobile",
  allowAnyElement = false,
  preferExactElement = false,
  actionLabel = "",
  interactiveWhenNotPicking = false,
  previewVariant = "",
  previewCss = "",
  previewMarkers = [],
  previewHtmlChanges = [],
  showTargetRefreshButton = false,
  afterPreviewContent = null,
}) => {
  const { useRef, useEffect, useState, Fragment, createElement: h } = wp.element;
  const frameRef = useRef(null);
  const previewWrapRef = useRef(null);
  const [picking, setPicking] = useState(false);
  const [previewWrapWidth, setPreviewWrapWidth] = useState(0);
  const [refreshNonce, setRefreshNonce] = useState(0);
  const [previewLoadState, setPreviewLoadState] = useState("loading");
  const [htmlMatchCounts, setHtmlMatchCounts] = useState({});

  const makePickerUrl = (baseUrl, nonce = 0) => {
    let next = String(baseUrl || "").trim();

    if (!next) {
      return "";
    }

    if (!nonce) {
      return next;
    }

    try {
      const parsed = new URL(next, window.location.href);
      parsed.searchParams.set("abtestkit_r", String(nonce));
      return parsed.toString();
    } catch (_) {
      if (next.indexOf("abtestkit_r=") !== -1) {
        return next.replace(
          /([?&])abtestkit_r=[^&]*/g,
          "$1abtestkit_r=" + encodeURIComponent(String(nonce))
        );
      }

      return (
        next +
        (next.indexOf("?") === -1 ? "?" : "&") +
        "abtestkit_r=" +
        encodeURIComponent(String(nonce))
      );
    }
  };

  // --- helpers for specific, stable selectors -----------------
  const cssEscape = (window.CSS && CSS.escape) ? CSS.escape : (s) =>
    String(s).replace(/[^a-zA-Z0-9_-]/g, (m) => "\\" + m.charCodeAt(0).toString(16) + " ");

  const nthOfType = (el) => {
    let i = 1, sib = el;
    while ((sib = sib.previousElementSibling)) {
      if (sib.tagName === el.tagName) i++;
    }
    return i;
  };

  const nearestButtonContainer = (el) => {
    // Prefer common Gutenberg wrappers so selectors remain stable after edits
    return el.closest(".wp-block-buttons, .wp-block-group, main, article, section, .entry-content, body");
  };

  const makeExactSelector = (el) => {
    if (!el || !el.tagName) return "";
    if (el.id) return "#" + cssEscape(el.id);

    const doc = el.ownerDocument;
    const parts = [];
    let cur = el;

    while (cur && cur.tagName && cur !== doc.documentElement) {
      if (cur.id) {
        parts.unshift("#" + cssEscape(cur.id));
        break;
      }

      let seg = cur.tagName.toLowerCase();
      const stableClasses = [...(cur.classList || [])]
        .filter((className) => {
          const name = String(className || "");
          return (
            name &&
            name.indexOf("abtestkit-") !== 0 &&
            !/^(active|current|selected|open|opened|hover|focus|is-active|is-open|is-selected)$/i.test(name)
          );
        })
        .slice(0, 2);

      if (stableClasses.length) {
        seg += "." + stableClasses.map(cssEscape).join(".");
      }

      const parent = cur.parentElement;
      if (parent) {
        const sameTagSiblings = Array.from(parent.children).filter(
          (sibling) => sibling.tagName === cur.tagName
        );

        if (sameTagSiblings.length > 1) {
          seg += `:nth-of-type(${sameTagSiblings.indexOf(cur) + 1})`;
        }
      }

      parts.unshift(seg);

      const candidate = parts.join(" > ");
      try {
        if (doc.querySelectorAll(candidate).length === 1) {
          return candidate;
        }
      } catch (_) {}

      cur = parent;
    }

    return parts.join(" > ");
  };

  const makeSelector = (rawEl, exactElement = false) => {
    if (!rawEl || !rawEl.tagName) return "";

    if (exactElement) {
      return makeExactSelector(rawEl);
    }

    // Existing click/CSS behaviour: build the selector for the clickable thing.
    const el =
      rawEl.closest("a[href],button,[role='button'],[onclick],input[type='submit'],.wp-block-button__link") ||
      rawEl;

    // If it has an ID, that's the best selector
    if (el.id) return "#" + cssEscape(el.id);

    // Try to build a short path from a meaningful container
    const container = nearestButtonContainer(el);
    let cur = el;
    const parts = [];

    while (cur && cur !== container.parentElement) {
      let seg;

      if (cur.id) {
        seg = "#" + cssEscape(cur.id);
      } else {
        // Prefer a single, specific Gutenberg class when present
        const classes = [...(cur.classList || [])];
        const wpClass = classes.find((c) => c.startsWith("wp-block"));
        if (wpClass) {
          seg = "." + wpClass;
        } else if (classes.length) {
          // keep it short but specific
          seg = cur.tagName.toLowerCase() + "." + classes.slice(0, 2).map(cssEscape).join(".");
        } else {
          seg = cur.tagName.toLowerCase();
        }

        // Disambiguate siblings of same tag
        const n = nthOfType(cur);
        if (n > 1) seg += `:nth-of-type(${n})`;
      }

      parts.unshift(seg);

      // Stop once we hit a stable parent ID. This keeps selectors short, e.g.
      // Woo tabs become #tab-title-reviews > a instead of a long theme path.
      if (cur.id && cur !== el) break;
      if (cur === container) break;
      cur = cur.parentElement;
    }

    // Typical Gutenberg: .wp-block-buttons .wp-block-button:nth-of-type(N) .wp-block-button__link
    return parts.join(" > ");
  };

  const isValidPick = (win, target) => {
    if (!(target instanceof win.Element)) return false;

    if (allowAnyElement) {
      return true;
    }

    const clickable =
      target.closest &&
      target.closest(
        "a[href], button, [role='button'], [onclick], input[type='submit'], .wp-block-button__link"
      );

    if (goal === "clicks") return !!clickable;
    // Picker isn't used for "form" – those are handled by submissions
    if (goal === "form") return false;

    return false;
  };

  useEffect(() => {
    if (!picking || previewLoadState !== "loaded" || !frameRef.current) return;

    const frame = frameRef.current;

    const bind = () => {
      let win;
      let doc;

      try {
        win = frame.contentWindow;
        doc = win && win.document;
      } catch (_) {
        return;
      }

      if (!doc || !doc.body) return;

      // Highlight box
      const hi = doc.createElement("div");
      hi.style.cssText =
        "position:fixed;pointer-events:none;outline:2px solid #2271b1;z-index:2147483647;transition:outline-color .15s ease";
      doc.body.appendChild(hi);

      let lastBadFlash = 0;
      const flashBad = () => {
        const now = Date.now();
        if (now - lastBadFlash < 250) return; // rate-limit the flash
        lastBadFlash = now;
        hi.style.outlineColor = "#d63638"; // WP error red
        setTimeout(() => {
          hi.style.outlineColor = "#2271b1";
        }, 180);
      };

      const move = (e) => {
        const t = e.target;
        if (!(t instanceof win.Element)) return;
        const r = t.getBoundingClientRect();
        hi.style.left = r.left + "px";
        hi.style.top = r.top + "px";
        hi.style.width = r.width + "px";
        hi.style.height = r.height + "px";
      };

      const pick = (e) => {
        // Stop normal navigation / click behaviour
        e.preventDefault();
        e.stopPropagation();
        if (e.stopImmediatePropagation) e.stopImmediatePropagation();

        const t = e.target;
        if (!isValidPick(win, t)) {
          flashBad();
          if (typeof onWarn === "function") {
            onWarn(
              allowAnyElement
                ? "Please click an element in the preview."
                : "Please click something clickable (button, link, or interactive element)."
            );
          }
          return;
        }

        const anchor = t.closest("a[href]");
        const selector = makeSelector(t, preferExactElement);

        // Build a small display payload (for the UI list)
        let href = "";
        let label = "";
        if (anchor) {
          try {
            const a = anchor;
            const rawAnchorHref = String(a.getAttribute("href") || "").trim();
            const u = new URL(rawAnchorHref || a.href, win.location.href);
            const current = new URL(win.location.href);

            const stripPreviewParams = (url) => {
              [
                "abtestkit_preview",
                "abtestkit_force",
                "abtestkit_shadow_preview_id",
                "abtestkit_r",
              ].forEach((key) => url.searchParams.delete(key));
              url.hash = "";
              url.pathname = url.pathname.replace(/\/+$/, "") || "/";
              return url;
            };

            const compareTarget = stripPreviewParams(new URL(u.href));
            const compareCurrent = stripPreviewParams(new URL(current.href));
            const isSamePageTarget =
              compareTarget.origin === compareCurrent.origin &&
              compareTarget.pathname === compareCurrent.pathname &&
              compareTarget.search === compareCurrent.search;

            const isHashOnlyTarget = rawAnchorHref.charAt(0) === "#";
            const isJavascriptTarget = /^javascript:/i.test(rawAnchorHref);

            // Same-page tabs/anchors are element clicks, not destination URL clicks.
            // Saving their URL would create broad current-page targets such as
            // /product/example?abtestkit_preview=1*, so prefer the selector.
            if (!isSamePageTarget && !isHashOnlyTarget && !isJavascriptTarget) {
              // store path+query only (stable across domains), with preview params removed
              href =
                (compareTarget.pathname.replace(/\/+$/, "") || "/") +
                (compareTarget.search || "");
            }
          } catch (_) {
            href = anchor.getAttribute("href") || "";
          }
          label = (anchor.textContent || "").trim().replace(/\s+/g, " ").slice(0, 80);
        } else if (t && t.textContent) {
          label = t.textContent.trim().replace(/\s+/g, " ").slice(0, 80);
        }

        onPick({
          selector,
          href,
          label,
          inner_html: t && typeof t.innerHTML === "string" ? t.innerHTML : "",
          tag_name: t && t.tagName ? String(t.tagName).toLowerCase() : "",
        });

        cleanup();
      };

      const esc = (e) => {
        if (e.key === "Escape") cleanup();
      };

      const cleanup = () => {
        try {
          hi.remove();
        } catch (_) {}
        doc.removeEventListener("mousemove", move, true);
        doc.removeEventListener("click", pick, true);
        doc.removeEventListener("keydown", esc, true);
        setPicking(false);
      };

      doc.addEventListener("mousemove", move, true);
      doc.addEventListener("click", pick, true); // capture phase so we intercept before page handlers
      doc.addEventListener("keydown", esc, true);

      return cleanup;
    };

    let isReady = false;

    try {
      isReady = !!(frame.contentDocument && frame.contentDocument.readyState === "complete");
    } catch (_) {
      isReady = false;
    }

    if (isReady) {
      return bind();
    } else {
      const onLoad = () => bind();
      frame.addEventListener("load", onLoad, { once: true });
      return () => frame.removeEventListener("load", onLoad);
    }
  }, [picking, goal, previewLoadState]);

  useEffect(() => {
    const el = previewWrapRef.current;
    if (!el) return;

    let raf = 0;
    const update = () => {
      if (raf) {
        window.cancelAnimationFrame(raf);
      }

      raf = window.requestAnimationFrame(() => {
        setPreviewWrapWidth(el.clientWidth || 0);
      });
    };

    update();

    if (window.ResizeObserver) {
      const ro = new ResizeObserver(update);
      ro.observe(el);

      return () => {
        if (raf) {
          window.cancelAnimationFrame(raf);
        }
        ro.disconnect();
      };
    }

    window.addEventListener("resize", update);

    return () => {
      if (raf) {
        window.cancelAnimationFrame(raf);
      }
      window.removeEventListener("resize", update);
    };
  }, [previewMode]);

  const targetUrl =
    typeof rawUrl === "string" && rawUrl.trim() !== ""
      ? rawUrl.trim()
      : `${viewBase}${pageId}&abtestkit_preview=1`;

  const url = makePickerUrl(targetUrl, refreshNonce);
  const previewLoadFailed = previewLoadState === "failed";

  const retryPreview = () => {
    setPicking(false);
    setPreviewLoadState("loading");
    setRefreshNonce(Date.now());
  };

  useEffect(() => {
    const frame = frameRef.current;

    setPicking(false);
    setPreviewLoadState(url ? "loading" : "idle");
    setHtmlMatchCounts({});

    if (!frame || !url) {
      return;
    }

    let settled = false;

    const onLoad = () => {
      if (settled) {
        return;
      }

      settled = true;
      window.clearTimeout(timer);
      setPreviewLoadState("loaded");
    };

    const timer = window.setTimeout(() => {
      if (settled) {
        return;
      }

      settled = true;
      frame.removeEventListener("load", onLoad);
      setPicking(false);
      setPreviewLoadState("failed");

      try {
        frame.src = "about:blank";
      } catch (_) {}

      abtkRecordPreviewLoadFailure({
        postId: pageId,
        url,
        label,
        context: preferExactElement
          ? "html_selector_picker"
          : allowAnyElement
          ? "css_marker_picker"
          : "click_picker",
      });
    }, ABTK_PREVIEW_LOAD_TIMEOUT_MS);

    frame.addEventListener("load", onLoad, { once: true });

    try {
      const doc = frame.contentDocument;
      const href =
        frame.contentWindow && frame.contentWindow.location
          ? String(frame.contentWindow.location.href || "")
          : "";

      if (doc && doc.readyState === "complete" && href && href !== "about:blank") {
        onLoad();
      }
    } catch (_) {}

    return () => {
      settled = true;
      window.clearTimeout(timer);
      frame.removeEventListener("load", onLoad);
    };
  }, [url, pageId, label, allowAnyElement, preferExactElement]);

  useEffect(() => {
    const frame = frameRef.current;
    if (!frame || !url || previewLoadState !== "loaded") {
      return;
    }

    const htmlChanges = Array.isArray(previewHtmlChanges)
      ? previewHtmlChanges
      : [];

    const shouldApplyCustomPreview =
      String(previewVariant || "").toUpperCase() === "B" ||
      String(previewCss || "").trim() !== "" ||
      (Array.isArray(previewMarkers) && previewMarkers.length > 0) ||
      htmlChanges.length > 0;

    if (!shouldApplyCustomPreview) {
      return;
    }

    let restored = false;
    const appliedHtml = [];

    const undoAppliedHtml = () => {
      appliedHtml.reverse().forEach((item) => {
        if (!item) {
          return;
        }

        if (item.type === "replace" && item.el && typeof item.original === "string") {
          try {
            item.el.innerHTML = item.original;
          } catch (_) {}
          return;
        }

        if (item.type === "insert" && Array.isArray(item.nodes)) {
          item.nodes.forEach((node) => {
            try {
              if (node && node.parentNode) {
                node.parentNode.removeChild(node);
              }
            } catch (_) {}
          });
        }
      });

      appliedHtml.length = 0;
    };

    const restoreHtmlPreview = () => {
      if (restored) {
        return;
      }

      restored = true;
      undoAppliedHtml();
    };

    const applyCustomPreview = () => {
      let doc;

      try {
        doc = frame.contentDocument || (frame.contentWindow && frame.contentWindow.document);
      } catch (_) {
        return;
      }

      if (!doc || !doc.documentElement) {
        return;
      }

      if (appliedHtml.length) {
        undoAppliedHtml();
      }

      const nextMatchCounts = {};

      if (doc.body && doc.body.classList) {
        doc.body.classList.remove("abtestkit-custom-css-b", "abtestkit-custom-html-b");

        if (htmlChanges.length > 0) {
          doc.body.classList.add("abtestkit-custom-html-b");
        } else {
          doc.body.classList.add("abtestkit-custom-css-b");
        }
      }

      try {
        doc.querySelectorAll("[class*='abtestkit-marker-']").forEach((el) => {
          if (!el || !el.classList) {
            return;
          }

          Array.from(el.classList).forEach((className) => {
            if (String(className || "").indexOf("abtestkit-marker-") === 0) {
              el.classList.remove(className);
            }
          });
        });
      } catch (_) {}

      (Array.isArray(previewMarkers) ? previewMarkers : []).forEach((marker) => {
        if (!marker || !marker.selector || !marker.class_name) {
          return;
        }

        try {
          doc.querySelectorAll(marker.selector).forEach((el) => {
            if (el && el.classList) {
              el.classList.add(marker.class_name);
            }
          });
        } catch (_) {}
      });

      const old = doc.getElementById("abtestkit-custom-css-picker-preview-style");
      if (old && old.parentNode) {
        old.parentNode.removeChild(old);
      }

      const css = String(previewCss || "").trim();
      if (css) {
        const style = doc.createElement("style");
        style.id = "abtestkit-custom-css-picker-preview-style";
        style.appendChild(doc.createTextNode(css));
        (doc.head || doc.documentElement).appendChild(style);
      }

      htmlChanges.forEach((change, changeIndex) => {
        const selector = String((change && change.selector) || "").trim();
        const html = String((change && change.html) || "");
        const operation = [
          "replace_contents",
          "insert_before",
          "insert_after",
          "prepend_inside",
          "append_inside",
        ].includes(String((change && change.operation) || "replace_contents"))
          ? String((change && change.operation) || "replace_contents")
          : "replace_contents";
        const matchMode = String((change && change.match_mode) || "all") === "first"
          ? "first"
          : "all";

        if (!selector) {
          nextMatchCounts[changeIndex] = -1;
          return;
        }

        try {
          const matched = Array.from(doc.querySelectorAll(selector));
          const targets = matchMode === "first" ? matched.slice(0, 1) : matched;
          nextMatchCounts[changeIndex] = matched.length;

          targets.forEach((el) => {
            if (!el) {
              return;
            }

            if (operation === "replace_contents") {
              if (typeof el.innerHTML !== "string") {
                return;
              }

              appliedHtml.push({ type: "replace", el, original: el.innerHTML });
              el.innerHTML = html;
              return;
            }

            const template = doc.createElement("template");
            template.innerHTML = html;
            const fragment = template.content
              ? template.content.cloneNode(true)
              : doc.createRange().createContextualFragment(html);
            const insertedNodes = Array.from(fragment.childNodes || []);

            if (operation === "insert_before") {
              if (!el.parentNode) return;
              el.parentNode.insertBefore(fragment, el);
            } else if (operation === "insert_after") {
              if (!el.parentNode) return;
              el.parentNode.insertBefore(fragment, el.nextSibling);
            } else if (operation === "prepend_inside") {
              el.insertBefore(fragment, el.firstChild);
            } else {
              el.appendChild(fragment);
            }

            appliedHtml.push({ type: "insert", nodes: insertedNodes });
          });
        } catch (_) {
          nextMatchCounts[changeIndex] = -1;
        }
      });

      setHtmlMatchCounts(nextMatchCounts);
    };

    frame.addEventListener("load", applyCustomPreview);
    applyCustomPreview();

    return () => {
      frame.removeEventListener("load", applyCustomPreview);
      restoreHtmlPreview();
    };
  }, [
    url,
    previewLoadState,
    previewVariant,
    previewCss,
    JSON.stringify(previewMarkers || []),
    JSON.stringify(previewHtmlChanges || []),
  ]);

  const hasPicks = Array.isArray(selected) && selected.length > 0;
  const buttonLabel =
    previewLoadState === "failed"
      ? "Preview unavailable"
      : previewLoadState !== "loaded"
      ? "Loading preview…"
      : picking
      ? (actionLabel || "Now click your target in the preview above…")
      : hasPicks
      ? (allowAnyElement ? "Select another element" : "Select another")
      : allowAnyElement
      ? "Select an element"
      : "Begin selecting click targets";

  const frameIsInteractive =
    previewLoadState === "loaded" && (picking || !!interactiveWhenNotPicking);
  const isMobilePreview = String(previewMode || "mobile") !== "desktop";
  const previewLabel = isMobilePreview ? "Mobile view" : "Desktop view";
  const desktopViewportWidth = 1440;
  const desktopViewportHeight = 810;
  const mobileViewportWidth = 390;
  const mobileViewportHeight = 620;
  const measuredDesktopWidth =
    previewWrapWidth && previewWrapWidth > 320 ? previewWrapWidth : 720;

  // Desktop mode: keep a real 1440px iframe viewport so responsive
  // themes render desktop menus, then zoom the whole iframe down
  // until it fully fits inside the visible preview box.
  // Guard against first-render/hidden-layout measurements collapsing the iframe.
  const desktopScale = Math.min(1, measuredDesktopWidth / desktopViewportWidth);
  const desktopFrameHeight = Math.max(
    360,
    Math.ceil(desktopViewportHeight * desktopScale)
  );

  return h(Fragment, null, [
    h(
      "div",
      {
        ref: previewWrapRef,
        style: {
          width: "100%",
          maxWidth: "100%",
          minWidth: 0,
          overflow: "hidden",
          position: "relative",
          display: previewLoadFailed ? "flex" : (isMobilePreview ? "flex" : "block"),
          justifyContent: previewLoadFailed || isMobilePreview ? "center" : undefined,
          alignItems: previewLoadFailed ? "center" : undefined,
          height: isMobilePreview
            ? (previewLoadFailed ? mobileViewportHeight + "px" : "auto")
            : desktopFrameHeight + "px",
          border: isMobilePreview
            ? (previewLoadFailed ? "1px solid #dcdcde" : "none")
            : (picking ? "3px solid #2271b1" : "1px solid #dcdcde"),
          borderRadius: isMobilePreview ? undefined : "4px",
          background: "#fff",
          boxSizing: "border-box",
          padding: previewLoadFailed ? "18px" : 0,
        },
      },
      [
        h("iframe", {
          ref: frameRef,
          src: previewLoadFailed ? "about:blank" : url,
          style: {
          position: isMobilePreview ? "static" : "absolute",
          top: isMobilePreview ? undefined : 0,
          left: isMobilePreview ? undefined : 0,
          display: previewLoadFailed ? "none" : "block",
          width: isMobilePreview
            ? mobileViewportWidth + "px"
            : desktopViewportWidth + "px",
          maxWidth: isMobilePreview ? "100%" : "none",
          height: isMobilePreview
            ? mobileViewportHeight + "px"
            : desktopViewportHeight + "px",
          border: isMobilePreview
            ? (picking ? "3px solid #2271b1" : "1px solid #dcdcde")
            : "0",
          borderRadius: "4px",
          background: "#fff",
          pointerEvents: frameIsInteractive ? "auto" : "none",
          boxSizing: "border-box",
          transform: isMobilePreview ? "none" : "scale(" + desktopScale + ")",
          transformOrigin: "top left",
          },
          title: label + " preview - " + previewLabel,
        }),
        previewLoadFailed
          ? h(PreviewLoadFailureCard, {
              previewUrl: url,
              onRetry: retryPreview,
            })
          : null,
      ]
    ),

    Array.isArray(previewHtmlChanges) && previewHtmlChanges.length
      ? h(
          "div",
          {
            style: {
              marginTop: 10,
              padding: "10px 12px",
              background: "#f6f7f7",
              border: "1px solid #dcdcde",
              borderRadius: 6,
              fontSize: 12,
              color: "#50575e",
            },
          },
          previewHtmlChanges.map((change, index) => {
            const count = Object.prototype.hasOwnProperty.call(htmlMatchCounts, index)
              ? htmlMatchCounts[index]
              : null;
            const matchText = count === -1
              ? "Invalid selector"
              : count === null
              ? "Checking selector…"
              : `${count} ${count === 1 ? "match" : "matches"}`;
            const applyText = String((change && change.match_mode) || "all") === "first"
              ? "first match"
              : "all matches";

            return h(
              "div",
              {
                key: `${String((change && change.selector) || "selector")}-${index}`,
                style: { marginTop: index ? 4 : 0 },
              },
              [
                h("code", null, String((change && change.selector) || "—")),
                ` — ${matchText}; applying to ${applyText}`,
              ]
            );
          })
        )
      : null,

    afterPreviewContent
      ? h(
          "div",
          {
            style: {
              marginTop: 12,
            },
          },
          afterPreviewContent
        )
      : null,

    // BUTTON + INFO UNDERNEATH
    h(
      "div",
      {
        style: {
          marginTop: 8,
          display: "flex",
          gap: 8,
          alignItems: "center",
          flexWrap: "wrap",
        },
      },
      [
        h(
          "button",
          {
            type: "button",
            className: "button button-primary button-large abtestkit-picker-button",
            style: {
              padding: "8px 18px",
              fontSize: "14px",
              fontWeight: 600,
            },
            disabled: previewLoadState !== "loaded",
            onClick: () => {
              if (previewLoadState !== "loaded") {
                return;
              }

              setPicking((s) => !s);
            },
          },
          buttonLabel
        ),
        showTargetRefreshButton
          ? h(
              "button",
              {
                type: "button",
                className: "button button-secondary",
                onClick: retryPreview,
              },
              "Refresh target page"
            )
          : null,
        h(
          "span",
          { style: { color: "#757575" } },
          (interactiveWhenNotPicking && !picking && previewLoadState === "loaded"
            ? "Preview is clickable before selecting. Open menus, tabs, accordions, or dropdowns first. "
            : "") +
            "Preview: " + label + " • " + previewLabel + " • " + (allowAnyElement ? "Accepting any element" : "Accepting clickable elements")
        ),
      ]
    ),
  ]);
};


// ─────────────────────────────────────────────────────────────
// PrettyPickedList: shows {label, href, selector} and allows removing
// ─────────────────────────────────────────────────────────────
const PrettyPickedList = ({ picks = [], onRemove }) => {
  const { createElement: h, Fragment } = wp.element;
  if (!picks.length) return null;

  return h("div", { style: { marginTop: 10 } }, [
    h("div", { style: { fontWeight: 600, marginBottom: 6 } }, "Selected targets"),
    h(
      "ul",
      {
        style: {
          listStyle: "none",
          padding: 0,
          margin: 0,
          display: "flex",
          flexDirection: "column",
          gap: 6,
        },
      },
      picks.map((p, i) =>
        h(
          "li",
          {
            key: (p.selector || p.href || "x") + "_" + i,
            style: {
              display: "flex",
              alignItems: "center",
              gap: 8,
              background: "#f6f7f7",
              border: "1px solid #dcdcde",
              borderRadius: 4,
              padding: "6px 8px",
            },
          },
          [
            h("div", null, [
              p.label ? h("strong", null, p.label + " ") : null,
              p.href ? h("code", null, p.href) : null,
              !p.label && !p.href ? h("code", null, p.selector) : null,
              p.selector && (p.label || p.href)
                ? h("div", { style: { color: "#6c7781", fontSize: 12 } }, p.selector)
                : null,
            ]),
            h(
              "button",
              { className: "button button-small", onClick: () => onRemove(p) },
              "× Remove"
            ),
          ]
        )
      )
    ),
  ]);
};

const ClickPreviewModeSelector = ({ value = "mobile", onChange }) => {
  const { createElement: h } = wp.element;
  const mode = String(value || "mobile") === "desktop" ? "desktop" : "mobile";

  const optionStyle = (active) => ({
    display: "inline-flex",
    alignItems: "center",
    gap: 8,
    minHeight: 36,
    padding: "7px 12px",
    borderRadius: 4,
    border: active ? "1px solid #2271b1" : "1px solid #c3c4c7",
    background: active ? "#e5f1fa" : "#ffffff",
    color: active ? "#1d2327" : "#50575e",
    fontWeight: active ? 600 : 500,
    cursor: "pointer",
    boxShadow: active ? "0 0 0 1px #2271b133" : "none",
  });

  const iconStyle = {
    fontSize: 18,
    width: 18,
    height: 18,
    color: "#2271b1",
  };

  return h(
    "div",
    {
      style: {
        marginTop: 14,
        marginBottom: 14,
        padding: "12px 14px",
        background: "#f6f7f7",
        border: "1px solid #dcdcde",
        borderRadius: 8,
      },
    },
    [
      h(
        "div",
        {
          style: {
            display: "flex",
            justifyContent: "space-between",
            alignItems: "center",
            gap: 12,
            flexWrap: "wrap",
          },
        },
        [
          h("div", { style: { minWidth: 260, flex: "1 1 320px" } }, [
            h(
              "div",
              {
                style: {
                  fontSize: 12,
                  fontWeight: 600,
                  color: "#1d2327",
                  textTransform: "uppercase",
                  letterSpacing: "0.04em",
                  marginBottom: 4,
                },
              },
              "Preview size"
            ),
            h(
              "p",
              {
                style: {
                  margin: 0,
                  color: "#6c7781",
                  fontSize: 13,
                  lineHeight: 1.45,
                },
              },
              mode === "desktop"
                ? ""
                : ""
            ),
          ]),

          h(
            "div",
            {
              role: "group",
              "aria-label": "Choose preview size",
              style: {
                display: "inline-flex",
                alignItems: "center",
                gap: 8,
                flexWrap: "wrap",
              },
            },
            [
              h(
                "button",
                {
                  type: "button",
                  onClick: () => onChange && onChange("mobile"),
                  "aria-pressed": mode === "mobile" ? "true" : "false",
                  style: optionStyle(mode === "mobile"),
                },
                [
                  h("span", {
                    className: "dashicons dashicons-smartphone",
                    style: iconStyle,
                  }),
                  h("span", null, "Mobile"),
                ]
              ),
              h(
                "button",
                {
                  type: "button",
                  onClick: () => onChange && onChange("desktop"),
                  "aria-pressed": mode === "desktop" ? "true" : "false",
                  style: optionStyle(mode === "desktop"),
                },
                [
                  h("span", {
                    className: "dashicons dashicons-desktop",
                    style: iconStyle,
                  }),
                  h("span", null, "Desktop"),
                ]
              ),
            ]
          ),
        ]
      ),
    ]
  );
};


  /* Key-value row used in the summary step (readable + dividers) */
  const ListItem = ({ label, value, noBorder = false }) =>
    h(
      "div",
      {
        style: {
          display: "grid",
          gridTemplateColumns: "190px 1fr", // keeps values close to labels on wide screens
          gap: 12,
          alignItems: "start",
          padding: "10px 0",
          borderBottom: noBorder ? "none" : "1px solid #e5e7eb",
        },
      },
      [
        h(
          "div",
          {
            style: {
              color: "#6c7781",
              fontSize: 13,
              lineHeight: "1.35",
              paddingTop: 2,
            },
          },
          label
        ),
        h(
          "div",
          {
            style: {
              minWidth: 0,
              fontSize: 13,
              lineHeight: "1.45",
              wordBreak: "break-word",
            },
          },
          value
        ),
      ]
    );

  /* WordPress-style list table for selecting pages (with simple pagination) */
  const PageTable = ({
    pages,
    selectedId,
    selectedValues = [],
    onSelect,
    onRemove,
    empty = "No pages found.",
    allowLockedSelect = false,
    selectionMode = "radio",
    getItemValue = null,
  }) => {
    const [pageIndex, setPageIndex] = useState(0);
    const pageSize = 25;

    const list = Array.isArray(pages) ? pages : [];
    const total = list.length;
    const totalPages = total ? Math.ceil(total / pageSize) : 1;

    // Clamp page index when results change (e.g. new search)
    useEffect(() => {
      if (pageIndex > 0 && pageIndex > totalPages - 1) {
        setPageIndex(0);
      }
    }, [total]);

    const start = pageIndex * pageSize;
    const end = Math.min(start + pageSize, total);
    const visible = total ? list.slice(start, end) : [];

    const table = h(
      "table",
      { className: "wp-list-table widefat fixed striped", style: { marginTop: 12 } },
      [
        h(
          "thead",
          null,
          h("tr", null, [
            h(
              "th",
              { style: { width: selectionMode === "toggle" ? 70 : 24 } },
              selectionMode === "toggle" ? "Add" : ""
            ),
            h("th", null, "Title"),
            h("th", { style: { width: 160 } }, "Category"),
            h("th", { style: { width: 120 } }, "Status"),
            h("th", { style: { width: 180 } }, "Date"),
          ])
        ),
        h(
          "tbody",
          null,
          visible && visible.length
            ? visible.map((p) => {
                const rowValue =
                  typeof getItemValue === "function" ? String(getItemValue(p) || "") : "";
                const selectedValueSet = new Set(
                  (Array.isArray(selectedValues) ? selectedValues : [])
                    .map((value) => String(value || "").trim())
                    .filter(Boolean)
                );
                const isToggleSelected = rowValue ? selectedValueSet.has(rowValue) : false;
                const isSel =
                  selectionMode === "toggle"
                    ? isToggleSelected
                    : String(selectedId || "") === String(p.id);

                // Mark pages/products that are already in a running test.
                // Some picker contexts, such as destination URL goals, can still safely select them.
                const hasRunningConflict = !!p.in_running_test;
                const isLocked = hasRunningConflict && !allowLockedSelect;
                const statusLabel = hasRunningConflict
                  ? "In running test"
                  : (p.status || "").replace(/^./, (s) => s.toUpperCase());

                return h(
                  "tr",
                  {
                    key: p.id,
                    onClick: () => {
                      if (isLocked) {
                        return;
                      }

                      if (selectionMode === "toggle") {
                        if (!rowValue) {
                          return;
                        }

                        if (isToggleSelected) {
                          if (typeof onRemove === "function") {
                            onRemove(p, rowValue);
                          }
                          return;
                        }

                        if (typeof onSelect === "function") {
                          onSelect(p, rowValue);
                        }
                        return;
                      }

                      if (typeof onSelect === "function") {
                        onSelect(p);
                      }
                    },
                    style: {
                      cursor: isLocked ? "not-allowed" : "pointer",
                      opacity: isLocked ? 0.55 : 1,
                    },
                  },
                  [
                    h(
                      "td",
                      null,
                      selectionMode === "toggle"
                        ? h(
                            "button",
                            {
                              type: "button",
                              className: isToggleSelected
                                ? "button button-small"
                                : "button button-small button-primary",
                              disabled: isLocked || !rowValue,
                              onClick: (e) => {
                                e.preventDefault();
                                e.stopPropagation();

                                if (isLocked || !rowValue) {
                                  return;
                                }

                                if (isToggleSelected) {
                                  if (typeof onRemove === "function") {
                                    onRemove(p, rowValue);
                                  }
                                  return;
                                }

                                if (typeof onSelect === "function") {
                                  onSelect(p, rowValue);
                                }
                              },
                              "aria-label": isToggleSelected
                                ? "Remove destination URL"
                                : "Add destination URL",
                              title: isToggleSelected
                                ? "Remove this URL"
                                : "Add this URL",
                              style: {
                                minWidth: 34,
                                minHeight: 28,
                                fontSize: 18,
                                lineHeight: "20px",
                                padding: "0 8px",
                              },
                            },
                            isToggleSelected ? "−" : "+"
                          )
                        : h("input", {
                            type: "radio",
                            checked: isSel,
                            disabled: isLocked,
                            onChange: () => {
                              if (!isLocked) onSelect(p);
                            },
                          })
                    ),
                    h("td", null, h("strong", null, decodeEntities(p.title || "(no title)"))),
                    h("td", null, p.category || "—"),
                    h("td", null, statusLabel),
                    h("td", null, p.date || "—"),
                  ]
                );
              })
            : h(
                "tr",
                null,
                h(
                  "td",
                  {
                    colSpan: 5,
                    style: { padding: "12px 10px", color: "#6c7781" },
                  },
                  empty
                )
              )
        ),
      ]
    );

const pager =
  totalPages > 1
    ? (() => {
        // Build a compact page number strip (max 7 buttons)
        const maxButtons = 7;
        const half = Math.floor(maxButtons / 2);

        let startPage = Math.max(0, pageIndex - half);
        let endPage = Math.min(totalPages - 1, startPage + (maxButtons - 1));
        startPage = Math.max(0, endPage - (maxButtons - 1));

        const pageButtons = [];
        for (let i = startPage; i <= endPage; i++) {
          const active = i === pageIndex;
          pageButtons.push(
            h(
              Button,
              {
                key: "p" + i,
                isSmall: true,
                isPrimary: active,
                isSecondary: !active,
                onClick: () => setPageIndex(i),
              },
              String(i + 1)
            )
          );
        }

        return h(
          "div",
          {
            style: {
              marginTop: 8,
              display: "flex",
              justifyContent: "space-between",
              alignItems: "center",
              fontSize: 12,
              color: "#6c7781",
              gap: 10,
              flexWrap: "wrap",
            },
          },
          [
            h("span", null, `Showing ${start + 1}–${end} of ${total}`),

            h(
              "div",
              { style: { display: "flex", gap: 4, alignItems: "center", flexWrap: "wrap" } },
              [
                h(
                  Button,
                  {
                    isSmall: true,
                    disabled: pageIndex === 0,
                    onClick: () => setPageIndex(0),
                  },
                  "First"
                ),

                h(
                  Button,
                  {
                    isSmall: true,
                    disabled: pageIndex === 0,
                    onClick: () => setPageIndex((i) => Math.max(0, i - 1)),
                  },
                  "Previous"
                ),

                ...pageButtons,

                h(
                  Button,
                  {
                    isSmall: true,
                    disabled: pageIndex >= totalPages - 1,
                    onClick: () => setPageIndex((i) => Math.min(totalPages - 1, i + 1)),
                  },
                  "Next"
                ),

                h(
                  Button,
                  {
                    isSmall: true,
                    disabled: pageIndex >= totalPages - 1,
                    onClick: () => setPageIndex(totalPages - 1),
                  },
                  "Last"
                ),
              ]
            ),
          ]
        );
      })()
    : null;

    return h("div", null, [table, pager]);
  };


  // Small iframe preview used on the "Build Version B" step
const PreviewPane = ({ pageId, label, viewBase, extraQuery = "", rawUrl = "" }) => {
  const { useEffect, useRef, useState } = wp.element;
  const frameRef = useRef(null);
  const [refreshNonce, setRefreshNonce] = useState(0);
  const [previewLoadState, setPreviewLoadState] = useState("loading");

  if (!pageId && !rawUrl) return null;

  const safeExtra =
    typeof extraQuery === "string" && extraQuery.trim() !== ""
      ? extraQuery.trim()
      : "";

  const baseSrc =
    typeof rawUrl === "string" && rawUrl.trim() !== ""
      ? rawUrl.trim()
      : `${viewBase}${pageId}&abtestkit_preview=1`;

  const srcWithoutRetryNonce = `${baseSrc}${safeExtra}`; // preview disables redirects/tracking
  const src = refreshNonce
    ? abtkSetPreviewQueryParam(srcWithoutRetryNonce, "abtestkit_r", refreshNonce)
    : srcWithoutRetryNonce;
  const previewLoadFailed = previewLoadState === "failed";

  const retryPreview = () => {
    setPreviewLoadState("loading");
    setRefreshNonce(Date.now());
  };

  useEffect(() => {
    const frame = frameRef.current;

    setPreviewLoadState(src ? "loading" : "idle");

    if (!frame || !src) {
      return;
    }

    let settled = false;

    const onLoad = () => {
      if (settled) {
        return;
      }

      settled = true;
      window.clearTimeout(timer);
      setPreviewLoadState("loaded");
    };

    const timer = window.setTimeout(() => {
      if (settled) {
        return;
      }

      settled = true;
      frame.removeEventListener("load", onLoad);
      setPreviewLoadState("failed");

      try {
        frame.src = "about:blank";
      } catch (_) {}

      abtkRecordPreviewLoadFailure({
        postId: pageId,
        url: src,
        label,
        context: "version_preview",
      });
    }, ABTK_PREVIEW_LOAD_TIMEOUT_MS);

    frame.addEventListener("load", onLoad, { once: true });

    try {
      const doc = frame.contentDocument;
      const href =
        frame.contentWindow && frame.contentWindow.location
          ? String(frame.contentWindow.location.href || "")
          : "";

      if (doc && doc.readyState === "complete" && href && href !== "about:blank") {
        onLoad();
      }
    } catch (_) {}

    return () => {
      settled = true;
      window.clearTimeout(timer);
      frame.removeEventListener("load", onLoad);
    };
  }, [src, pageId, label]);

  return h(
    "div",
    { style: { display: "grid", gap: 8 } },
    [
      label
        ? h(
            "div",
            { style: { fontWeight: 600 } },
            label
          )
        : null,
      h(
        "div",
        {
          style: {
            minHeight: "460px",
            border: "1px solid #dcdcde",
            borderRadius: "4px",
            background: "#fff",
            display: previewLoadFailed ? "flex" : "block",
            alignItems: previewLoadFailed ? "center" : undefined,
            justifyContent: previewLoadFailed ? "center" : undefined,
            padding: previewLoadFailed ? "18px" : 0,
            boxSizing: "border-box",
          },
        },
        [
          h("iframe", {
            ref: frameRef,
            src: previewLoadFailed ? "about:blank" : src,
            title: label || "Preview",
            style: {
              display: previewLoadFailed ? "none" : "block",
              width: "100%",
              height: "460px",
              border: 0,
              borderRadius: "4px",
              background: "#fff",
            },
          }),
          previewLoadFailed
            ? h(PreviewLoadFailureCard, {
                previewUrl: src,
                onRetry: retryPreview,
              })
            : null,
        ]
      ),
    ]
  );
};

const abtestkitCssEscape =
  (window.CSS && CSS.escape)
    ? CSS.escape
    : (s) => String(s || "").replace(/[^a-zA-Z0-9_-]/g, "-");

const customCssMarkerClassFromLabel = (label) => {
  const slug = String(label || "")
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "");

  return "abtestkit-marker-" + (slug || "marker");
};

const customCssStarterForMarkers = (markers) => {
  const list = Array.isArray(markers) ? markers : [];

  return list
    .map((marker) => marker && marker.class_name ? `.${marker.class_name} {\n\n}` : "")
    .filter(Boolean)
    .join("\n\n");
};

const appendQueryParams = (url, params = {}) => {
  let base = String(url || "").trim();

  if (!base) {
    return "";
  }

  Object.keys(params || {}).forEach((key) => {
    const value = params[key];

    if (value === undefined || value === null || value === "") {
      return;
    }

    if (base.indexOf(`${encodeURIComponent(key)}=`) !== -1) {
      return;
    }

    base +=
      (base.indexOf("?") === -1 ? "?" : "&") +
      `${encodeURIComponent(key)}=${encodeURIComponent(String(value))}`;
  });

  return base;
};

const CustomCssPreviewPane = ({
  rawUrl = "",
  label = "Preview",
  variant = "A",
  customCss = "",
  markers = [],
}) => {
  const { createElement: h, useEffect, useRef } = wp.element;
  const frameRef = useRef(null);

  const src = appendQueryParams(rawUrl, {
    abtestkit_preview: "1",
    abtestkit_force: variant,
    abtestkit_r: Date.now(),
  });

  useEffect(() => {
    const frame = frameRef.current;
    if (!frame || !src) {
      return;
    }

    const applyPreview = () => {
      if (variant !== "B") {
        return;
      }

      let doc;
      try {
        doc = frame.contentDocument || (frame.contentWindow && frame.contentWindow.document);
      } catch (_) {
        return;
      }

      if (!doc || !doc.documentElement) {
        return;
      }

      if (doc.body && doc.body.classList) {
        doc.body.classList.add("abtestkit-custom-css-b");
      }

      (Array.isArray(markers) ? markers : []).forEach((marker) => {
        if (!marker || !marker.selector || !marker.class_name) {
          return;
        }

        try {
          doc.querySelectorAll(marker.selector).forEach((el) => {
            if (el && el.classList) {
              el.classList.add(marker.class_name);
            }
          });
        } catch (_) {}
      });

      const css = String(customCss || "").trim();
      if (css) {
        const old = doc.getElementById("abtestkit-custom-css-preview-style");
        if (old && old.parentNode) {
          old.parentNode.removeChild(old);
        }

        const style = doc.createElement("style");
        style.id = "abtestkit-custom-css-preview-style";
        style.appendChild(doc.createTextNode(css));
        (doc.head || doc.documentElement).appendChild(style);
      }
    };

    frame.addEventListener("load", applyPreview);
    applyPreview();

    return () => frame.removeEventListener("load", applyPreview);
  }, [src, variant, customCss, JSON.stringify(markers || [])]);

  if (!src) {
    return null;
  }

  return h("div", { style: { display: "grid", gap: 8 } }, [
    label ? h("div", { style: { fontWeight: 600 } }, label) : null,
    h("iframe", {
      ref: frameRef,
      src,
      title: label || "Preview",
      style: {
        width: "100%",
        height: "460px",
        border: "1px solid #dcdcde",
        borderRadius: "4px",
        background: "#fff",
      },
    }),
  ]);
};

function destinationTargetFromEntity(entity) {
    if (!entity || typeof entity !== "object") return "";

    const raw = String(entity.permalink || entity.preview_url || "").trim();

    if (!raw) return "";

    try {
      const parsed = new URL(raw, window.location.origin);

      [
        "abtestkit_preview",
        "abtestkit_force",
        "abtestkit_shadow_preview_id",
        "abtestkit_r",
        "abtestkit_token",
      ].forEach((key) => parsed.searchParams.delete(key));

      parsed.hash = "";

      const path = parsed.pathname.replace(/\/+$/, "") || "/";
      const target = path + (parsed.search || "");

      // Selected destinations are saved as prefix matches by default so
      // confirmation pages with query strings, such as /thank-you?order=123,
      // still count. Never wildcard the homepage/root.
      return target === "/" ? "/" : target + "*";
    } catch (_) {
      const clean = raw
        .replace(/^https?:\/\/[^/]+/i, "")
        .replace(/[?#].*$/, "")
        .replace(/\/+$/, "") || "/";

      return clean === "/" ? "/" : clean + "*";
    }
  }

function fetchPages(postType, q) {
    if (!postType) return Promise.resolve([]);

    const allowedTypes = ["page", "post", "product", "reusable_section"];
    const safeType = allowedTypes.includes(postType) ? postType : "page";
    const typeParam = `&type=${safeType}`;

    return apiFetch({
      path: `/abtestkit/v1/pt/pages?q=${encodeURIComponent(
        q || ""
      )}${typeParam}`,
      headers: { "X-WP-Nonce": cfg.nonce },
    }).then((r) => {
      const list = Array.isArray(r && r.pages) ? r.pages : [];

      return list.filter((p) => {
        if (!p) return false;

        const actualType = String(p.post_type || "").toLowerCase();
        const isProduct =
          !!p.product ||
          actualType === "product" ||
          actualType === "product_variation";

        if (safeType === "product") {
          return isProduct;
        }

        if (safeType === "post") {
          return !isProduct && actualType === "post";
        }

        // For pages, allow anything that is clearly not a product and not a post.
        // This keeps products out, keeps posts out, and avoids live-site payload quirks
        // where page items do not come through as a clean "page" string every time.
        return !isProduct && actualType !== "post";
      });
    });
  }

  function Wizard() {
    const [step, setStep] = useState(0);

    // "Page type" selection: 'page' | 'product'
    const [postType, setPostType] = useState(""); // required on first step

    // Hard reset for anything downstream of test type selection.
    // This prevents "state leaking" (e.g. Version B sticking around across runs).
    const resetWizardState = () => {
      // Lists/search
      setPageSearch("");
      setPages([]);
      setLoading(false);
      setError("");

      // A/B selection
      setPageA(null);
      setTestTitle("");
      testTitleManuallyEditedRef.current = false;
      setBMode("duplicate");
      setSeoSafeExistingB(true);
      setPageBSearch("");
      setPagesB([]);
      setPageB(null);
      setHasEditedB(false);
      setTempBDraftId(null);
      setRequiresBEdit(false);

      // Goal / conversions
      setGoal("");
      setConversionChosen(false);
      setClickScope("on_test_pages");
      setClickPreviewMode("mobile");
      setScrollDepth("50");

      // Click targets
      setLinks("");
      setPrettyPicks([]);
      setShowManualTargets(false);
      setGoalPageSearch("");
      setGoalPages([]);
      setGoalPage(null);
      setDestinationPostType("page");

      // Custom Code
      setCustomCodeType("");
      setCssScope("page");
      setCustomCss("");
      setCssMarkers([]);
      setCssPreviewMode("desktop");
      setCustomHtmlChanges([]);
      setManualHtmlSelector("");
      setHtmlPreviewMode("desktop");

      // Product overrides
      setProductPreviewToken("");
      setProductBTitle("");
      setProductBPrice("");
      setProductBSalePrice("");
      setProductBShortDesc("");
      setProductBLongDesc("");
      setProductBImageUrl("");
      setProductBImageId("");
      setProductBGalleryUrls("");
      setProductBGalleryIds([]);
      setShowProductBTitle(false);
      setShowProductBPrice(false);
      setShowProductBSalePrice(false);
      setShowProductBShortDesc(false);
      setShowProductBLongDesc(false);
      setShowProductBImage(false);
      setShowProductBGallery(false);

      // Reset “prefill from A” flags
      shortHydratedRef.current = false;
      longHydratedRef.current = false;
    };

    const selectTestType = (type) => {
      if (String(type) === String(postType) && step === 0) {
        return;
      }

      const hasProgressToLose =
        !!postType &&
        (
          !!pageA ||
          !!pageB ||
          !!tempBDraftId ||
          !!hasEditedB ||
          String(testTitle || "").trim() !== "" ||
          String(goal || "").trim() !== "" ||
          !!conversionChosen ||
          String(links || "").trim() !== "" ||
          !!productPreviewToken ||
          String(productBTitle || "").trim() !== "" ||
          String(productBPrice || "").trim() !== "" ||
          String(productBSalePrice || "").trim() !== "" ||
          String(productBShortDesc || "").trim() !== "" ||
          String(productBLongDesc || "").trim() !== "" ||
          String(productBImageUrl || "").trim() !== "" ||
          String(productBGalleryUrls || "").trim() !== "" ||
          String(customCss || "").trim() !== "" ||
          (Array.isArray(cssMarkers) && cssMarkers.length > 0) ||
          (Array.isArray(customHtmlChanges) && customHtmlChanges.length > 0)
        );

      if (!hasProgressToLose) {
        resetWizardState();
        setPostType(type);
        setStep(0);
        return;
      }

      const ok = window.confirm(
        "Changing the test type will clear this setup.\n\nAny Version B shadow created for this wizard will be deleted and all progress will be lost.\n\nPress OK to continue."
      );

      if (!ok) return;

      cleanupWizardDuplicate({ silent: true }).finally(() => {
        autoCreatedBRef.current = false;
        resetWizardState();
        setPostType(type);
        setStep(0);

        tlmSend("pt_wizard_action", {
          action: "reset_on_type_change",
          value: 1,
          step: tlmStepKey(),
        });

        setTimeout(() => {
          window.scrollTo({ top: 0, behavior: "auto" });
        }, 0);
      });
    };

    // Data
    const [pageSearch, setPageSearch] = useState("");
    const [pages, setPages] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState("");

    const [pageA, setPageA] = useState(null);
    const [testTitle, setTestTitle] = useState("");
    const testTitleManuallyEditedRef = useRef(false);

    const [bMode, setBMode] = useState("duplicate"); // 'duplicate' | 'existing'

    // Existing-B SEO protection (recommended): noindex + canonical-to-A while used in a test.
    const [seoSafeExistingB, setSeoSafeExistingB] = useState(true);
    const [pageBSearch, setPageBSearch] = useState("");
    const [pagesB, setPagesB] = useState([]);
    const [pageB, setPageB] = useState(null);
    const [hasEditedB, setHasEditedB] = useState(false);
    const [tempBDraftId, setTempBDraftId] = useState(null);
    const [requiresBEdit, setRequiresBEdit] = useState(false);

    // Used to force-refresh the Version B preview iframe (existing-mode only)
    const [previewBNonce, setPreviewBNonce] = useState(0);

    const [goal, setGoal] = useState("");
    const [scrollDepth, setScrollDepth] = useState("50");

    const [customCodeType, setCustomCodeType] = useState("");
    const [cssScope, setCssScope] = useState("page");
    const [customCss, setCustomCss] = useState("");
    const [cssMarkers, setCssMarkers] = useState([]);
    const [customHtmlChanges, setCustomHtmlChanges] = useState([]);
    const [manualHtmlSelector, setManualHtmlSelector] = useState("");

    // Decision rule (min impressions + min conversions, or manual)
    // fast     = 25 impressions + 3 conversions
    // balanced = 50 impressions + 5 conversions
    // precise  = 75 impressions + 10 conversions
    // manual   = never auto-declares a winner
    const [decisionRule, setDecisionRule] = useState("balanced");

    const [productPreviewToken, setProductPreviewToken] = useState("");
    const [links, setLinks] = useState(""); // comma-separated
    const [prettyPicks, setPrettyPicks] = useState([]);
    const [showManualTargets, setShowManualTargets] = useState(false);
    const [conversionChosen, setConversionChosen] = useState(false);

    // Clicks: where do we track them?
    // "on_test_pages" → Version A/B
    // "other_page"    → a different page you pick
    const [clickScope, setClickScope] = useState("on_test_pages");
    const [clickPreviewMode, setClickPreviewMode] = useState("mobile");
    const [cssPreviewMode, setCssPreviewMode] = useState("desktop");
    const [htmlPreviewMode, setHtmlPreviewMode] = useState("desktop");

    const isCustomCode = postType === "custom_code";
    const isCustomCss = isCustomCode && customCodeType === "custom_css";
    const isCustomHtml = isCustomCode && customCodeType === "custom_html";
    const customCodeTestType = isCustomCss
      ? "custom_css"
      : isCustomHtml
      ? "custom_html"
      : "";

    const [showWizardCompatibilityHelp, setShowWizardCompatibilityHelp] = useState(false);
    const [wizardCompatibilityMessage, setWizardCompatibilityMessage] = useState("");
    const [wizardCompatibilitySubmitting, setWizardCompatibilitySubmitting] = useState(false);
    const [wizardCompatibilityStatus, setWizardCompatibilityStatus] = useState(null);

    // Pick click targets on a different page, or choose a destination URL from site content
    const [goalPageSearch, setGoalPageSearch] = useState("");
    const [goalPages, setGoalPages] = useState([]);
    const [goalPageLoading, setGoalPageLoading] = useState(false);
    const [goalPage, setGoalPage] = useState(null);
    const [destinationPostType, setDestinationPostType] = useState("page");

    // Product-only: Version B overrides entered on the "Build Version B" step
    const [productBTitle, setProductBTitle] = useState("");
    const [productBPrice, setProductBPrice] = useState("");
    const [productBSalePrice, setProductBSalePrice] = useState("");
    const [productBShortDesc, setProductBShortDesc] = useState("");
    const [productBLongDesc, setProductBLongDesc] = useState("");
    const [productBImageUrl, setProductBImageUrl] = useState("");
    const [productBImageId, setProductBImageId] = useState("");
    const [productBGalleryUrls, setProductBGalleryUrls] = useState("");
    const [productBGalleryIds, setProductBGalleryIds] = useState("");

    const [showProductBTitle, setShowProductBTitle] = useState(false);
    const [showProductBPrice, setShowProductBPrice] = useState(false);
    const [showProductBSalePrice, setShowProductBSalePrice] = useState(false);
    const [showProductBShortDesc, setShowProductBShortDesc] = useState(false);
    const [showProductBLongDesc, setShowProductBLongDesc] = useState(false);
    const [showProductBImage, setShowProductBImage] = useState(false);
    const [showProductBGallery, setShowProductBGallery] = useState(false);

    // Track whether we've already pre-filled Version B descriptions from Version A
    const shortHydratedRef = useRef(false);
    const longHydratedRef = useRef(false);

    const clearClickTargetState = () => {
      setLinks("");
      setPrettyPicks([]);
      setShowManualTargets(false);
      setGoalPageSearch("");
      setGoalPages([]);
      setGoalPage(null);
      setClickPreviewMode("mobile");
    };

    const clearCustomCodeState = () => {
      setCustomCss("");
      setCssMarkers([]);
      setCssPreviewMode("desktop");
      setCustomHtmlChanges([]);
      setManualHtmlSelector("");
      setHtmlPreviewMode("desktop");
    };

    const clearProductBState = () => {
      setProductPreviewToken("");
      setProductBTitle("");
      setProductBPrice("");
      setProductBSalePrice("");
      setProductBShortDesc("");
      setProductBLongDesc("");
      setProductBImageUrl("");
      setProductBImageId("");
      setProductBGalleryUrls("");
      setProductBGalleryIds([]);
      setShowProductBTitle(false);
      setShowProductBPrice(false);
      setShowProductBSalePrice(false);
      setShowProductBShortDesc(false);
      setShowProductBLongDesc(false);
      setShowProductBImage(false);
      setShowProductBGallery(false);
      shortHydratedRef.current = false;
      longHydratedRef.current = false;
    };

    const clearVersionBAndDownstream = () => {
      setError("");
      setPageBSearch("");
      setPageB(null);
      setHasEditedB(false);
      setTempBDraftId(null);
      setRequiresBEdit(false);
      setGoal("");
      setConversionChosen(false);
      setClickScope("on_test_pages");
      setScrollDepth("50");
      clearClickTargetState();
      clearCustomCodeState();
      clearProductBState();
    };

    const resetToControlSelection = () => {
      setError("");
      setPageA(null);
      setTestTitle("");
      testTitleManuallyEditedRef.current = false;
      setBMode("duplicate");
      setSeoSafeExistingB(true);
      clearVersionBAndDownstream();
    };

    const cleanupWizardDuplicate = ({ silent = false } = {}) => {
      const controlId = pageA && pageA.id ? pageA.id : 0;
      const variantId =
        tempBDraftId || (pageB && pageB.id ? pageB.id : 0);

      if (bMode !== "duplicate" || !controlId || !variantId) {
        return Promise.resolve({ ok: true, deleted: 0 });
      }

      // Pages/posts already had a working direct-delete flow.
      // Keep products on the custom endpoint because their shadow handling is stricter.
      const isDirectDeleteType = postType === "page" || postType === "post";

      if (isDirectDeleteType) {
        const corePath =
          postType === "page"
            ? `/wp/v2/pages/${variantId}`
            : `/wp/v2/posts/${variantId}`;

        return apiFetch({
          path: corePath,
          method: "DELETE",
          headers: {
            "X-WP-Nonce": cfg.nonce,
          },
          data: {
            force: true,
          },
        })
          .then(() => {
            return { ok: true, deleted: variantId };
          })
          .catch(() => {
            // Fallback to the custom cleanup route if core delete fails for any reason.
            return apiFetch({
              path: "/abtestkit/v1/pt/cleanup-duplicate",
              method: "POST",
              headers: {
                "X-WP-Nonce": cfg.nonce,
                "Content-Type": "application/json",
              },
              data: {
                control_id: controlId,
                variant_id: variantId,
              },
            }).then((res) => {
              if (!res || !res.ok) {
                throw new Error((res && res.error) || "cleanup_failed");
              }
              return res;
            });
          })
          .catch((e) => {
            if (!silent) {
              setError(
                e && e.message
                  ? e.message
                  : "Couldn’t clean up the Version B shadow."
              );
            }
            return { ok: false, error: (e && e.message) || "cleanup_failed" };
          });
      }

      return apiFetch({
        path: "/abtestkit/v1/pt/cleanup-duplicate",
        method: "POST",
        headers: {
          "X-WP-Nonce": cfg.nonce,
          "Content-Type": "application/json",
        },
        data: {
          control_id: controlId,
          variant_id: variantId,
        },
      })
        .then((res) => {
          if (!res || !res.ok) {
            throw new Error((res && res.error) || "cleanup_failed");
          }
          return res;
        })
        .catch((e) => {
          if (!silent) {
            setError(
              e && e.message
                ? e.message
                : "Couldn’t clean up the Version B shadow."
            );
          }
          return { ok: false, error: (e && e.message) || "cleanup_failed" };
        });
    };

    // ─────────────────────────────────────────────────────────────
    // Wizard telemetry (session + friction)
    // ─────────────────────────────────────────────────────────────
    const ABTK_WIZ_UI = "pt-wizard-1.2.0";
    const ABTK_WIZ_LS_KEY = "abtk_pt_wizard_session";

    const tlmSessionIdRef = useRef("");
    const tlmStartedAtRef = useRef(0);
    const tlmNavDirRef = useRef("start"); // start|next|back|jump
    const tlmProgressRef = useRef({});

    const tlmMs = () => abtkSafeInt(Date.now() - (tlmStartedAtRef.current || Date.now()), 0, 86400000);

    const tlmDecisionMode = () => (String(decisionRule || "") === "manual" ? "manual" : "auto");

    const tlmLinksCount = () =>
      (links || "")
        .split(",")
        .map((s) => s.trim())
        .filter(Boolean).length;

    const tlmStepKey = () => {
      // Stable step keys (do NOT depend on step titles)
      if (step === 0) return "select_type";

      if (isCustomCode) {
        if (step === 1) return "select_custom_code_type";
        if (step === 2) return "select_control";
        if (step === 3) return "review_versions";
        if (step === 4) return "choose_conversion_type";
        if ((goal === "clicks" || goal === "destination_url" || goal === "scroll_depth") && step === 5) {
          if (goal === "destination_url") return "set_destination_url";
          if (goal === "scroll_depth") return "set_scroll_depth";
          return "select_click_targets";
        }
        return "summary";
      }

      if (step === 1) return "select_control";

      if (postType === "product") {
        if (step === 2) return "review_versions";
        if (step === 3) return "choose_conversion_type";
      if ((goal === "clicks" || goal === "destination_url" || goal === "scroll_depth") && step === 4) {
          if (goal === "destination_url") return "set_destination_url";
          if (goal === "scroll_depth") return "set_scroll_depth";
          return "select_click_targets";
        }
        return "summary";
      }

      // pages/posts
      if (step === 2) return "version_b_source";
      if (step === 3) return "review_versions";
      if (step === 4) return "choose_conversion_type";
      if ((goal === "clicks" || goal === "destination_url" || goal === "scroll_depth") && step === 5) {
        if (goal === "destination_url") return "set_destination_url";
        if (goal === "scroll_depth") return "set_scroll_depth";
        return "select_click_targets";
      }
      return "summary";
    };

    /*
     * Local, privacy-safe progress used only if this administrator later
     * explicitly submits deactivation feedback. Keep content, titles, post
     * IDs, URLs, selectors and custom code out of this object.
     */
    tlmProgressRef.current = {
      ui: ABTK_WIZ_UI,
      step: tlmStepKey(),
      step_index: abtkSafeInt(step, 0, 50),
      kind: String(postType || ""),
      custom_code_type: String(customCodeType || ""),
      scope: String(isCustomCode ? cssScope : postType || ""),
      b_mode: String(isCustomCode ? customCodeTestType : bMode || ""),
      has_control: pageA && pageA.id ? 1 : 0,
      has_variant:
        (pageB && pageB.id) ||
        (isCustomCss && String(customCss || "").trim()) ||
        (isCustomHtml && customHtmlChanges.length)
          ? 1
          : 0,
      has_temp_variant: tempBDraftId ? 1 : 0,
      edited_variant: hasEditedB ? 1 : 0,
      seo_safe_existing_b: bMode === "existing" ? (seoSafeExistingB ? 1 : 0) : 1,
      goal: String(goal || ""),
      conversion_chosen: conversionChosen ? 1 : 0,
      click_scope: String(clickScope || ""),
      links_count: tlmLinksCount(),
      scroll_depth: abtkSafeInt(scrollDepth, 0, 100),
      decision_mode: tlmDecisionMode(),
      decision_rule: String(decisionRule || ""),
      custom_css_length: String(customCss || "").length,
      css_marker_count: Array.isArray(cssMarkers) ? cssMarkers.length : 0,
      html_change_count: Array.isArray(customHtmlChanges) ? customHtmlChanges.length : 0,
      has_error: error ? 1 : 0,
      product_title_changed: showProductBTitle ? 1 : 0,
      product_price_changed: showProductBPrice ? 1 : 0,
      product_sale_price_changed: showProductBSalePrice ? 1 : 0,
      product_short_description_changed: showProductBShortDesc ? 1 : 0,
      product_long_description_changed: showProductBLongDesc ? 1 : 0,
      product_image_changed: showProductBImage ? 1 : 0,
      product_gallery_changed: showProductBGallery ? 1 : 0,
    };

    const tlmBase = () => ({
      session_id: tlmSessionIdRef.current,
      ms: tlmMs(),

      // Receiver already has columns for these
      kind: String(postType || ""),
      goal: String(goal || ""),
      decision_mode: tlmDecisionMode(),
      decision_rule: String(decisionRule || ""),

      // Useful context for analysis (still anonymous)
      b_mode: String(bMode || ""),
      seo_safe_existing_b: bMode === "existing" ? (seoSafeExistingB ? 1 : 0) : 1,

      // Counts only (no URLs/titles)
      links_count: tlmLinksCount(),
      has_b: pageB ? 1 : 0,
      edited_b: hasEditedB ? 1 : 0,
      conversion_chosen: conversionChosen ? 1 : 0,
      click_scope: String(clickScope || ""),
    });

    const tlmSend = (event, extra = {}) =>
      abtkSendTelemetry(event, { ...tlmBase(), ...(extra && typeof extra === "object" ? extra : {}) });

    const openWizardCompatibilityHelpModal = () => {
      setWizardCompatibilityStatus(null);
      setShowWizardCompatibilityHelp(true);
    };

    const closeWizardCompatibilityHelpModal = () => {
      if (wizardCompatibilitySubmitting) return;
      setShowWizardCompatibilityHelp(false);
      setWizardCompatibilityStatus(null);
    };

    const submitWizardCompatibilityHelp = () => {
      if (wizardCompatibilitySubmitting) return;

      setWizardCompatibilitySubmitting(true);
      setWizardCompatibilityStatus(null);

      tlmSend("pt_wizard_compatibility_help_request", {
        source: "pt_wizard_summary",
        message: String(wizardCompatibilityMessage || "").slice(0, 1500),
        has_preview_a: previewUrlA ? 1 : 0,
        has_preview_b: previewUrlB ? 1 : 0,
        has_control: pageA ? 1 : 0,
        has_variant: pageB ? 1 : 0,
        click_target_count: tlmLinksCount(),
      })
        .then(() => {
          setWizardCompatibilityStatus("sent");
          setWizardCompatibilityMessage("");
          setWizardCompatibilitySubmitting(false);
        })
        .catch(() => {
          setWizardCompatibilityStatus("error");
          setWizardCompatibilitySubmitting(false);
        });
    };

    const wizardCompatibilityHelpLink = () =>
      h(
        "button",
        {
          type: "button",
          onClick: (e) => {
            e.preventDefault();
            e.stopPropagation();
            openWizardCompatibilityHelpModal();
          },
          style: {
            border: 0,
            background: "transparent",
            padding: 0,
            margin: 0,
            color: "#2271b1",
            cursor: "pointer",
            fontSize: 13,
            fontWeight: 500,
            lineHeight: 1.4,
            textDecoration: "underline",
            textUnderlineOffset: 2,
            whiteSpace: "nowrap",
          },
        },
        "Something not right?"
      );

    const tlmPersist = (patch = {}) => {
      try {
        const curRaw = window.localStorage.getItem(ABTK_WIZ_LS_KEY);
        const cur = curRaw ? JSON.parse(curRaw) : {};
        const progress = tlmProgressRef.current || {};
        const currentStepIndex = abtkSafeInt(progress.step_index, 0, 50);
        const sameSession =
          cur.session_id &&
          String(cur.session_id) === String(tlmSessionIdRef.current || "");
        const priorFurthestIndex = sameSession
          ? abtkSafeInt(cur.furthest_step_index, 0, 50)
          : 0;
        const reachedNewFurthest =
          !sameSession || !cur.furthest_step || currentStepIndex > priorFurthestIndex;

        // IMPORTANT: spread `cur` first so it can’t overwrite the current session_id
        const next = {
          ...cur,
          ...progress,

          session_id: tlmSessionIdRef.current,
          ui: ABTK_WIZ_UI,
          last_seen: Date.now(),
          step: tlmStepKey(),
          furthest_step: reachedNewFurthest
            ? String(progress.step || tlmStepKey())
            : String(cur.furthest_step || ""),
          furthest_step_index: reachedNewFurthest
            ? currentStepIndex
            : priorFurthestIndex,
          ms: tlmMs(),
          completed: false,
          kind: String(postType || ""),
          goal: String(goal || ""),

          ...patch,
        };

        window.localStorage.setItem(ABTK_WIZ_LS_KEY, JSON.stringify(next));
      } catch (_) {}
    };

    // On mount: close stale session as "abandoned", start a new session, emit session_start
    useEffect(() => {
      if (tlmSessionIdRef.current) return;

      const now = Date.now();

      // If a prior session exists and is stale, mark it abandoned
      try {
        const prevRaw = window.localStorage.getItem(ABTK_WIZ_LS_KEY);
        const prev = prevRaw ? JSON.parse(prevRaw) : null;

        if (
          prev &&
          prev.session_id &&
          !prev.completed &&
          prev.last_seen &&
          now - Number(prev.last_seen) > 30 * 60 * 1000 // 30 min stale = abandoned
        ) {
          abtkSendTelemetry("pt_wizard_result", {
            session_id: String(prev.session_id),
            result: "abandoned",
            step: String(prev.step || ""),
            ms: abtkSafeInt(prev.ms || 0, 0, 86400000),
            kind: String(prev.kind || ""),
            goal: String(prev.goal || ""),
          });
        }
      } catch (_) {}

      tlmSessionIdRef.current = abtkMakeSessionId();
      tlmStartedAtRef.current = now;

      // Existing one-shot wizard milestone (opt-in gated in PHP helper)
      abtkSendTelemetry("pt_wizard_opened", {});

      // New: session start (per-open)
      tlmSend("pt_wizard_session_start", { ui: ABTK_WIZ_UI, step: tlmStepKey() });

      tlmPersist({ completed: false });
    }, []);

    useEffect(() => {
      if (!tlmSessionIdRef.current) return;
      const t = setInterval(() => {
        tlmPersist({});
      }, 20000); // every 20s
      return () => clearInterval(t);
    }, []);

    // Step view event (fires whenever step changes)
    useEffect(() => {
      if (!tlmSessionIdRef.current) return;

      const stepKey = tlmStepKey();
      const dir = String(tlmNavDirRef.current || "jump");

      tlmSend("pt_wizard_step", {
        step: stepKey,
        step_index: abtkSafeInt(step, 0, 50),
        direction: dir,
      });

      tlmNavDirRef.current = "jump";
      tlmPersist({ step: stepKey });
    }, [step]);

    // Action breadcrumbs (lightweight, change-based)
    const tlmPrevRef = useRef({
      postType: "",
      pageA: 0,
      bMode: "",
      pageB: 0,
      hasEditedB: 0,
      goal: "",
      clickScope: "",
      linksCount: 0,
      seoSafe: 1,
      decisionRule: "",
    });

    useEffect(() => {
      const cur = {
        postType: String(postType || ""),
        pageA: pageA && pageA.id ? Number(pageA.id) : 0,
        bMode: String(bMode || ""),
        pageB: pageB && pageB.id ? Number(pageB.id) : 0,
        hasEditedB: hasEditedB ? 1 : 0,
        goal: String(goal || ""),
        clickScope: String(clickScope || ""),
        linksCount: tlmLinksCount(),
        seoSafe: bMode === "existing" ? (seoSafeExistingB ? 1 : 0) : 1,
        decisionRule: String(decisionRule || ""),
      };

      const prev = tlmPrevRef.current || {};

      if (cur.postType && cur.postType !== prev.postType) {
        tlmSend("pt_wizard_action", { action: "select_type", value: cur.postType, step: tlmStepKey() });
      }
      if (cur.pageA && cur.pageA !== prev.pageA) {
        tlmSend("pt_wizard_action", { action: "select_control", value: 1, step: tlmStepKey() });
      }
      if (cur.bMode && cur.bMode !== prev.bMode) {
        tlmSend("pt_wizard_action", { action: "select_b_mode", value: cur.bMode, step: tlmStepKey() });
      }
      if (cur.pageB && cur.pageB !== prev.pageB) {
        tlmSend("pt_wizard_action", { action: "select_b", value: 1, step: tlmStepKey() });
      }
      if (cur.hasEditedB && cur.hasEditedB !== prev.hasEditedB) {
        tlmSend("pt_wizard_action", { action: "edit_b_opened", value: 1, step: tlmStepKey() });
      }
      if (cur.goal && cur.goal !== prev.goal) {
        tlmSend("pt_wizard_action", { action: "select_goal", value: cur.goal, step: tlmStepKey() });
      }
      if (cur.clickScope && cur.clickScope !== prev.clickScope) {
        tlmSend("pt_wizard_action", { action: "select_click_scope", value: cur.clickScope, step: tlmStepKey() });
      }
      if (cur.linksCount !== prev.linksCount) {
        tlmSend("pt_wizard_action", { action: "targets_count_changed", value: cur.linksCount, step: tlmStepKey() });
      }
      if (cur.seoSafe !== prev.seoSafe) {
        tlmSend("pt_wizard_action", { action: "toggle_seo_safe", value: cur.seoSafe, step: tlmStepKey() });
      }
      if (cur.decisionRule && cur.decisionRule !== prev.decisionRule) {
        tlmSend("pt_wizard_action", { action: "select_decision_rule", value: cur.decisionRule, step: tlmStepKey() });
      }

      tlmPrevRef.current = cur;
    }, [
      postType,
      pageA && pageA.id,
      bMode,
      pageB && pageB.id,
      hasEditedB,
      goal,
      clickScope,
      links,
      seoSafeExistingB,
      decisionRule,
    ]);

    // Persist meaningful progress changes immediately; the existing interval
    // only refreshes last_seen while the wizard remains open.
    useEffect(() => {
      if (!tlmSessionIdRef.current) return;
      tlmPersist({});
    }, [
      step,
      postType,
      customCodeType,
      cssScope,
      pageA && pageA.id,
      bMode,
      pageB && pageB.id,
      tempBDraftId,
      hasEditedB,
      seoSafeExistingB,
      goal,
      conversionChosen,
      clickScope,
      links,
      scrollDepth,
      decisionRule,
      customCss,
      cssMarkers,
      customHtmlChanges,
      error,
      showProductBTitle,
      showProductBPrice,
      showProductBSalePrice,
      showProductBShortDesc,
      showProductBLongDesc,
      showProductBImage,
      showProductBGallery,
    ]);

    // Fetch lists
    useEffect(() => {
      if (!postType) {
        setPages([]);
        return;
      }
      setLoading(true);
      fetchPages(isCustomCode ? cssScope : postType, pageSearch)
        .then(setPages)
        .catch((err) => {
          console.error("abtestkit wizard fetch failed:", err);
          setError("Could not load items. Please try a narrower search.");
        })
        .finally(() => setLoading(false));
    }, [pageSearch, postType, cssScope, customCodeType]);

    useEffect(() => {
      if (bMode !== "existing" || !postType) {
        setPagesB([]);
        return;
      }
      setLoading(true);
      fetchPages(postType, pageBSearch)
        .then((list) => {
          // exclude chosen A
          setPagesB(list.filter((p) => !pageA || p.id !== pageA.id));
        })
        .catch(() => {})
        .finally(() => setLoading(false));
    }, [bMode, pageBSearch, pageA, postType]);

    // Only reset "edited" status when the user explicitly changes the source of B
    // i.e. switching between duplicate <-> existing mode.
    // DO NOT reset when pageB changes (because creating a draft will always change it).
    useEffect(() => {
      // Switching B source should clear B selection/draft and any “edited” state.
      setHasEditedB(false);
      setPageB(null);
      setTempBDraftId(null);
      setPageBSearch("");
      setPagesB([]);
    }, [bMode]);

    // If a temporary duplicate/shadow has been created but the test
    // has not yet been saved, warn before leaving the wizard and clean it up.
    useEffect(() => {
      const hasTempVariation =
        bMode === "duplicate" &&
        !!pageA &&
        !!(tempBDraftId || (pageB && pageB.id));

      if (!hasTempVariation) return;

      const root = document.getElementById("abtestkit-pt-wizard-root");

      const handleClickAway = (e) => {
        const target = e.target;
        if (root && root.contains(target)) return;

        const link =
          target && target.closest
            ? target.closest("a[href]")
            : null;
        if (!link) return;

        // Let modified clicks (new tab, etc.) behave normally.
        if (
          e.metaKey ||
          e.ctrlKey ||
          e.shiftKey ||
          e.altKey ||
          link.target === "_blank"
        ) {
          return;
        }

        e.preventDefault();

        const msg =
          "You've started building a Version B shadow for this test.\n\n" +
          "If you leave now, that Version B shadow will be deleted and any unsaved wizard progress will be lost.\n\n" +
          "Press OK to delete Version B and leave the wizard.\n" +
          "Press Cancel to stay in the wizard.";

        const confirmDelete = window.confirm(msg);

        if (!confirmDelete) {
          tlmSend("pt_wizard_action", { action: "leave_cancelled", value: 1, step: tlmStepKey() });
          return;
        }

        tlmSend("pt_wizard_result", { result: "abandoned", step: tlmStepKey() });
        tlmPersist({ completed: true, result: "abandoned" });

        setError("");
        setLoading(true);

        cleanupWizardDuplicate({ silent: false })
          .then((result) => {
            if (!result || result.ok === false) {
              return;
            }

            setTempBDraftId(null);
            window.location.href = link.href;
          })
          .finally(() => {
            setLoading(false);
          });
      };

      document.addEventListener("click", handleClickAway, true);
      return () => {
        document.removeEventListener("click", handleClickAway, true);
      };
    }, [bMode, pageA && pageA.id, pageB && pageB.id, tempBDraftId, postType]);

    // Step 0: must choose a top-level test type.
    const canNext0 =
      postType === "page" ||
      postType === "post" ||
      postType === "product" ||
      postType === "reusable_section" ||
      postType === "custom_code";

    // Custom Code adds one extra choice before the normal control selection.
    const canNextCustomCodeType =
      customCodeType === "custom_css" || customCodeType === "custom_html";

    // Control selection must have a real page/post/product.
    const canNext1 = !!pageA;

    // Keep the auto-generated title synced to the current Version A source,
    // but stop auto-updating once the user manually edits the title.
    useEffect(() => {
      if (!pageA) {
        if (!testTitleManuallyEditedRef.current) {
          setTestTitle("");
        }
        return;
      }

      if (testTitleManuallyEditedRef.current) {
        return;
      }

      setTestTitle(decodeEntities(pageA.title || ""));
    }, [pageA && pageA.id]);

    useEffect(() => {
      if (!isCustomCode) {
        return;
      }

      setPageA(null);
      setTestTitle("");
      testTitleManuallyEditedRef.current = false;
      setGoal("");
      setConversionChosen(false);
      clearClickTargetState();
      clearCustomCodeState();
    }, [cssScope, customCodeType]);

    // If Version A changes, Version B must be reset (prevents mismatched A/B pairs)
    useEffect(() => {
      setPageB(null);
      setTempBDraftId(null);
      setHasEditedB(false);
      setPageBSearch("");
      setPagesB([]);

      // Also clear conversion config + targets, because they’re tied to the chosen pages
      setGoal("");
      setConversionChosen(false);
      setClickScope("on_test_pages");
      setClickPreviewMode("mobile");
      setLinks("");
      setPrettyPicks([]);
      setShowManualTargets(false);
      setGoalPage(null);
      setGoalPages([]);
      setGoalPageSearch("");

      // Reset product preview token + hydration guards (safe even for non-product)
      setProductPreviewToken("");
      shortHydratedRef.current = false;
      longHydratedRef.current = false;
    }, [pageA && pageA.id]);

    // Keep the pretty list in sync with the CSV (manual edits / clears)
    useEffect(() => {
      const tokens = (links || "")
        .split(",")
        .map((s) => s.trim())
        .filter(Boolean);

      if (tokens.length === 0) {
        // If the field is cleared, clear the pretty cards too
        setPrettyPicks([]);
        return;
      }

      const norm = (s) => {
        const raw = (s || "").trim();
        const clean = raw.replace(/\/+$/, "");
        return clean || (raw === "/" ? "/" : "");
      };
      const tokenSet = new Set(tokens);

      setPrettyPicks((prev) =>
        prev.filter((p) => {
          const sel = p.selector || "";
          const hrefBase = p.href ? norm(p.href) : "";
          // Accept if the token CSV still contains either the selector
          // or a matching href form (with or without trailing star)
          if (sel && tokenSet.has(sel)) return true;
          if (hrefBase && (tokenSet.has(hrefBase) || tokenSet.has(hrefBase + "*"))) return true;
          return false;
        })
      );
    }, [links]);

    const destinationTargets = () =>
      (links || "")
        .split(",")
        .map((s) => s.trim())
        .filter(Boolean);

    const addDestinationEntity = (entity, suppliedTarget = "") => {
      const target = suppliedTarget || destinationTargetFromEntity(entity);

      if (!target) {
        return;
      }

      const list = destinationTargets();

      if (!list.includes(target)) {
        list.push(target);
        setLinks(list.join(", "));
      }

      setGoalPage(entity);
    };

    const removeDestinationEntity = (entity, suppliedTarget = "") => {
      const target = suppliedTarget || destinationTargetFromEntity(entity);

      if (!target) {
        return;
      }

      const filtered = destinationTargets().filter((item) => item !== target);
      setLinks(filtered.join(", "));

      if (goalPage && entity && String(goalPage.id || "") === String(entity.id || "")) {
        setGoalPage(null);
      }
    };

    // Shared handler for click picks from Version A, Version B, or other pages
    const handlePick = (picked) => {
      // Prefer URLs (hrefs) when available, they survive redesigns
      const list = (links || "")
        .split(",")
        .map((s) => s.trim())
        .filter(Boolean);

      let token = "";

      if (picked.href) {
        const rawHref = String(picked.href || "").trim();
        const cleanHref = rawHref.replace(/\/+$/, "") || "/";

        // Homepage/root links must be exact.
        // A wildcard root target would match every internal URL.
        token = cleanHref === "/" ? "/" : cleanHref + "*";
      }

      if (!token && picked.selector) {
        // Fallback for elements without links (pure <button> / custom clickable)
        token = picked.selector;
      }

      if (token && !list.includes(token)) {
        list.push(token);
        setLinks(list.join(", "));
      }

      // Maintain pretty list display
      setPrettyPicks((prev) => {
        const key = (x) => (x.selector || "") + "||" + (x.href || "");
        const seen = new Set(prev.map(key));
        if (!seen.has(key(picked))) {
          return [...prev, picked];
        }
        return prev;
      });
    };

    // Fetch other pages when clickScope is "other_page" (non-product tests only),
    // or fetch pages/posts/products for destination URL goals.
    useEffect(() => {
      const isOtherPageClick =
        postType !== "product" && goal === "clicks" && clickScope === "other_page";
      const isDestinationUrlPicker = goal === "destination_url";

      if (!isOtherPageClick && !isDestinationUrlPicker) {
        return;
      }

      const q = (goalPageSearch || "").trim();
      const searchType = isDestinationUrlPicker ? destinationPostType : "page";

      setGoalPageLoading(true);
      fetchPages(searchType, q)
        .then((list) => {
          setGoalPages(list || []);
        })
        .catch(() => {})
        .finally(() => setGoalPageLoading(false));
    }, [postType, goal, clickScope, goalPageSearch, destinationPostType]);

    // Step 2 (Version B source):
    // - If using an existing page/product, you must pick Version B.
    // - If duplicating, you can move on and create/edit B on the next step.
    const canNext2 = bMode === "existing" ? !!pageB : true;

    // Step 3 (Build Version B):
    // - For pages:
    //     • duplicate: must have created B AND clicked "Edit" once.
    //     • existing: just need a valid B selected.
    // - For products:
    //     • no physical B page; user must change at least one Version B field.
    const hasProductOverrides =
      (productBTitle && productBTitle.trim() !== "") ||
      (productBPrice && productBPrice.trim() !== "") ||
      (productBSalePrice && productBSalePrice.trim() !== "") ||
      (productBShortDesc && productBShortDesc.trim() !== "") ||
      (productBLongDesc && productBLongDesc.trim() !== "") ||
      (productBImageUrl && productBImageUrl.trim() !== "") ||
      (productBGalleryUrls && productBGalleryUrls.trim() !== "") ||
      (Array.isArray(productBGalleryIds) && productBGalleryIds.length > 0);

    // Step 3 (Build Version B):
    // - Pages/posts:
    //     • duplicate: must have created B AND clicked “Edit …” once (hasEditedB).
    //     • existing: just need a valid B selected.
    // - Products:
    //     • must have at least one Version B override filled in.
    const hasCompleteCustomHtmlChange =
      Array.isArray(customHtmlChanges) &&
      customHtmlChanges.some(
        (change) => String((change && change.selector) || "").trim() !== ""
      );

    const canNext3 =
      isCustomHtml
        ? hasCompleteCustomHtmlChange
        : isCustomCss
        ? String(customCss || "").trim().length > 0
        : postType === "product"
        ? !!pageB && hasEditedB
        : bMode === "duplicate"
        ? !!pageB && hasEditedB
        : !!pageB;

    // Step 4: Choose conversion type – must choose a goal
    const canNext4 = conversionChosen;

    // Step 5: Conversion goal – if clicks or destination URL, require at least one target;
    // if form, add_to_cart or purchase, always OK
    const canNext5 =
      goal === "form" ||
      goal === "add_to_cart" ||
      goal === "purchase" ||
      goal === "scroll_depth" ||
      ((goal === "clicks" || goal === "destination_url") && (links || "").trim().length > 0);


    // Step 6: Summary – final step, no "Next"
    const canNext6 = false;

    const onCreate = (start) => {
      setError("");

      // Telemetry: create attempt
      tlmSend("pt_wizard_create_attempt", {
        step: tlmStepKey(),
        started: start ? 1 : 0,
      });
      // Base payload used for normal page tests
      // Map decision rules -> thresholds
      const decisionMap = {
        fast:     { min_impressions: 25, min_conversions: 3,  decision_mode: "auto" },
        balanced: { min_impressions: 50, min_conversions: 5,  decision_mode: "auto" },
        precise:  { min_impressions: 75, min_conversions: 10, decision_mode: "auto" },
        manual:   { min_impressions: 0,  min_conversions: 0,  decision_mode: "manual" },
      };

      const chosen = decisionMap[String(decisionRule || "balanced")] || decisionMap.balanced;

      const payload = {
        control_id: pageA.id,
        test_title: String(testTitle || "").trim(),
        test_type: isCustomCode ? customCodeTestType : postType,
        b_mode: isCustomCode ? customCodeTestType : bMode,
        b_page_id: isCustomCode ? 0 : (bMode === "existing" ? (pageB && pageB.id) : 0),
        css_scope: isCustomCss ? cssScope : "",
        custom_css: isCustomCss ? customCss : "",
        css_markers: isCustomCss ? cssMarkers : [],
        html_scope: isCustomHtml ? cssScope : "",
        html_changes: isCustomHtml ? customHtmlChanges : [],

        // Existing page as B: optional SEO protection toggle
        seo_safe_existing_b: bMode === "existing" ? !!seoSafeExistingB : true,
        start: !!start,
        split: 50,
        goal,

        // NEW: decision rule + thresholds
        decision_rule: String(decisionRule || "balanced"),
        decision_mode: String(chosen.decision_mode || "auto"),
        min_impressions: Number.isFinite(chosen.min_impressions) ? chosen.min_impressions : 50,
        min_conversions: Number.isFinite(chosen.min_conversions) ? chosen.min_conversions : 5,

        links: links.split(",").map((s) => s.trim()).filter(Boolean),
        scroll_depth: parseInt(scrollDepth, 10) || 50,
      };

      // For WooCommerce product tests we never create a physical Version B product.
      // Instead we send field-level overrides for a "virtual" Version B.
      if (postType === "product") {
        payload.b_mode = "duplicate";
        payload.b_page_id = pageB && pageB.id ? pageB.id : 0;
      }

      apiFetch({
        path: "/abtestkit/v1/pt/create",
        method: "POST",
        headers: { "X-WP-Nonce": cfg.nonce, "Content-Type": "application/json" },
        data: payload,
      })
        .then((res) => {
          if (!res || !res.ok) {
            const code = (res && res.error) || "create_failed";

            // Build a friendlier message per error code
            let msg;
            switch (code) {
              case "conflict_running": {
                const base =
                  (res && res.info && res.info.message) ||
                  "You can’t start this test because one or both pages are already used by another running test.";
                const list =
                  res &&
                  res.info &&
                  Array.isArray(res.info.conflicts) &&
                  res.info.conflicts.length
                    ? ` Conflicting test ID: ${res.info.conflicts.join(", ")}.`
                    : "";
                msg = base + list;
                break;
              }
              case "invalid_control":
                msg = "Please select a valid page for Version A (control).";
                break;
              case "invalid_mode":
                msg =
                  "Choose how to create Version B (duplicate this page or use an existing page).";
                break;
              case "missing_custom_css":
                msg = "Add Version B CSS before creating this Custom CSS test.";
                break;
              case "missing_custom_html":
                msg = "Select at least one element and add Version B HTML before creating this Custom HTML test.";
                break;
              case "create_failed":
                msg = "Couldn’t create the test. Please try again.";
                break;
              default:
                msg = "Something went wrong while creating the test.";
            }

            const err = new Error(msg);
            err.code = code;
            err.info = res && res.info;
            throw err;
          }

          // Telemetry: create succeeded + session completed
          Promise.all([
            tlmSend("pt_wizard_create_succeeded", { step: tlmStepKey(), started: start ? 1 : 0 }),
            tlmSend("pt_wizard_result", { result: "completed", step: tlmStepKey(), started: start ? 1 : 0 }),
          ]).finally(() => {
            tlmPersist({ completed: true, result: "completed" });
            window.location.href = res.redirect || cfg.dashboard;
          });
        })
        .catch((e) => {
        tlmSend("pt_wizard_create_failed", {
          step: tlmStepKey(),
          error_code: String((e && e.code) ? e.code : "create_failed"),
        });
        setError(e.message || "Couldn’t create the test.");
      });
    };

    const createBDraftNow = () => {
      if (!pageA) return Promise.resolve();
      setLoading(true);
      setError("");

      return apiFetch({
        path: "/abtestkit/v1/pt/duplicate",
        method: "POST",
        headers: {
          "X-WP-Nonce": cfg.nonce,
          "Content-Type": "application/json",
        },
        data: {
          control_id: pageA.id,
          test_type: postType,
        },
      })
        .then((res) => {
          if (!res || !res.ok || !res.page) {
            throw new Error(res && res.error ? res.error : "duplicate_failed");
          }

          setPageB(res.page);
          setTempBDraftId(res.page.id);

          // A new draft B should REQUIRE an edit before Next.
          setHasEditedB(false);

          // Telemetry: variation created
          tlmSend("pt_wizard_action", { action: "duplicate_created", value: 1, step: tlmStepKey() });
        })
        .catch((e) => {
          tlmSend("pt_wizard_action", {
            action: "duplicate_failed",
            value: 1,
            step: tlmStepKey(),
            error_code: String((e && e.message) ? e.message : "duplicate_failed").slice(0, 64),
          });
          setError(e.message || "Failed to create variation");
        })
        .finally(() => setLoading(false));
    };

    const selectableCardStyle = (isSelected) => ({
      cursor: "pointer",
      boxSizing: "border-box",
      border: `1px solid ${isSelected ? "#2271b1" : "#dcdcde"}`,
      boxShadow: isSelected
        ? "0 0 0 1px #2271b1, 0 1px 2px rgba(0,0,0,0.04)"
        : "0 1px 2px rgba(0,0,0,0.04)",
    });

    /* Step 0 – Select test type (Page, Post or WooCommerce product) */
    const stepType = h(Fragment, null, [
      h("p", null, "What would you like to test?"),
      h(
        "div",
        {
          style: {
            display: "grid",
            gridTemplateColumns: "repeat(auto-fit, minmax(min(100%, 220px), 1fr))",
            gap: 16,
            marginTop: 8,
            alignItems: "stretch",
          },
        },
        [
          // PAGE CARD
          h(
            Card,
            {
              onClick: () => selectTestType("page"),
              style: selectableCardStyle(postType === "page"),
            },
            h(CardBody, null, [
              h("h3", { style: { margin: 0 } }, "Page"),
              h(
                "p",
                { style: { marginTop: 4, color: "#50575e" } },
                "Any normal WordPress page, like Home, Pricing, or Contact."
              ),
              h(
                "p",
                { style: { marginTop: 4, color: "#6c7781", fontSize: 12 } },
                "Perfect for landing pages, content pages, and lead magnets."
              ),
            ])
          ),

          // POST CARD
          h(
            Card,
            {
              onClick: () => selectTestType("post"),
              style: selectableCardStyle(postType === "post"),
            },
            h(CardBody, null, [
              h("h3", { style: { margin: 0 } }, "Post"),
              h(
                "p",
                { style: { marginTop: 4, color: "#50575e" } },
                "Blog posts and articles in your main post feed."
              ),
              h(
                "p",
                { style: { marginTop: 4, color: "#6c7781", fontSize: 12 } },
                "Ideal for headlines, intros, and featured image experiments."
              ),
            ])
          ),

          // PRODUCT CARD
          h(
            Card,
            {
              onClick: () => selectTestType("product"),
              style: selectableCardStyle(postType === "product"),
            },
            h(CardBody, null, [
              h("h3", { style: { margin: 0 } }, "WooCommerce product"),
              h(
                "p",
                { style: { marginTop: 4, color: "#50575e" } },
                "Optimize product pages."
              ),
              h(
                "p",
                { style: { marginTop: 4, color: "#6c7781", fontSize: 12 } },
                "Test titles, descriptions, images & more."
              ),
            ])
          ),

          // CUSTOM CODE CARD
          h(
            Card,
            {
              onClick: () => selectTestType("custom_code"),
              style: selectableCardStyle(postType === "custom_code"),
            },
            h(CardBody, null, [
              h("h3", { style: { margin: 0 } }, "Custom Code"),
              h(
                "p",
                { style: { marginTop: 4, color: "#50575e" } },
                "Test a CSS or HTML change on a real page, post, or product."
              ),
              h(
                "p",
                { style: { marginTop: 4, color: "#6c7781", fontSize: 12 } },
                "No shadow page is created. Version B applies only the custom code you configure."
              ),
            ])
          ),

          // REUSABLE SECTION CARD
          h(
            Card,
            {
              onClick: () => selectTestType("reusable_section"),
              style: selectableCardStyle(postType === "reusable_section"),
            },
            h(CardBody, null, [
              h("h3", { style: { margin: 0 } }, "Reusable Section"),
              h(
                "p",
                { style: { marginTop: 4, color: "#50575e" } },
                "Test a reusable section wherever it appears on your site."
              ),
              h(
                "p",
                { style: { marginTop: 4, color: "#6c7781", fontSize: 12 } },
                "Currently supports sections embedded with a shortcode such as [embed_page id=\"123\"]."
              ),
            ])
          ),
        ]
      ),
    ]);

    /* Custom Code – choose the code layer before selecting a page */
    const stepCustomCodeType = h(Fragment, null, [
      h("p", null, "What kind of custom code would you like to test?"),
      h(
        "div",
        {
          style: {
            display: "grid",
            gridTemplateColumns: "repeat(auto-fit, minmax(240px, 1fr))",
            gap: 16,
            marginTop: 12,
          },
        },
        [
          h(
            Card,
            {
              onClick: () => setCustomCodeType("custom_css"),
              style: selectableCardStyle(customCodeType === "custom_css"),
            },
            h(CardBody, null, [
              h("h3", { style: { margin: 0 } }, "CSS"),
              h(
                "p",
                { style: { marginTop: 4, color: "#50575e" } },
                "Change styling while keeping the original page content and URL."
              ),
              h(
                "p",
                { style: { marginTop: 4, color: "#6c7781", fontSize: 12 } },
                "Use existing selectors directly or add B-only marker classes with the visual picker."
              ),
            ])
          ),
          h(
            Card,
            {
              onClick: () => setCustomCodeType("custom_html"),
              style: selectableCardStyle(customCodeType === "custom_html"),
            },
            h(CardBody, null, [
              h("h3", { style: { margin: 0 } }, "HTML"),
              h(
                "p",
                { style: { marginTop: 4, color: "#50575e" } },
                "Replace or insert HTML around selected elements for Version B visitors."
              ),
              h(
                "p",
                { style: { marginTop: 4, color: "#6c7781", fontSize: 12 } },
                "Choose a visual or manual selector, a safe operation, and whether to change the first or every match."
              ),
            ])
          ),
        ]
      ),
    ]);

    /* Step 1 – Select page/post/product (Version A / control) */
    const step0 = h(Fragment, null, [
      h(
        "p",
        null,
        isCustomCode
          ? `Choose where this ${isCustomHtml ? "HTML" : "CSS"} should run, then select the page, post, or product.`
          : postType === "product"
          ? "Select a WooCommerce product to test."
          : postType === "post"
          ? "Select a post to test."
          : postType === "reusable_section"
          ? "Select the source page ID used by your shortcode, for example the page inside [embed_page id=\"123\"]."
          : "Select a page to test."
      ),
      isCustomCode &&
        h(
          "div",
          {
            style: {
              marginBottom: 12,
              padding: "12px 14px",
              background: "#f6f7f7",
              border: "1px solid #dcdcde",
              borderRadius: 8,
            },
          },
          h(SelectControl, {
            label: `Where should this ${isCustomHtml ? "HTML" : "CSS"} run?`,
            value: cssScope,
            options: [
              { label: "Page", value: "page" },
              { label: "Post", value: "post" },
              { label: "WooCommerce product", value: "product" },
            ],
            onChange: setCssScope,
            help: `Version A is the original content. Version B is the same URL with your ${isCustomHtml ? "HTML changes" : "CSS"} applied.`,
          })
        ),
      h(SearchControl, {
        label:
          isCustomCode
            ? cssScope === "product"
              ? "Search products"
              : cssScope === "post"
              ? "Search posts"
              : "Search pages"
            : postType === "product"
            ? "Search products"
            : postType === "post"
            ? "Search posts"
            : postType === "reusable_section"
            ? "Search reusable source pages"
            : "Search pages",
        value: pageSearch,
        onChange: setPageSearch,
        placeholder:
          postType === "custom_css"
            ? cssScope === "product"
              ? "Search products…"
              : cssScope === "post"
              ? "Search posts…"
              : "Search pages…"
            : postType === "product"
            ? "Search products…"
            : postType === "post"
            ? "Search posts…"
            : postType === "reusable_section"
            ? "Search pages used by [embed_page id=\"...\"]…"
            : "Search pages…",
      }),
      loading && h(Spinner),
      h(PageTable, {
        pages,
        selectedId: pageA && pageA.id,
        onSelect: (p) => setPageA(p),
        empty:
          postType === "custom_css"
            ? cssScope === "product"
              ? "No matching products."
              : cssScope === "post"
              ? "No matching posts."
              : "No matching pages."
            : postType === "product"
            ? "No matching products."
            : postType === "post"
            ? "No matching posts."
            : "No matching pages.",
      }),
    ]);


    /* Step 1 – Version B source (duplicate vs existing) */
    const step1 =
      postType === "product"
        ? h(
            Fragment,
            null,
            [
              h(
                "p",
                null,
                "For WooCommerce product tests, abtestkit keeps a single real product."
              ),
              h(
                "p",
                { style: { marginTop: 6, color: "#6c7781" } },
                "This keeps your SKU, inventory & orders safe."
              ),
            ]
          )
        : h(Fragment, null, [
            h(RadioControl, {
              label: "How would you like to create Version B?",
              selected: bMode,
              options: [
                { label: "Create a shadow variation of this page", value: "duplicate" },
                { label: "Use an existing page as Version B", value: "existing" },
              ],
              onChange: setBMode,
            }),
            bMode === "existing" &&
              h(Fragment, null, [

                h(
                  "div",
                  { style: { marginTop: 12 } },
                  h(ToggleControl, {
                    label: "SEO-safe mode (recommended)",
                    checked: !!seoSafeExistingB,
                    onChange: () => setSeoSafeExistingB((v) => !v),
                    help: seoSafeExistingB
                      ? "Version B will be hidden from search engines & the frontend of the website while this test is active."
                      : "Version B SEO & frontend visibility will be left unchanged (advanced).",
                  })
                ),
                // Add some breathing room under the radio control
                h(
                  "div",
                  { style: { marginTop: 12 } },
                  h(SearchControl, {
                    label:
                    postType === "product"
                      ? "Search products for Version B"
                      : postType === "post"
                      ? "Search posts for Version B"
                      : "Search pages for Version B",
                    value: pageBSearch,
                    onChange: setPageBSearch,
                    placeholder: "Search…",
                  })
                ),

                loading && h(Spinner),
                h(PageTable, {
                  pages: pagesB,
                  selectedId: pageB && pageB.id,
                  onSelect: (p) => setPageB(p),
                  empty: "Search for a page to use as Version B.",
                }),
              ]),
          ]);

              // Derived attributes for WooCommerce products (Version A / control)
              const productMeta =
                postType === "product" && pageA && pageA.product ? pageA.product : null;

              const productATitle = pageA ? pageA.title : "";
              const productAPrice =
                productMeta && productMeta.regular_price ? productMeta.regular_price : "";
              const productASale =
                productMeta && productMeta.sale_price ? productMeta.sale_price : "";
              const productAShort =
                productMeta && productMeta.short_description
                  ? productMeta.short_description
                  : "";
              const productALong =
                productMeta && productMeta.description
                  ? productMeta.description
                  : "";
              const productAImageUrl =
                productMeta && productMeta.image_url ? productMeta.image_url : "";
              const productAGalleryUrls =
                productMeta &&
                Array.isArray(productMeta.gallery_urls) &&
                productMeta.gallery_urls.length
                  ? productMeta.gallery_urls.join(", ")
                  : "";

              // Plain-text versions for placeholders
              const productAShortPlain = stripHtml(productAShort);
              const productALongPlain = stripHtml(productALong);

              // Prefill Version B editors with Version A content once (but let the user clear them)
              useEffect(() => {
                if (
                  postType === "product" &&
                  productAShort &&
                  !shortHydratedRef.current &&
                  !productBShortDesc
                ) {
                  setProductBShortDesc(productAShort);
                  shortHydratedRef.current = true;
                }
              }, [postType, productAShort, productBShortDesc]);

              useEffect(() => {
                if (
                  postType === "product" &&
                  productALong &&
                  !longHydratedRef.current &&
                  !productBLongDesc
                ) {
                  setProductBLongDesc(productALong);
                  longHydratedRef.current = true;
                }
              }, [postType, productALong, productBLongDesc]);
                  
const customCodePreviewUrl =
  pageA
    ? (
        cssScope === "product"
          ? getProductPreviewUrl(pageA)
          : getEntityPreviewUrl(pageA, cssScope)
      )
    : "";

const customCssPreviewUrl = isCustomCss ? customCodePreviewUrl : "";
const customHtmlPreviewUrl = isCustomHtml ? customCodePreviewUrl : "";

const addCustomCssMarker = (picked) => {
  const selector = String((picked && picked.selector) || "").trim();

  if (!selector) {
    return;
  }

  const fallbackLabel =
    String((picked && picked.label) || "").trim() ||
    selector.replace(/^[.#]/, "").split(/[ >.:[]/)[0] ||
    "Primary CTA";

  const entered = window.prompt("Name this B-only CSS class", fallbackLabel);

  if (entered === null) {
    return;
  }

  const label = String(entered || "").trim();

  if (!label) {
    return;
  }

  const baseClass = customCssMarkerClassFromLabel(label);
  let className = baseClass;
  let suffix = 2;

  const used = new Set((cssMarkers || []).map((marker) => marker.class_name));

  while (used.has(className)) {
    className = `${baseClass}-${suffix}`;
    suffix += 1;
  }

  const marker = {
    label,
    selector,
    class_name: className,
  };

  setCssMarkers((prev) => [...prev, marker]);

  setCustomCss((current) => {
    const css = String(current || "");
    const starter = `.${className} {\n\n}`;

    if (css.indexOf(`.${className}`) !== -1) {
      return css;
    }

    return css.trim() ? `${css.trim()}\n\n${starter}` : starter;
  });
};

const customCssPickerAfterPreviewContent = h(
  "div",
  {
    style: {
      padding: "12px 14px",
      background: "#f6f7f7",
      border: "1px solid #dcdcde",
      borderRadius: 8,
    },
  },
  [
    h(
      "div",
      {
        style: {
          color: "#1d2327",
          fontSize: 14,
          fontWeight: 600,
          lineHeight: 1.4,
        },
      },
      cssMarkers.length
        ? `Add a CSS class (optional) — ${cssMarkers.length} added`
        : "Add a CSS class (optional)"
    ),

    h(
      "p",
      {
        style: {
          margin: "4px 0 0",
          color: "#6c7781",
          fontSize: 13,
          lineHeight: 1.45,
        },
      },
      "Use the visual picker when the element does not already have a class you can target. The new class is only added for Version B visitors."
    ),

    cssMarkers.length
      ? h(
          "div",
          {
            style: {
              marginTop: 12,
              paddingTop: 12,
              borderTop: "1px solid #dcdcde",
            },
          },
          [
            h(
              "div",
              {
                style: {
                  fontWeight: 600,
                  marginBottom: 6,
                },
              },
              "Added B-only CSS classes"
            ),
            h(
              "ul",
              {
                style: {
                  listStyle: "none",
                  padding: 0,
                  margin: 0,
                  display: "flex",
                  flexDirection: "column",
                  gap: 6,
                },
              },
              cssMarkers.map((marker, index) =>
                h(
                  "li",
                  {
                    key: `${marker.class_name}-${index}`,
                    style: {
                      background: "#fff",
                      border: "1px solid #dcdcde",
                      borderRadius: 4,
                      padding: "8px 10px",
                      display: "flex",
                      justifyContent: "space-between",
                      gap: 10,
                      alignItems: "flex-start",
                    },
                  },
                  [
                    h(
                      "div",
                      {
                        style: {
                          minWidth: 0,
                        },
                      },
                      [
                        h("strong", null, marker.label),
                        h(
                          "div",
                          {
                            style: {
                              color: "#6c7781",
                              fontSize: 12,
                              marginTop: 2,
                            },
                          },
                          marker.selector
                        ),
                        h(
                          "code",
                          {
                            style: {
                              display: "inline-block",
                              marginTop: 4,
                            },
                          },
                          `.${marker.class_name}`
                        ),
                      ]
                    ),
                    h(
                      Button,
                      {
                        isSmall: true,
                        isSecondary: true,
                        onClick: () => {
                          setCssMarkers((prev) =>
                            prev.filter((_, i) => i !== index)
                          );
                        },
                      },
                      "Remove"
                    ),
                  ]
                )
              )
            ),
          ]
        )
      : null,
  ]
);

const addCustomHtmlChange = (picked) => {
  const selector = String((picked && picked.selector) || "").trim();
  const tagName = String((picked && picked.tag_name) || "").toLowerCase();
  const unsupportedTags = [
    "html", "head", "body", "script", "style", "link", "meta"
  ];
  const voidTags = [
    "img", "input", "br", "hr", "source", "area", "base", "col",
    "embed", "param", "track", "wbr"
  ];

  if (!selector) {
    return;
  }

  if (unsupportedTags.includes(tagName)) {
    setError("Choose an element inside the page content rather than the document, head, body, script, style, link or meta element.");
    return;
  }

  setError("");

  setCustomHtmlChanges((current) => {
    const list = Array.isArray(current) ? current : [];

    if (list.some((change) => String(change.selector || "") === selector)) {
      return list;
    }

    const label =
      String((picked && picked.label) || "").trim() ||
      selector.replace(/^[.#]/, "").split(/[ >.:[]/)[0] ||
      "Selected element";

    return [
      ...list,
      {
        label,
        selector,
        operation: voidTags.includes(tagName) ? "insert_after" : "replace_contents",
        match_mode: "all",
        html: String((picked && picked.inner_html) || ""),
        tag_name: tagName,
      },
    ];
  });
};

const addManualCustomHtmlChange = () => {
  const selector = String(manualHtmlSelector || "").trim();

  if (!selector) {
    setError("Enter an element selector before adding a manual HTML target.");
    return;
  }

  try {
    document.querySelector(selector);
  } catch (_) {
    setError("That element selector is not valid CSS selector syntax. Check its brackets, quotes and combinators, then try again.");
    return;
  }

  addCustomHtmlChange({
    selector,
    label: selector.replace(/^[.#]/, "").split(/[ >.:[]/)[0] || "Manual selector",
    inner_html: "",
    tag_name: "",
  });
  setManualHtmlSelector("");
};

const updateCustomHtmlChange = (index, patch) => {
  setCustomHtmlChanges((current) =>
    (Array.isArray(current) ? current : []).map((change, changeIndex) =>
      changeIndex === index ? { ...change, ...(patch || {}) } : change
    )
  );
};

const customHtmlOperationOptions = [
  { label: "Replace selected element contents", value: "replace_contents" },
  { label: "Insert before selected element", value: "insert_before" },
  { label: "Insert after selected element", value: "insert_after" },
  { label: "Prepend inside selected element", value: "prepend_inside" },
  { label: "Append inside selected element", value: "append_inside" },
];

const customHtmlOperationHelp = (operation) => {
  switch (String(operation || "replace_contents")) {
    case "insert_before":
      return "Adds the HTML immediately before the selected element without changing that element.";
    case "insert_after":
      return "Adds the HTML immediately after the selected element without changing that element.";
    case "prepend_inside":
      return "Adds the HTML as the first content inside the selected element.";
    case "append_inside":
      return "Adds the HTML as the last content inside the selected element.";
    case "replace_contents":
    default:
      return "Replaces the contents inside the selected element while keeping its outer tag, classes and ID.";
  }
};

const customHtmlOperationLabel = (operation) => {
  const selected = customHtmlOperationOptions.find(
    (option) => option.value === String(operation || "replace_contents")
  );
  return selected ? selected.label : "Replace selected element contents";
};

const customHtmlEditorCards = h(
  "div",
  { style: { marginTop: 16 } },
  customHtmlChanges.length
    ? customHtmlChanges.map((change, index) =>
        h(
          Card,
          {
            key: `${change.selector}-${index}`,
            style: { marginBottom: 12 },
          },
          h(CardBody, null, [
            h(
              "div",
              {
                style: {
                  display: "flex",
                  justifyContent: "space-between",
                  gap: 12,
                  alignItems: "flex-start",
                  marginBottom: 10,
                },
              },
              [
                h("div", { style: { minWidth: 0 } }, [
                  h("strong", null, change.label || `HTML change ${index + 1}`),
                ]),
                h(
                  Button,
                  {
                    isSmall: true,
                    isSecondary: true,
                    onClick: () =>
                      setCustomHtmlChanges((current) =>
                        (Array.isArray(current) ? current : []).filter(
                          (_, changeIndex) => changeIndex !== index
                        )
                      ),
                  },
                  "Remove"
                ),
              ]
            ),
            h(TextControl, {
              label: "Element selector",
              value: String(change.selector || ""),
              onChange: (value) =>
                updateCustomHtmlChange(index, { selector: String(value || "") }),
              help: "Uses CSS selector syntax. Prefer a stable ID or attribute; the preview below reports how many elements currently match.",
            }),
            h(SelectControl, {
              label: "HTML operation",
              value: String(change.operation || "replace_contents"),
              options:
                ["img", "input", "br", "hr", "source", "area", "base", "col", "embed", "param", "track", "wbr"].includes(
                  String(change.tag_name || "").toLowerCase()
                )
                  ? customHtmlOperationOptions.filter((option) =>
                      option.value === "insert_before" || option.value === "insert_after"
                    )
                  : customHtmlOperationOptions,
              onChange: (value) =>
                updateCustomHtmlChange(index, { operation: String(value || "replace_contents") }),
              help: customHtmlOperationHelp(change.operation),
            }),
            h(SelectControl, {
              label: "Apply this change to",
              value: String(change.match_mode || "all"),
              options: [
                { label: "All matching elements", value: "all" },
                { label: "First matching element only", value: "first" },
              ],
              onChange: (value) =>
                updateCustomHtmlChange(index, { match_mode: value === "first" ? "first" : "all" }),
              help: "Choose explicitly when a selector matches more than one element.",
            }),
            h(TextareaControl, {
              label: "Version B HTML",
              value: String(change.html || ""),
              onChange: (value) =>
                updateCustomHtmlChange(index, { html: String(value || "") }),
              rows: 10,
              help: customHtmlOperationHelp(change.operation),
              placeholder: "<strong>New Version B content</strong>",
            }),
          ])
        )
      )
    : h(
        Notice,
        { status: "info", isDismissible: false },
        "Select an element in the preview to create your first Version B HTML change."
      )
);

/* Step 2 – Build Version B */
const step2 = isCustomHtml
  ? h(Fragment, null, [
      h(
        "p",
        null,
        "Version A keeps the original page. For Version B, select a target, choose how the HTML should be inserted or replaced, and decide whether to change the first match or every match."
      ),
      h(
        Notice,
        { status: "info", isDismissible: false },
        "Saved HTML is sanitised before preview and delivery. Script, style, iframe, object and embed elements, plus unsafe event-handler attributes, are removed."
      ),
      h(ClickPreviewModeSelector, {
        value: htmlPreviewMode,
        onChange: setHtmlPreviewMode,
      }),
      h(ElementPicker, {
        pageId: pageA && pageA.id,
        viewBase: getPreviewBase(cssScope),
        rawUrl: customHtmlPreviewUrl,
        goal: "custom_html_selector",
        label: "HTML selector picker",
        previewMode: htmlPreviewMode,
        allowAnyElement: true,
        preferExactElement: true,
        actionLabel: "Now click the element you want to change…",
        interactiveWhenNotPicking: true,
        previewVariant: "B",
        previewHtmlChanges: customHtmlChanges,
        showTargetRefreshButton: true,
        selected: (customHtmlChanges || []).map((change) => change.selector),
        onWarn: (msg) => {
          console.warn("[abtestkit custom html picker]", msg);
        },
        onPick: addCustomHtmlChange,
      }),
      h(
        "details",
        {
          style: {
            marginTop: 14,
            padding: "12px 14px",
            background: "#f6f7f7",
            border: "1px solid #dcdcde",
            borderRadius: 8,
          },
        },
        [
          h(
            "summary",
            {
              style: {
                cursor: "pointer",
                fontWeight: 600,
              },
            },
            "Advanced: enter an element selector manually"
          ),
          h(
            "div",
            { style: { display: "flex", gap: 8, alignItems: "flex-end", marginTop: 8 } },
            [
              h(
                "div",
                { style: { flex: "1 1 auto" } },
                h(TextControl, {
                  label: "Element selector",
                  value: manualHtmlSelector,
                  onChange: setManualHtmlSelector,
                  placeholder: "#hero .wp-block-heading",
                  help: "Uses CSS selector syntax. Use this when an element is hidden or difficult to click in the preview.",
                })
              ),
              h(
                Button,
                {
                  isSecondary: true,
                  onClick: addManualCustomHtmlChange,
                  disabled: String(manualHtmlSelector || "").trim() === "",
                  style: { marginBottom: 24 },
                },
                "Add selector"
              ),
            ]
          ),
        ]
      ),
      customHtmlEditorCards,
      error &&
        h(
          Notice,
          { status: "error", isDismissible: false, style: { marginTop: 12 } },
          error
        ),
    ])
  : isCustomCss
  ? h(Fragment, null, [
      h(
        "p",
        null,
        "Version A is the original page. Version B is the same page with your CSS loaded after the existing theme and plugin styles."
      ),

      h(
        "div",
        { style: { marginTop: 16 } },
        h(TextareaControl, {
          label: "Version B CSS",
          value: customCss,
          onChange: setCustomCss,
          rows: 12,
          help: "This CSS only loads for Version B visitors and is loaded after your existing theme and plugin styles.",
          placeholder:
            cssMarkers.length
              ? customCssStarterForMarkers(cssMarkers)
              : ".single_add_to_cart_button {\n  background: #111;\n  border-radius: 999px;\n}",
        })
      ),

      h(
        "div",
        {
          style: {
            marginTop: 16,
          },
        },
        [
          h(ClickPreviewModeSelector, {
            value: cssPreviewMode,
            onChange: setCssPreviewMode,
          }),

          h(ElementPicker, {
            pageId: pageA && pageA.id,
            viewBase: getPreviewBase(cssScope),
            rawUrl: customCssPreviewUrl,
            goal: "custom_css_marker",
            label: "CSS class picker",
            previewMode: cssPreviewMode,
            allowAnyElement: true,
            actionLabel: "Now click the element you want to add the class to…",
            interactiveWhenNotPicking: true,
            previewVariant: "B",
            previewCss: customCss,
            previewMarkers: cssMarkers,
            showTargetRefreshButton: true,
            selected: (cssMarkers || []).map((marker) => marker.selector),
            afterPreviewContent: customCssPickerAfterPreviewContent,
            onWarn: (msg) => {
              console.warn("[abtestkit custom css marker picker]", msg);
            },
            onPick: addCustomCssMarker,
          }),
        ]
      ),

      error &&
        h(
          Notice,
          { status: "error", isDismissible: false, style: { marginTop: 12 } },
          error
        ),
    ])
  : h(Fragment, null, [
  postType !== "product" &&
h("p", null, [
  'Click “Edit page” to make changes in a new tab, save and ',
  h("strong", null, "return here"),
  ".",
]),


  h(
    "div",
    { style: { display: "grid", gridTemplateColumns: "1fr 1fr", gap: 16 } },
    [
      h(
        Card,
        null,
        h(CardBody, null, [
          h("h3", null, "Version A (Control)"),
          pageA ? h("p", null, decodeEntities(`${pageA.title}`)) : null,
          pageA
            ? h(PreviewPane, {
                pageId: pageA.id,
                label: "Preview: Version A",
                viewBase: abtestkit_PT.viewBase,
              })
            : null,
        ])
      ),

      h(
        Card,
        null,
        h(CardBody, null, [
          // Duplicate mode: we auto-create the variation when entering this step
          bMode === "duplicate" && !pageB
            ? h(Fragment, null, [
                h("p", null, "Creating your variation (Version B)…"),
                loading ? h(Spinner) : null,
                h(
                  "p",
                  { style: { marginTop: 8, color: "#6c7781", fontSize: 12 } },
                  "Once it’s ready, click “Edit product” and make at least one change before continuing."
                ),
              ])
            : null,

          // If we have B (either selected existing or just created), show title/button
          ((bMode === "existing" && pageB) || (bMode === "duplicate" && pageB)) &&
            h(Fragment, null, [
              h(
                "div",
                {
                  style: {
                    display: "flex",
                    justifyContent: "space-between",
                    alignItems: "baseline",
                    marginBottom: "4px",
                    paddingBottom: "0",
                  },
                },
                [
                  h("div", null, [
                    h("h3", null, "Version B"),
                    pageB ? h("p", null, decodeEntities(`${pageB.title}`)) : null,
                  ]),

                  h(
                    Button,
                    {
                      href: cfg.editBase + pageB.id + "&action=edit",
                      target: "_blank",
                      onClick: () => setHasEditedB(true),
                      style: {
                        background: "#2271b1",
                        color: "#ffffff",
                        borderRadius: "14px",
                        padding: "6px 14px",
                        border: "1px solid #1b5f8c",
                        fontWeight: 600,
                        cursor: "pointer",
                        marginTop: "2px",
                        whiteSpace: "nowrap",
                        display: "inline-flex",
                        alignItems: "center",
                        gap: "6px",
                      },
                    },
                    [
                      "Edit Version B",
                      h("span", {
                        className: "dashicons dashicons-external",
                        style: {
                          fontSize: 14,
                          width: 14,
                          height: 14,
                        },
                      }),
                    ]
                  ),
                ]
              ),

              // Only show the Version B iframe when using an existing page/post.
              // (Duplicate mode: remove the iframe entirely.)
              postType !== "product" &&
                bMode === "existing" &&
                h(Fragment, null, [
                  h(PreviewPane, {
                    pageId: pageB.id,
                    label: "Preview: Version B",
                    viewBase: getPreviewBase(postType),
                    extraQuery: previewBNonce ? `&abtestkit_r=${previewBNonce}` : "",
                  }),

                  // Refresh button under the iframe (existing-mode only)
                  h(
                    "div",
                    { style: { marginTop: 8, display: "flex", justifyContent: "flex-end" } },
                    h(
                      Button,
                      {
                        isSecondary: true,
                        onClick: () => setPreviewBNonce(Date.now()),
                      },
                      "Refresh preview"
                    )
                  ),
                ]),

              bMode === "duplicate" &&
                pageB &&
                h(
                  "div",
                  {
                    style: {
                      marginTop: 10,
                      padding: "14px 16px",
                      background: "#f6f7f7",
                      border: "1px solid #dcdcde",
                      borderRadius: 8,
                    },
                  },
                  [
                    h(
                      "div",
                      {
                        style: {
                          fontSize: 12,
                          fontWeight: 600,
                          color: "#1d2327",
                          marginBottom: 8,
                          textTransform: "uppercase",
                          letterSpacing: "0.04em",
                        },
                      },
                      "What to do next"
                    ),

                    h(
                      "ol",
                      {
                        style: {
                          margin: "0 0 0 18px",
                          padding: 0,
                          color: "#50575e",
                          fontSize: 13,
                          lineHeight: 1.7,
                        },
                      },
                      [
                        h(
                          "li",
                          { style: { marginBottom: 4 } },
                          "Click “Edit Version B” to make the changes you want to test."
                        ),
                        h(
                          "li",
                          { style: { marginBottom: 4 } },
                          postType === "product"
                            ? "Save the product as a draft."
                            : postType === "post"
                            ? "Save the post as a draft."
                            : "Save the page as a draft."
                        ),
                        h(
                          "li",
                          null,
                          "Close the Version B tab and return here to continue building your test."
                        ),
                      ]
                    ),

                    h("div", {
                      style: {
                        marginTop: 16,
                        marginBottom: 16,
                        borderTop: "1px solid #dcdcde",
                      },
                    }),

                    h(GoodToKnowCarousel, { postType, embedded: true }),
                  ]
                ),
            ]),
        ])
      ),
    ]
  ),

  error &&
    h(
      Notice,
      { status: "error", isDismissible: false, style: { marginTop: 12 } },
      error
    ),
]);


/* Step 3 – Choose conversion type (cards) */
const step3 = h(Fragment, null, [
  h("p", null, "What do you want to count as a conversion on this page?"),
  h(
    "div",
    {
      style: {
        display: "grid",
        gridTemplateColumns: "repeat(auto-fit, minmax(min(100%, 260px), 1fr))",
        gap: 16,
        marginTop: 8,
        alignItems: "stretch",
      },
    },
    [
      // CLICKS CARD
      h(
        Card,
        {
          onClick: () => {
            setGoal("clicks");
            setConversionChosen(true);
            setClickScope("on_test_pages");
          },
          style: selectableCardStyle(goal === "clicks"),
        },
        h(CardBody, null, [
          h("h3", null, "Clicks"),
          h(
            "p",
            { style: { marginTop: 4, color: "#50575e" } },
            "Track clicks on buttons, links, or any clickable element."
          ),
          h(
            "p",
            { style: { marginTop: 4, color: "#6c7781", fontSize: 12 } },
            "Best for CTAs, navigation, and click-throughs."
          ),
        ])
      ),

      // FORMS CARD
      h(
        Card,
        {
          onClick: () => {
            setGoal("form");
            setConversionChosen(true);
          },
          style: selectableCardStyle(goal === "form"),
        },
        h(CardBody, null, [
          h("h3", null, "Form submissions"),
          h(
            "p",
            { style: { marginTop: 4, color: "#50575e" } },
            "Count a conversion when a form on the page is successfully submitted."
          ),
          h(
            "p",
            { style: { marginTop: 4, color: "#6c7781", fontSize: 12 } },
            "Best for lead gen, contact, or signup forms."
          ),
        ])
      ),

      // DESTINATION URL CARD
      h(
        Card,
        {
          onClick: () => {
            setGoal("destination_url");
            setConversionChosen(true);
            setClickScope("on_test_pages");
            setLinks("");
            setPrettyPicks([]);
            setShowManualTargets(false);
          },
          style: selectableCardStyle(goal === "destination_url"),
        },
        h(CardBody, null, [
          h("h3", null, "Destination URL"),
          h(
            "p",
            { style: { marginTop: 4, color: "#50575e" } },
            "Count a conversion when a visitor reaches a specific URL after seeing the test."
          ),
          h(
            "p",
            { style: { marginTop: 4, color: "#6c7781", fontSize: 12 } },
            "Best for thank-you pages, booking confirmations, and multi-step journeys."
          ),
        ])
      ),

      // SCROLL DEPTH CARD – page/post/product tests
      postType !== "reusable_section" &&
        h(
          Card,
          {
            onClick: () => {
              setGoal("scroll_depth");
              setConversionChosen(true);
              setClickScope("on_test_pages");
              setLinks("");
              setPrettyPicks([]);
              setShowManualTargets(false);
            },
            style: selectableCardStyle(goal === "scroll_depth"),
          },
          h(CardBody, null, [
            h("h3", null, "Scroll depth"),
            h(
              "p",
              { style: { marginTop: 4, color: "#50575e" } },
              "Count a conversion when a visitor scrolls to a chosen percentage of the page."
            ),
            h(
              "p",
              { style: { marginTop: 4, color: "#6c7781", fontSize: 12 } },
              "Best for measuring whether people reach key content, offers, FAQs, or lower-page CTAs."
            ),
          ])
        ),

      // ADD TO CART CARD – only show for WooCommerce products
      postType === "product" &&
        h(
          Card,
          {
            onClick: () => {
              setGoal("add_to_cart");
              setConversionChosen(true);
            },
            style: selectableCardStyle(goal === "add_to_cart"),
          },
          h(CardBody, null, [
            h("h3", null, "Add to cart"),
            h(
              "p",
              { style: { marginTop: 4, color: "#50575e" } },
              "Count a conversion when this product is successfully added to the cart."
            ),
            h(
              "p",
              { style: { marginTop: 4, color: "#6c7781", fontSize: 12 } },
              "Best for product intent and basket-rate tests."
            ),
          ])
        ),

      // COMPLETED ORDERS (REVENUE) CARD – show for WooCommerce product tests and reusable sections shown on product pages
      (postType === "product" || postType === "reusable_section") &&
        h(
          Card,
          {
            onClick: () => {
              setGoal("purchase");
              setConversionChosen(true);
            },
            style: selectableCardStyle(goal === "purchase"),
          },
          h(CardBody, null, [
            h("h3", null, "Completed Orders (Revenue)"),
            h(
              "p",
              { style: { marginTop: 4, color: "#50575e" } },
              postType === "reusable_section"
                ? "Count a conversion when a visitor who saw this reusable section places a WooCommerce order."
                : "Count a conversion when an order containing this product is placed, and track revenue from that product."
            ),
            h(
              "p",
              { style: { marginTop: 4, color: "#6c7781", fontSize: 12 } },
              "Best for completed sales, revenue per visitor, and revenue per customer."
            ),
          ])
        ),
    ]
  ),
  h(
    "p",
    { style: { marginTop: 12, color: "#6c7781" } },
    "You can fine-tune the details on the next step."
  ),
]);

/* Step 4 – Conversion goal (existing logic, now extended for A/B + other page) */
const clickPickerRawUrlA =
  isCustomCode
    ? customCodePreviewUrl
    : postType === "product" && pageA
    ? getProductPreviewUrl(pageA, "A")
    : "";

const clickPickerRawUrlB =
  isCustomCode
    ? customCodePreviewUrl
    : postType === "product" && pageA
    ? getProductPreviewUrl(
        pageA,
        "B",
        pageB && pageB.id
          ? { abtestkit_shadow_preview_id: pageB.id }
          : {}
      )
    : pageB
    ? getEntityPreviewUrl(pageB, postType)
    : "";

const step4 = h(Fragment, null, [
  h("p", null, "Fine-tune how we count a conversion."),

  // Short reminder of what you've chosen
  goal === "clicks" &&
    h(
      "p",
      { style: { marginTop: 0, color: "#6c7781" } },
      "You're counting a conversion when someone clicks one of the targets you choose below."
    ),

  goal === "clicks" &&
    h(RadioControl, {
      label: "Where are the clicks you want to track?",
      selected: clickScope,
      options: [
        {
          label: "On this test page (Version A/B)",
          value: "on_test_pages",
        },
        {
          label: "On a different page",
          value: "other_page",
        },
      ],
      onChange: setClickScope,
    }),

  goal === "clicks" &&
    h(ClickPreviewModeSelector, {
      value: clickPreviewMode,
      onChange: setClickPreviewMode,
    }),

  goal === "form" &&
    h(
      "p",
      { style: { marginTop: 0, color: "#6c7781" } },
      "You're counting a conversion when a form on this page is successfully submitted."
    ),

  goal === "add_to_cart" &&
    h(
      "p",
      { style: { marginTop: 0, color: "#6c7781" } },
      "You're counting a conversion when this product is successfully added to the cart."
    ),

  goal === "purchase" &&
    h(
      "p",
      { style: { marginTop: 0, color: "#6c7781" } },
      postType === "reusable_section"
        ? "You're counting a conversion when a visitor who saw this reusable section places a WooCommerce order. Revenue will also be tracked for this test."
        : "You're counting a conversion when an order containing this product is placed. Revenue will also be tracked for this test."
    ),

  goal === "destination_url" &&
    h(
      "p",
      { style: { marginTop: 0, color: "#6c7781" } },
      "You're counting a conversion when someone who has seen this test later lands on the destination URL you choose."
    ),

  goal === "scroll_depth" &&
    h(
      "p",
      { style: { marginTop: 0, color: "#6c7781" } },
      "You're counting a conversion when someone scrolls far enough down the page."
    ),

  goal === "scroll_depth" &&
    h(
      "div",
      {
        style: {
          marginTop: 12,
          padding: "12px 14px",
          background: "#f6f7f7",
          border: "1px solid #dcdcde",
          borderRadius: 8,
        },
      },
      [
        h(SelectControl, {
          label: "Scroll depth threshold",
          value: scrollDepth,
          options: [
            { label: "25% of the page", value: "25" },
            { label: "50% of the page", value: "50" },
            { label: "75% of the page", value: "75" },
            { label: "90% of the page", value: "90" },
          ],
          onChange: setScrollDepth,
          help: "A conversion is recorded once per visitor session when they reach this depth on their assigned version.",
        }),
      ]
    ),

  goal === "destination_url" &&
    h(
      "div",
      {
        style: {
          marginTop: 12,
          padding: "12px 14px",
          background: "#f6f7f7",
          border: "1px solid #dcdcde",
          borderRadius: 8,
        },
      },
      [
        h(TextControl, {
          label: "Destination URL(s)",
          help:
            "Enter one or more URLs or paths, separated with commas. Use a full URL like https://example.com/thank-you or a path like /thank-you. Add * at the end to match child URLs or query strings, for example /thank-you*.",
          value: links,
          onChange: setLinks,
          placeholder: "/thank-you or https://example.com/thank-you",
        }),

        h(
          "div",
          {
            style: {
              marginTop: 16,
              paddingTop: 12,
              borderTop: "1px solid #dcdcde",
            },
          },
          [
            h(
              "h4",
              { style: { marginTop: 0, marginBottom: 8 } },
              "Or choose a page, post, or product"
            ),
            h(
              "div",
              {
                style: {
                  display: "grid",
                  gridTemplateColumns: "180px 1fr",
                  gap: 12,
                  alignItems: "end",
                },
              },
              [
                h(SelectControl, {
                  label: "Content type",
                  value: destinationPostType,
                  options: [
                    { label: "Pages", value: "page" },
                    { label: "Posts", value: "post" },
                    { label: "Products", value: "product" },
                  ],
                  onChange: (value) => {
                    setDestinationPostType(value);
                    setGoalPage(null);
                    setGoalPages([]);
                    setGoalPageSearch("");
                  },
                }),
                h(SearchControl, {
                  label:
                    destinationPostType === "product"
                      ? "Search products"
                      : destinationPostType === "post"
                      ? "Search posts"
                      : "Search pages",
                  value: goalPageSearch,
                  onChange: setGoalPageSearch,
                  placeholder:
                    destinationPostType === "product"
                      ? "Search products…"
                      : destinationPostType === "post"
                      ? "Search posts…"
                      : "Search pages…",
                }),
              ]
            ),
            goalPageLoading && h(Spinner),
            h(PageTable, {
              pages: goalPages,
              selectedId: goalPage && goalPage.id,
              selectedValues: destinationTargets(),
              onSelect: addDestinationEntity,
              onRemove: removeDestinationEntity,
              allowLockedSelect: true,
              selectionMode: "toggle",
              getItemValue: destinationTargetFromEntity,
              empty:
                destinationPostType === "product"
                  ? "No matching products."
                  : destinationPostType === "post"
                  ? "No matching posts."
                  : "No matching pages.",
            }),
          ]
        ),

        h(
          "p",
          {
            style: {
              margin: "8px 0 0",
              color: "#6c7781",
              fontSize: 12,
              lineHeight: 1.45,
            },
          },
          "This is tracked on page load. Selected pages, posts, and products are added with a wildcard so query strings on confirmation URLs still count."
        ),
      ]
    ),

  // Click pickers for Version A + Version B on the tested page/product.
  goal === "clicks" &&
  clickScope === "on_test_pages" &&
    pageA &&
    h(
      "div",
      {
        style: {
          marginTop: 12,
          display: "grid",
          gridTemplateColumns:
            (pageB || isCustomCode) && clickPreviewMode !== "desktop"
              ? "1fr 1fr"
              : "1fr",
          gap: 16,
        },
      },
      [
        h(
          "div",
          null,
          [
            h(
              "h4",
              { style: { marginTop: 0, marginBottom: 8 } },
              "Pick targets on Version A"
            ),
            h(ElementPicker, {
              pageId: pageA.id,
              viewBase: getPreviewBase(isCustomCode ? cssScope : postType),
              rawUrl: clickPickerRawUrlA,
              goal,
              label: "Version A",
              previewMode: clickPreviewMode,
              selected: (links || "")
                .split(",")
                .map((s) => s.trim())
                .filter(Boolean),
              onWarn: (msg) => {
                console.warn("[abtestkit picker]", msg);
              },
              onPick: handlePick,
            }),
          ]
        ),
        (pageB || isCustomCode) &&
          h(
            "div",
            null,
            [
              h(
                "h4",
                { style: { marginTop: 0, marginBottom: 8 } },
                "Pick targets on Version B"
              ),
              h(ElementPicker, {
                pageId: isCustomCode
                  ? pageA.id
                  : postType === "product"
                  ? pageA.id
                  : pageB.id,
                viewBase: getPreviewBase(isCustomCode ? cssScope : postType),
                rawUrl: clickPickerRawUrlB,
                goal,
                label: "Version B",
                previewMode: clickPreviewMode,
                previewVariant: isCustomCode ? "B" : "",
                previewCss: isCustomCss ? customCss : "",
                previewMarkers: isCustomCss ? cssMarkers : [],
                previewHtmlChanges: isCustomHtml ? customHtmlChanges : [],
                selected: (links || "")
                  .split(",")
                  .map((s) => s.trim())
                  .filter(Boolean),
                onWarn: (msg) => {
                  console.warn("[abtestkit picker]", msg);
                },
                onPick: handlePick,
              }),
            ]
          ),
      ]
    ),

      // "Clicks on a different page" flow (page tests only)
    goal === "clicks" &&
      clickScope === "other_page" &&
      postType !== "product" &&
      h(
        "div",
      {
        style: {
          marginTop: 16,
          paddingTop: 8,
          borderTop: "1px solid #dcdcde",
        },
      },
      [
        h(
          "h4",
          { style: { marginTop: 0, marginBottom: 8 } },
          "Pick the page where the conversion click happens"
        ),
        h(SearchControl, {
          label: "Search pages for your conversion page",
          value: goalPageSearch,
          onChange: setGoalPageSearch,
          placeholder: "Search by title…",
        }),
        goalPageLoading && h(Spinner),
        h(PageTable, {
          pages: goalPages,
          selectedId: goalPage && goalPage.id,
          onSelect: (p) => setGoalPage(p),
          empty: "No matching pages.",
        }),
        goalPage &&
          h(
            "div",
            { style: { marginTop: 12 } },
            [
              h(
                "p",
                {
                  style: {
                    margin: "0 0 6px",
                    fontSize: 12,
                    color: "#6c7781",
                  },
                },
                "Now click a target in the preview to track conversions from this page."
              ),
              h(ElementPicker, {
                pageId: goalPage.id,
                viewBase: abtestkit_PT.viewBase,
                goal,
                label: goalPage.title || "Conversion page",
                previewMode: clickPreviewMode,
                selected: (links || "")
                  .split(",")
                  .map((s) => s.trim())
                  .filter(Boolean),
                onWarn: (msg) => {
                  console.warn("[abtestkit picker]", msg);
                },
                onPick: handlePick,
              }),
            ]
          ),
      ]
    ),

  // Advanced manual field (hidden under a small "Advanced" dropdown)
  goal === "clicks" &&
    h(
      "div",
      { style: { marginTop: 12, marginBottom: 4 } },
      [
        h(
          Button,
          {
            isSecondary: true,
            isSmall: true,
            onClick: () => setShowManualTargets((s) => !s),
          },
          showManualTargets ? "Hide advanced" : "Advanced"
        ),
        showManualTargets &&
          h(
            "div",
            {
              style: {
                marginTop: 6,
                padding: "8px 10px",
                background: "#f6f7f7",
                borderRadius: 4,
                border: "1px solid #dcdcde",
              },
            },
            [
              h(
                "p",
                {
                  style: {
                    margin: "0 0 6px",
                    fontSize: 12,
                    color: "#6c7781",
                  },
                },
                "Manually pick target URLs or CSS selectors (advanced)."
              ),
              h(TextControl, {
                className: "abtestkit-targets-input",
                label:
                  "Targets (comma-separated URLs or CSS selectors)",
                help:
                  "Advanced users only - the click picker above fills this automatically.",
                value: links,
                onChange: setLinks,
                placeholder: "",
              }),
            ]
          ),
      ]
    ),

  // Pretty list of picked targets (cards) – BELOW the manual box
  goal === "clicks" &&
    h(PrettyPickedList, {
      picks: prettyPicks,
      onRemove: (p) => {
        // Remove from the visible list
        setPrettyPicks((prev) =>
          prev.filter(
            (x) =>
              (x.selector || "") + "||" + (x.href || "") !==
              (p.selector || "") + "||" + (p.href || "")
          )
        );

        // Also remove corresponding tokens from the CSV (selector and/or href)
        const norm = (s) => {
          const raw = (s || "").trim();
          const clean = raw.replace(/\/+$/, "");
          return clean || (raw === "/" ? "/" : "");
        };
        const raw = (links || "")
          .split(",")
          .map((s) => s.trim())
          .filter(Boolean);

        const removeSet = new Set();
        if (p.selector) removeSet.add(p.selector);

        if (p.href) {
          const base = norm(p.href);
          // tokens might be stored as "path?x=y*" or "path?x=y"
          removeSet.add(base);
          removeSet.add(base + "*");
        }

        const filtered = raw.filter((t) => {
          const tNormNoStar = norm(t.replace(/\*$/, ""));
          // remove if exact match, wildcard match, or normalized match
          if (removeSet.has(t)) return false;
          if (removeSet.has(tNormNoStar)) return false;
          if (removeSet.has(tNormNoStar + "*")) return false;
          return true;
        });

        setLinks(filtered.join(", "));
      },
    }),
]);

/* Step 6 – Summary */

// Default-friendly labels for the test title field
const defaultSuggestedTitle =
  decodeEntities(
    (pageA && pageA.title) ? pageA.title : ""
  );

const summaryTestTitle =
  String(testTitle || "").trim() || defaultSuggestedTitle;

// Preview URLs for Version A/B in a new tab (no tracking / no impressions)
const customCodeSummaryBaseUrl =
  isCustomCode && customCodePreviewUrl
    ? abtkSetPreviewQueryParam(customCodePreviewUrl, "abtestkit_preview", "1")
    : "";

const previewUrlA =
  pageA
    ? (
        isCustomCode
          ? abtkSetPreviewQueryParam(
              abtkSetPreviewQueryParam(
                abtkSetPreviewQueryParam(customCodeSummaryBaseUrl, "abtestkit_force", "A"),
                "abtestkit_preview",
                "1"
              ),
              "abtestkit_r",
              Date.now()
            )
          : postType === "product"
          ? getProductPreviewUrl(pageA, "A")
          : getEntityPreviewUrl(pageA, postType)
      )
    : "";

const previewUrlB =
  isCustomCode
    ? (
        pageA
          ? abtkSetPreviewQueryParam(
              abtkSetPreviewQueryParam(
                abtkSetPreviewQueryParam(customCodeSummaryBaseUrl, "abtestkit_force", "B"),
                "abtestkit_preview",
                "1"
              ),
              "abtestkit_r",
              Date.now()
            )
          : ""
      )
    : postType === "product"
    ? (
        pageA
          ? getProductPreviewUrl(
              pageA,
              "B",
              pageB && pageB.id
                ? { abtestkit_shadow_preview_id: pageB.id }
                : {}
            )
          : ""
      )
    : (pageB ? getEntityPreviewUrl(pageB, postType) : "");

const openCustomCodeSummaryPreview = (variant = "B") => {
  const isVersionB = String(variant || "").toUpperCase() === "B";
  const src = isVersionB ? previewUrlB : previewUrlA;

  if (!src) {
    return;
  }

  const popup = window.open("", "_blank");

  if (!popup) {
    setError("Could not open the preview window. Please allow popups for this site and try again.");
    return;
  }

  const navigatePopup = (nextUrl) => {
    const safeUrl = String(nextUrl || "").trim();

    if (!safeUrl) {
      try {
        popup.close();
      } catch (_) {}
      return;
    }

    try {
      popup.opener = null;
    } catch (_) {}

    popup.location.href = safeUrl;
  };

  if (!isVersionB) {
    navigatePopup(src);
    return;
  }

  if (!cfg || !cfg.nonce || !pageA || !pageA.id) {
    navigatePopup(src);
    return;
  }

  apiFetch({
    path: isCustomHtml ? "/abtestkit/v1/pt/custom-html-preview" : "/abtestkit/v1/pt/custom-css-preview",
    method: "POST",
    headers: { "X-WP-Nonce": cfg.nonce, "Content-Type": "application/json" },
    data: {
      control_id: parseInt(pageA.id, 10) || 0,
      custom_css: isCustomCss ? String(customCss || "") : "",
      css_markers: isCustomCss && Array.isArray(cssMarkers) ? cssMarkers : [],
      html_changes: isCustomHtml && Array.isArray(customHtmlChanges) ? customHtmlChanges : [],
    },
  })
    .then((response) => {
      if (!response || !response.ok || !response.token) {
        navigatePopup(src);
        return;
      }

      let previewSrc = src;
      previewSrc = abtkSetPreviewQueryParam(previewSrc, "abtestkit_preview", "1");
      previewSrc = abtkSetPreviewQueryParam(previewSrc, "abtestkit_force", "B");
      previewSrc = abtkSetPreviewQueryParam(
        previewSrc,
        isCustomHtml ? "abtestkit_custom_html_preview" : "abtestkit_custom_css_preview",
        response.token
      );
      previewSrc = abtkSetPreviewQueryParam(previewSrc, "abtestkit_r", Date.now());

      navigatePopup(previewSrc);
    })
    .catch(() => {
      setError(`Could not prepare the Custom ${isCustomHtml ? "HTML" : "CSS"} preview. Opening the normal preview instead.`);
      navigatePopup(src);
    });
};
const step5 = h(Fragment, null, [
  showWizardCompatibilityHelp &&
    h(
      "div",
      {
        role: "dialog",
        "aria-modal": "true",
        "aria-labelledby": "abtestkit-wizard-compatibility-help-title",
        onClick: closeWizardCompatibilityHelpModal,
        style: {
          position: "fixed",
          inset: 0,
          zIndex: 100000,
          background: "rgba(0,0,0,0.35)",
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          padding: "24px",
        },
      },
      h(
        "div",
        {
          onClick: (e) => e.stopPropagation(),
          style: {
            width: "100%",
            maxWidth: "560px",
            background: "#fff",
            borderRadius: 8,
            boxShadow: "0 18px 60px rgba(0,0,0,0.22)",
            border: "1px solid #dcdcde",
            overflow: "hidden",
          },
        },
        [
          h(
            "div",
            {
              style: {
                padding: "18px 20px",
                borderBottom: "1px solid #e5e7eb",
                display: "flex",
                alignItems: "center",
                justifyContent: "space-between",
                gap: 12,
              },
            },
            [
              h(
                "h2",
                {
                  id: "abtestkit-wizard-compatibility-help-title",
                  style: {
                    margin: 0,
                    fontSize: 18,
                    lineHeight: 1.3,
                  },
                },
                "Something not right?"
              ),
              h(
                "button",
                {
                  type: "button",
                  onClick: closeWizardCompatibilityHelpModal,
                  disabled: wizardCompatibilitySubmitting,
                  "aria-label": "Close compatibility help request",
                  style: {
                    border: 0,
                    background: "transparent",
                    padding: 0,
                    margin: 0,
                    width: 24,
                    height: 24,
                    display: "inline-flex",
                    alignItems: "center",
                    justifyContent: "center",
                    cursor: wizardCompatibilitySubmitting ? "default" : "pointer",
                    color: "#50575e",
                    fontSize: 20,
                    lineHeight: 1,
                  },
                },
                "×"
              ),
            ]
          ),
          h(
            "div",
            {
              style: {
                padding: "18px 20px 20px",
              },
            },
            [
              wizardCompatibilityStatus === "sent"
                ? h(
                    "div",
                    {
                      className: "notice notice-success",
                      style: {
                        margin: "0 0 14px",
                        padding: "10px 12px",
                      },
                    },
                    h("p", { style: { margin: 0 } }, "Thanks — your compatibility help request has been sent.")
                  )
                : null,
              wizardCompatibilityStatus === "error"
                ? h(
                    "div",
                    {
                      className: "notice notice-error",
                      style: {
                        margin: "0 0 14px",
                        padding: "10px 12px",
                      },
                    },
                    h("p", { style: { margin: 0 } }, "Sorry, the request could not be sent. Please try again.")
                  )
                : null,
                h(
                  "p",
                  {
                    style: {
                      margin: "0 0 10px",
                      fontSize: 13,
                      lineHeight: 1.5,
                    },
                  },
                  "Noticed something odd in the previews? Tell us what looks wrong before you start the test."
                ),
                h(
                  "p",
                  {
                    style: {
                      margin: "0 0 14px",
                      fontSize: 13,
                      lineHeight: 1.5,
                    },
                  },
                  "We’ll include a small diagnostic snapshot of your setup so we can understand what might be causing it."
                ),
                h(
                  "label",
                  {
                    htmlFor: "abtestkit-wizard-compatibility-help-message",
                    style: {
                      display: "block",
                      marginBottom: 6,
                      fontWeight: 600,
                      fontSize: 13,
                    },
                  },
                  "What looks wrong?"
                ),
                h("textarea", {
                  id: "abtestkit-wizard-compatibility-help-message",
                  value: wizardCompatibilityMessage,
                  onChange: (e) => setWizardCompatibilityMessage(e.target.value),
                  placeholder: "Example: Version B looks different, clicks aren't tracking, or the cart total doesn't match.",
                rows: 4,
                disabled: wizardCompatibilitySubmitting || wizardCompatibilityStatus === "sent",
                style: {
                  width: "100%",
                  minHeight: 96,
                  resize: "vertical",
                  marginBottom: 10,
                },
              }),
              h(
                "p",
                {
                  style: {
                    margin: "0 0 16px",
                    fontSize: 12,
                    lineHeight: 1.45,
                    color: "#6b7280",
                  },
                },
                "This sends diagnostic information about your WordPress setup and this test configuration. It does not send passwords, customer data, order details, or private page content."
              ),
              h(
                "div",
                {
                  style: {
                    display: "flex",
                    justifyContent: "flex-end",
                    gap: 8,
                    flexWrap: "wrap",
                  },
                },
                [
                  h(
                    Button,
                    {
                      isSecondary: true,
                      onClick: closeWizardCompatibilityHelpModal,
                      disabled: wizardCompatibilitySubmitting,
                    },
                    wizardCompatibilityStatus === "sent" ? "Close" : "Cancel"
                  ),
                  wizardCompatibilityStatus !== "sent"
                    ? h(
                        Button,
                        {
                          isPrimary: true,
                          onClick: submitWizardCompatibilityHelp,
                          disabled: wizardCompatibilitySubmitting,
                        },
                        wizardCompatibilitySubmitting ? "Sending…" : "Send help request"
                      )
                    : null,
                ].filter(Boolean)
              ),
            ].filter(Boolean)
          ),
        ]
      )
    ),
h(
  Card,
  { style: { marginBottom: 16 } },
    h(CardBody, null, [
      h("h3", { style: { marginTop: 0, marginBottom: 12 } }, "Test title"),
h(
  "div",
  {
    style: {
      position: "relative",
      maxWidth: "520px",
    },
  },
  [
h(
  "div",
  {
    style: {
      maxWidth: "520px",
    },
  },
  [
    h(
      "div",
      {
        style: {
          position: "relative",
        },
      },
      [
        h("input", {
         className: "abtestkit-test-title-input",
          type: "text",
          value: testTitle,
          onChange: (e) => {
            testTitleManuallyEditedRef.current = true;
            setTestTitle(e.target.value);
          },
          placeholder: defaultSuggestedTitle || "e.g. Product page hero CTA test",
          style: {
            width: "100%",
            height: "40px",
            padding: "0 42px 0 12px",
            border: "1px solid #8c8f94",
            borderRadius: "2px",
            fontSize: "16px",
            lineHeight: "1.4",
            boxSizing: "border-box",
            background: "#fff",
          },
        }),

        h(
          "button",
          {
            type: "button",
            onClick: () => {
              const input = document.querySelector(".abtestkit-test-title-input");
              if (input) input.focus();
            },
            style: {
              position: "absolute",
              right: "10px",
              top: "50%",
              transform: "translateY(-50%)",
              border: "0",
              background: "transparent",
              padding: 0,
              margin: 0,
              width: "18px",
              height: "18px",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              cursor: "pointer",
              color: "#2271b1",
            },
            "aria-label": "Edit test title",
            title: "Edit test title",
          },
          h("span", {
            className: "dashicons dashicons-edit",
            style: {
              fontSize: "16px",
              width: "16px",
              height: "16px",
            },
          })
        ),
      ]
    ),

    h(
      "p",
      {
        style: {
          marginTop: "8px",
          marginBottom: 0,
          color: "#6c7781",
          fontSize: "12px",
        },
      },
      "Give your test a name to help you identify it later."
    ),
  ]
),
  ]
),
    ])
  ),
  h(
    Card,
    { style: { marginBottom: 16 } },
    h(CardBody, null, [
      h("h3", { style: { marginTop: 0, marginBottom: 12 } }, "Auto decision mode"),
      h(
        "div",
        { style: { maxWidth: "520px" } },
        h(SelectControl, {
          label: "Decision mode",
          hideLabelFromVision: true,
          value: String(decisionRule),
          options: [
            { label: "Fast, less precise (25 impressions + 3 conversions)", value: "fast" },
            { label: "Balanced (50 impressions + 5 conversions)", value: "balanced" },
            { label: "Precise (75 impressions + 10 conversions)", value: "precise" },
            { label: "Manual (never auto-confirm a winner)", value: "manual" },
          ],
          onChange: (v) => setDecisionRule(v),
          help:
            String(decisionRule) === "manual"
              ? "Manual mode never auto-confirms a winner. You can apply the current leader from the dashboard."
              : "A winner will not be declared until both the impressions and conversions minimums are met.",
        })
      ),
    ])
  ),
  h("p", null, "Review and confirm:"),
  h(
    Card,
    null,
    h(CardBody, null, [
      h(ListItem, {
        label: "Test title",
        value: summaryTestTitle || "—",
      }),
      h(ListItem, {
        label: "Test type",
        value:
          isCustomHtml
            ? "Custom HTML"
            : isCustomCss
            ? "Custom CSS"
            : postType === "product"
            ? "WooCommerce product"
            : postType === "post"
            ? "Post"
            : postType === "reusable_section"
            ? "Reusable Section"
            : "Page",
      }),


      h(ListItem, {
        label: "Version A (Control)",
        value: pageA ? decodeEntities(`${pageA.title}`) : "—",
      }),
      isCustomCode
        ? h(ListItem, {
            label: "Version B",
            value: isCustomHtml
              ? "Custom HTML applied to selected elements on the original page"
              : "Custom CSS applied to the original page",
          })
        : h(ListItem, {
        label: postType === "product" ? "Version B" : "Version B Source",
        value:
          postType === "product"
            ? h(
                "div",
                {
                  style: {
                    display: "flex",
                    flexDirection: "column",
                    alignItems: "flex-start",
                    lineHeight: "1.4",
                    gap: "2px",
                  },
                },
                (() => {
                  const parts = [];

                  const shadowTitle =
                    pageB && pageB.title
                      ? decodeEntities(`${pageB.title}`)
                      : productBTitle && productBTitle.trim() !== ""
                      ? productBTitle.trim()
                      : pageA && pageA.title
                      ? decodeEntities(`${pageA.title}`)
                      : "Version B";

                  parts.push(
                    h(
                      "div",
                      {
                        key: "shadow-product-title"
                      },
                      `${shadowTitle} (shadow product)`
                    )
                  );
                  return parts;
                })()
              )
            : bMode === "duplicate"
            ? pageB
              ? `${decodeEntities(`${pageB.title}`)} (${postType === "post" ? "shadow post" : "shadow page"})`
              : `Version B (${postType === "post" ? "shadow post" : "shadow page"})`
            : pageB
            ? `${pageB.title}`
            : "—",
      }),

      isCustomCode
        ? h(ListItem, {
            label: isCustomHtml ? "HTML location" : "CSS location",
            value:
              cssScope === "product"
                ? "WooCommerce product"
                : cssScope === "post"
                ? "Post"
                : "Page",
          })
        : null,

      isCustomCss
        ? h(ListItem, {
            label: "B-only CSS classes",
            value: cssMarkers.length
              ? cssMarkers.map((marker) => `${marker.label}: .${marker.class_name}`).join(", ")
              : "None",
          })
        : null,

      isCustomHtml
        ? h(ListItem, {
            label: "HTML selectors",
            value: customHtmlChanges.length
              ? customHtmlChanges
                  .map(
                    (change) =>
                      `${change.label || "Selected element"}: ${change.selector} — ${customHtmlOperationLabel(change.operation)} — ${String(change.match_mode || "all") === "first" ? "first match" : "all matches"}`
                  )
                  .join(", ")
              : "None",
          })
        : null,

      // Human-friendly goal label
      h(ListItem, {
        label: "Conversion Goal",
        value:
          goal === "form"
            ? "Form submission"
            : goal === "add_to_cart"
            ? "Add to cart"
            : goal === "purchase"
            ? "Completed Orders (Revenue)"
            : goal === "destination_url"
            ? "Destination URL"
            : goal === "scroll_depth"
            ? "Scroll depth"
            : "Clicks (buttons/links/other)",
      }),

      goal === "scroll_depth"
        ? h(ListItem, {
            label: "Scroll depth threshold",
            value: `${scrollDepth}% of the page`,
          })
        : null,

      // where clicks are tracked
      goal === "clicks"
        ? h(ListItem, {
            label: "Clicks tracked on",
            value:
              clickScope === "other_page"
                ? (goalPage && goalPage.title
                    ? `Different page: ${goalPage.title}`
                    : "A different page")
                : "This test page (Version A/B)",
          })
        : null,

      // Existing: list the targets
      goal === "clicks"
        ? h(ListItem, {
            label: "Click Targets (URLs/selectors)",
            value: links || "—",
          })
        : null,

      goal === "destination_url"
        ? h(ListItem, {
            label: "Destination URL(s)",
            value: links || "—",
          })
        : null,

      h(ListItem, { label: "Split", value: "50% to Version B" }),

      // Preview links row
      (previewUrlA || previewUrlB) &&
        h(
          "div",
          {
            style: {
              marginTop: 12,
              display: "flex",
              flexDirection: "column",
              alignItems: "flex-end",
              gap: 8,
            },
          },
          [
            h(
              "div",
              {
                style: {
                  display: "flex",
                  justifyContent: "flex-end",
                  alignItems: "center",
                  gap: 8,
                  flexWrap: "wrap",
                },
              },
              [
                previewUrlA &&
                  h(
                    "a",
                    {
                      href: previewUrlA,
                      target: "_blank",
                      rel: "noopener noreferrer",
                      "aria-label": "Preview Version A",
                      style: {
                        minHeight: 36,
                        border: "1px solid #3858e9",
                        borderRadius: 4,
                        background: "#3858e9",
                        color: "#fff",
                        textDecoration: "none",
                        display: "inline-flex",
                        alignItems: "center",
                        justifyContent: "center",
                        gap: 6,
                        padding: "0 14px",
                        fontSize: 13,
                        fontWeight: 500,
                        lineHeight: 1,
                        whiteSpace: "nowrap",
                        flex: "0 0 auto",
                        boxSizing: "border-box",
                        boxShadow: "none",
                      },
                      title: "Preview Version A",
                    },
                    [
                      "Preview Version A",
                      h("span", {
                        className: "dashicons dashicons-external",
                        style: {
                          fontSize: 16,
                          width: 16,
                          height: 16,
                        },
                      }),
                    ]
                  ),

                previewUrlB &&
                  h(
                    "a",
                    {
                      href: previewUrlB,
                      target: "_blank",
                      rel: "noopener noreferrer",
                      "aria-label": "Preview Version B",
                      onClick:
                        isCustomCode
                          ? (e) => {
                              e.preventDefault();
                              openCustomCodeSummaryPreview("B");
                            }
                          : undefined,
                      style: {
                        minHeight: 36,
                        border: "1px solid #3858e9",
                        borderRadius: 4,
                        background: "#3858e9",
                        color: "#fff",
                        textDecoration: "none",
                        display: "inline-flex",
                        alignItems: "center",
                        justifyContent: "center",
                        gap: 6,
                        padding: "0 14px",
                        fontSize: 13,
                        fontWeight: 500,
                        lineHeight: 1,
                        whiteSpace: "nowrap",
                        flex: "0 0 auto",
                        boxSizing: "border-box",
                        boxShadow: "none",
                      },
                      title: "Preview Version B",
                    },
                    [
                      "Preview Version B",
                      h("span", {
                        className: "dashicons dashicons-external",
                        style: {
                          fontSize: 16,
                          width: 16,
                          height: 16,
                        },
                      }),
                    ]
                  ),
              ].filter(Boolean)
            ),
            wizardCompatibilityHelpLink(),
          ]
        ),
    ])
  ),
  error && h(Notice, { status: "error", isDismissible: false }, error),
]);

    // ── Updated steps array ─────────────────────────
    // For WooCommerce products we skip the "Version B source" step.
    let steps;
    if (isCustomCode) {
      steps = [
        {
          title: "Select test type",
          content: stepType,
          canNext: canNext0,
        },
        {
          title: "Choose code type",
          content: stepCustomCodeType,
          canNext: canNextCustomCodeType,
        },
        {
          title: isCustomHtml ? "Choose HTML location" : "Choose CSS location",
          content: step0,
          canNext: canNext1,
        },
        {
          title: "Build Version B",
          content: step2,
          canNext: canNext3,
        },
        {
          title: "Choose conversion type",
          content: step3,
          canNext: canNext4,
        },
      ]
        .concat(
          goal === "clicks" || goal === "destination_url" || goal === "scroll_depth"
            ? [
                {
                  title:
                    goal === "destination_url"
                      ? "Set destination URL"
                      : goal === "scroll_depth"
                      ? "Set scroll depth"
                      : "Select click targets",
                  content: step4,
                  canNext: canNext5,
                },
              ]
            : []
        )
        .concat([
          {
            title: "Summary",
            content: step5,
            canNext: canNext6,
          },
        ]);
    } else if (postType === "product") {
      steps = [
        {
          title: "Select test type",
          content: stepType,
          canNext: canNext0,
        },
        {
          title: "Select product",
          content: step0,
          canNext: canNext1,
        },
        {
          title: "Build Version B",
          content: step2,
          canNext: canNext3,
        },
        {
          title: "Choose conversion type",
          content: step3,
          canNext: canNext4,
        },
      ]
        .concat(
          goal === "clicks" || goal === "destination_url" || goal === "scroll_depth"
            ? [
                {
                  title:
                    goal === "destination_url"
                      ? "Set destination URL"
                      : goal === "scroll_depth"
                      ? "Set scroll depth"
                      : "Select click targets",
                  content: step4,
                  canNext: canNext5,
                },
              ]
            : []
        )
        .concat([
          {
            title: "Summary",
            content: step5,
            canNext: canNext6,
          },
        ]);
    } else {
      // Default flow for normal pages/posts – keep all 7 steps
      steps = [
        {
          title: "Select test type",
          content: stepType,
          canNext: canNext0,
        },
        {
          title:
            postType === "post"
              ? "Select post"
              : postType === "reusable_section"
              ? "Select reusable source page"
              : "Select page",
          content: step0,
          canNext: canNext1,
        },
        {
          title: "Version B source",
          content: step1,
          canNext: canNext2,
        },
        {
          title: "Build Version B",
          content: step2,
          canNext: canNext3,
        },
        {
          title: "Choose conversion type",
          content: step3,
          canNext: canNext4,
        },
      ]
        .concat(
          goal === "clicks" || goal === "destination_url" || goal === "scroll_depth"
            ? [
                {
                  title:
                    goal === "destination_url"
                      ? "Set destination URL"
                      : goal === "scroll_depth"
                      ? "Set scroll depth"
                      : "Select click targets",
                  content: step4,
                  canNext: canNext5,
                },
              ]
            : []
        )
        .concat([
          {
            title: "Summary",
            content: step5,
            canNext: canNext6,
          },
        ]);
    }

    // If the user changes conversion type, the number of steps can change.
    // Clamp the current step so we never point past the end of the steps array.
    useEffect(() => {
      const max = steps.length - 1;
      if (step > max) setStep(max);
    }, [goal, postType, steps.length]);

    // Next button state and hover helper

    const isLastStep = step === steps.length - 1;
    const canGoNext = steps[step].canNext;
    const nextDisabled = isLastStep || !canGoNext;

    // Tooltip hints and blocking logic (index-based = reliable)
    let nextTitle = "";

    // Determine the absolute index of key steps (varies when click-target step is hidden)
    const showClickTargetsStep = goal === "clicks" || goal === "destination_url" || goal === "scroll_depth";

    const reviewStepIndex = isCustomCode ? 3 : postType === "product" ? 2 : 3;
    const conversionTypeIndex = isCustomCode ? 4 : postType === "product" ? 3 : 4;

    const conversionGoalIndex = showClickTargetsStep
      ? (isCustomCode ? 5 : postType === "product" ? 4 : 5)
      : -1;

    // Auto-create the variation (Version B) when entering Build Version B in "duplicate" mode
    const autoCreatedBRef = useRef(false);

    useEffect(() => {
      // Custom Code tests do not create a physical Version B page.
      if (isCustomCode) return;

      // Only in duplicate mode
      if (bMode !== "duplicate") {
        autoCreatedBRef.current = false;
        return;
      }

      // Only when we're on the Build Version B step
      if (step !== reviewStepIndex) return;

      // If B already exists or we're already creating it, do nothing
      if (pageB || loading) return;

      // Prevent double-creation on rerenders
      if (autoCreatedBRef.current) return;
      autoCreatedBRef.current = true;

      createBDraftNow();
    }, [step, reviewStepIndex, postType, bMode, pageB, loading]);

    // Build Version B blocking
    if (step === reviewStepIndex && !canGoNext) {
      if (isCustomHtml) {
        nextTitle = "Select an element and add Version B HTML";
      } else if (isCustomCss) {
        nextTitle = "Add Version B CSS";
      } else if (postType === "product") {
        nextTitle = "Edit at least one Version B field";
      } else {
        nextTitle = "Edit Version B first";
      }
    }

    // Conversion type blocking
    else if (step === conversionTypeIndex && !canGoNext) {
      nextTitle = "Choose a conversion goal";
    }

    // Target blocking (only when that step exists)
    else if (conversionGoalIndex !== -1 && step === conversionGoalIndex && goal === "clicks" && !canGoNext) {
      nextTitle = "Select at least one click target";
    } else if (conversionGoalIndex !== -1 && step === conversionGoalIndex && goal === "destination_url" && !canGoNext) {
      nextTitle = "Enter at least one destination URL";
    }

    // Hide the left-hand stepper on the first screen so the
    // page-type cards can use the full width.
    const showStepper = step > 0 && !!postType;
    const layoutStyle = showStepper
      ? {
          display: "grid",
          gridTemplateColumns: "172px 1fr",
          gap: 12,
          alignItems: "start",
        }
      : {
          display: "block",
        };

    return h(
      "div",
      {
        style: layoutStyle,
      },
      [
        // Left stepper (hidden until a page/product type is chosen)
        showStepper
          ? h(
              "div",
              null,
              steps.map((s, i) =>
                h(Step, { key: i, title: s.title, index: i, current: step })
              )
            )
          : null,

        // Right content
        h("div", null, [
          h(Card, null, h(CardBody, null, [
            h("h2", { style: { marginTop: 0 } }, steps[step].title),
            steps[step].content,
            h("div", { style: { display: "flex", justifyContent: "space-between", marginTop: 16 } }, [
              // BACK
              h(
                Button,
                {
                  isSecondary: true,
                  disabled: step === 0,
onClick: () => {
                    const isClickGoal = goal === "clicks";
                    const isDestinationGoal = goal === "destination_url";
                    const clickTargetStep = isCustomCode ? 5 : postType === "product" ? 4 : 5;
                    const leavingClickTargets = (isClickGoal || isDestinationGoal) && step === clickTargetStep;
                    const leavingBuildVersionB = step === reviewStepIndex;

                    const hasSetupToLose =
                      !!pageA ||
                      !!pageB ||
                      !!tempBDraftId ||
                      !!hasEditedB ||
                      String(testTitle || "").trim() !== "" ||
                      String(goal || "").trim() !== "" ||
                      !!conversionChosen ||
                      String(links || "").trim() !== "" ||
                      !!productPreviewToken;

                    const goBackOne = () => {
                      tlmNavDirRef.current = "back";
                      setStep(Math.max(0, step - 1));

                      setTimeout(() => {
                        window.scrollTo({ top: 0, behavior: "auto" });
                      }, 0);
                    };

                    if (leavingBuildVersionB && isCustomCode) {
                      const ok = window.confirm(
                        isCustomHtml
                          ? "Are you sure you want to go back?\n\nYour unsaved Version B HTML changes and selected elements will be cleared.\n\nPress OK to continue."
                          : "Are you sure you want to go back?\n\nYour unsaved Version B CSS and added class selections will be cleared.\n\nPress OK to continue."
                      );

                      if (!ok) return;

                      clearCustomCodeState();

                      tlmSend("pt_wizard_action", {
                        action: "reset_on_back_from_custom_code_build_b",
                        value: 1,
                        step: tlmStepKey(),
                      });

                      goBackOne();
                      return;
                    }

                    if (leavingBuildVersionB) {
                      const ok = window.confirm(
                        postType === "product"
                          ? "Going back will delete the Version B shadow product and clear all progress for this setup.\n\nPress OK to return to product selection."
                          : "Going back will delete the Version B shadow and clear all progress made after choosing the Version B source.\n\nPress OK to return to Version B source."
                      );

                      if (!ok) return;

                      setError("");
                      setLoading(true);

                      cleanupWizardDuplicate({ silent: false })
                        .then((result) => {
                          if (!result || result.ok === false) {
                            return;
                          }

                          autoCreatedBRef.current = false;

                          if (postType === "product") {
                            resetToControlSelection();
                            setStep(1);
                          } else {
                            clearVersionBAndDownstream();
                            setStep(2);
                          }

                          tlmSend("pt_wizard_action", {
                            action: "reset_on_back_from_build_b",
                            value: 1,
                            step: tlmStepKey(),
                          });

                          setTimeout(() => {
                            window.scrollTo({ top: 0, behavior: "auto" });
                          }, 0);
                        })
                        .finally(() => {
                          setLoading(false);
                        });

                      return;
                    }

                    if (isCustomCode && step === 2 && hasSetupToLose) {
                      const ok = window.confirm(
                        "Going back to code type will clear the selected page and any Custom Code progress.\n\nPress OK to continue."
                      );

                      if (!ok) return;

                      resetToControlSelection();
                      tlmNavDirRef.current = "back";
                      setStep(1);

                      tlmSend("pt_wizard_action", {
                        action: "reset_on_back_to_custom_code_type",
                        value: 1,
                        step: tlmStepKey(),
                      });

                      setTimeout(() => {
                        window.scrollTo({ top: 0, behavior: "auto" });
                      }, 0);

                      return;
                    }

                    if (step === 1 && hasSetupToLose) {
                      const ok = window.confirm(
                        "Going back to test type will clear this setup.\n\nAll progress will be lost.\n\nPress OK to continue."
                      );

                      if (!ok) return;

                      setError("");
                      setLoading(true);

                      cleanupWizardDuplicate({ silent: false })
                        .then((result) => {
                          if (!result || result.ok === false) {
                            return;
                          }

                          autoCreatedBRef.current = false;
                          resetWizardState();
                          setPostType("");
                          setStep(0);

                          tlmSend("pt_wizard_action", {
                            action: "reset_on_back_to_type",
                            value: 1,
                            step: tlmStepKey(),
                          });

                          setTimeout(() => {
                            window.scrollTo({ top: 0, behavior: "auto" });
                          }, 0);
                        })
                        .finally(() => {
                          setLoading(false);
                        });
                      return;
                    }

                    if (leavingClickTargets) {
                      const hasTargets =
                        (links && links.trim().length > 0) ||
                        (prettyPicks && prettyPicks.length > 0);

                      if (hasTargets) {
                        const ok = window.confirm(
                          isDestinationGoal
                            ? "Going back will clear your selected destination URLs.\n\nPress OK to continue."
                            : "Going back will clear your selected click targets.\n\nPress OK to continue."
                        );

                        if (!ok) return;

                        clearClickTargetState();

                        tlmSend("pt_wizard_action", {
                          action: "cleared_targets_on_back",
                          value: 1,
                          step: tlmStepKey(),
                        });
                      }
                    }

                    goBackOne();
                  },
                },
                "Back"
              ),

              // RIGHT SIDE
              isLastStep
                ? h(
                    "span",
                    { style: { display: "inline-flex", gap: 8 } },
                    [
                      h(
                        Button,
                        {
                          isSecondary: true,
                          onClick: () => onCreate(false),
                        },
                        "Save as draft"
                      ),
                      h(
                        Button,
                        {
                          isPrimary: true,
                          onClick: () => onCreate(true),
                        },
                        "Start test"
                      ),
                    ]
                  )
                : h(
                    Tooltip,
                    { text: (nextDisabled && nextTitle) ? nextTitle : "", position: "top" },
                    h(
                      "span",
                      {
                        style: {
                          display: "inline-block",
                          cursor: nextDisabled ? "not-allowed" : "pointer",
                        },
                        onClick: () => {
                          if (nextDisabled) {
                            // Blocked-next telemetry (reason code)
                            let reason = "blocked";
                            const sk = tlmStepKey();

                            if (sk === "select_type") reason = "missing_test_type";
                            else if (sk === "select_custom_code_type") reason = "missing_custom_code_type";
                            else if (sk === "select_control") reason = "missing_control";
                            else if (sk === "version_b_source" && bMode === "existing" && !pageB) reason = "missing_version_b";
                            else if (sk === "review_versions" && isCustomHtml && !hasCompleteCustomHtmlChange) reason = "missing_custom_html";
                            else if (sk === "review_versions" && isCustomCss && String(customCss || "").trim() === "") reason = "missing_custom_css";
                            else if (sk === "review_versions" && !isCustomCode && !hasEditedB) reason = "edit_version_b_required";
                            else if (sk === "choose_conversion_type" && !conversionChosen) reason = "missing_goal";
                            else if (sk === "select_click_targets" && goal === "clicks" && tlmLinksCount() < 1) reason = "missing_click_targets";
                            else if (sk === "set_destination_url" && goal === "destination_url" && tlmLinksCount() < 1) reason = "missing_destination_url";

                            tlmSend("pt_wizard_blocked", {
                              step: sk,
                              error_code: reason,
                            });

                            return;
                          }

                          tlmNavDirRef.current = "next";
                          setStep(step + 1);

                          setTimeout(() => {
                            window.scrollTo({ top: 0, behavior: "auto" });
                          }, 0);
                        },
                      },
                      h(
                        Button,
                        {
                          isPrimary: true,
                          disabled: nextDisabled,
                          style: nextDisabled ? { pointerEvents: "none" } : undefined, // wrapper handles click tracking
                          title: (nextDisabled && nextTitle) ? nextTitle : undefined,
                          "aria-disabled": nextDisabled ? "true" : undefined,
                        },
                        "Next"
                      )
                    )
                  ),
            ]),
          ])),
          h(TipsPanel, { postType, step }),
        ]),
      ]
    );
  }

  wp.element.render(h(Wizard), document.getElementById("abtestkit-pt-wizard-root"));
})(window.wp);
