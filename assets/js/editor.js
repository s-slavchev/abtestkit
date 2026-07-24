// assets/js/editor.js
(function (wp) {
  const { createHigherOrderComponent } = wp.compose;
  const { select } = wp.data;
  const { useState, useEffect } = wp.element;

  /** 1) Extend core/button and acf/bv-panel to add all the A/B attributes needed */
  function addAbAttributes(settings, name) {
    const supportedBlocks = [
      "core/button",
      "acf/bv-panel",
      "core/heading",
      "core/paragraph",
      "core/image",
    ];
    if (!supportedBlocks.includes(name)) return settings;

    settings.attributes = {
      ...settings.attributes,
      abTestEnabled: { type: "boolean", default: false },
      abTestVariants: { type: "object", default: {} },
      abTestId: { type: "string", default: "" },
      abTestRunning: { type: "boolean", default: false },
      abTestWinner: { type: "string", default: "" },
      abTestResultsViewed: { type: "boolean", default: false },
      conversionFrom: { type: "array", default: [] },
      abTestLastUnlocked: { type: "number", default: 0 },
      abTestStartedAt: { type: "number", default: 0 },
    };
    return settings;
  }

  wp.hooks.addFilter(
    "blocks.registerBlockType",
    "abtest/extend-attributes",
    addAbAttributes,
  );

  // One-time CSS injector (works for parent doc AND iframe docs)
  function ensureAbLockCSS(targetDoc) {
    const doc = targetDoc || document;
    if (doc.getElementById("abtestkit-inline-lock-css")) return;
    const style = doc.createElement("style");
    style.id = "abtestkit-inline-lock-css";
    style.textContent = `
    /* When the block element has .abtest-locked-text, inner editables are visually read-only */
    .abtest-locked-text .block-editor-rich-text__editable,
    .abtest-locked-text[contenteditable],
    .abtest-locked-text .wp-block-button__link,
    .abtest-locked-text .block-editor-plain-text {
      user-select: none !important;
      -webkit-user-select: none !important;
      caret-color: transparent !important;
      cursor: default !important; /* I-beam -> arrow */
    }
  `;
    (doc.head || doc.documentElement).appendChild(style);
  }
  // Inject into parent now (iframe will be handled in the effect)
  ensureAbLockCSS(document);

  const withInlineLock = createHigherOrderComponent((BlockEdit) => {
    return (props) => {
      // Only handle blocks we intend to lock
      if (
        ![
          "core/button",
          "core/heading",
          "core/paragraph",
          "core/image",
        ].includes(props.name)
      ) {
        return wp.element.createElement(BlockEdit, props);
      }

      const { attributes: attrs, setAttributes: setAttrs, clientId } = props;
      const postId = wp.data.select("core/editor").getCurrentPostId();
      const { useState, useEffect } = wp.element;
      const [checkedStorage, setCheckedStorage] = useState(false);

      // Ensure localStorage-driven state only for THIS block
      if (!checkedStorage) {
        try {
          const key = `abTestRunning_${postId}_${clientId}`;
          if (
            window.localStorage.getItem(key) === "true" &&
            !attrs.abTestRunning
          ) {
            setAttrs({ abTestRunning: true });
          }
        } catch (_) {}
        setCheckedStorage(true);
      }

      useEffect(() => {
        if (!attrs.abTestRunning) return;
        try {
          const key = `abTestRunning_${postId}_${clientId}`;
          window.localStorage.setItem(key, "true");
        } catch (_) {}
      }, [attrs.abTestRunning]);

      // ---- Core locking logic (scoped to THIS block only) ----
      useEffect(() => {
        if (!clientId) return;

        const locked = attrs.abTestRunning || !!attrs.abTestWinner;

        // Find THIS block element, even if it's inside an iframe
        const SEL_BLOCK = `.block-editor-block-list__block[data-block="${clientId}"]`;
        const candidateDocs = [
          document,
          ...Array.from(document.querySelectorAll("iframe"))
            .map((f) => {
              try {
                return f.contentDocument || f.contentWindow?.document;
              } catch (_) {
                return null;
              }
            })
            .filter(Boolean),
        ];

        let blockEl = null,
          doc = document;
        for (const d of candidateDocs) {
          const el = d.querySelector(SEL_BLOCK);
          if (el) {
            blockEl = el;
            doc = d;
            break;
          }
        }
        if (!blockEl) return;

        // Make sure lock CSS exists in the right document
        ensureAbLockCSS(doc);

        // Visual hint (affects only descendants of this block)
        if (locked) blockEl.classList.add("abtest-locked-text");
        else blockEl.classList.remove("abtest-locked-text");

        // Only target true editables inside THIS block
        const EDITABLE_SEL =
          '.block-editor-rich-text__editable, [contenteditable="true"], .wp-block-button__link';
        const editables = [];
        try {
          editables.push(...blockEl.querySelectorAll(EDITABLE_SEL));
          if (blockEl.matches && blockEl.matches('[contenteditable="true"]')) {
            editables.unshift(blockEl);
          }
        } catch (_) {}

        // Soft clamp: set contenteditable=false, preserve original state for restore
        const clamp = () => {
          if (!locked) return;
          editables.forEach((n) => {
            try {
              // If it's already clamped, do nothing (prevents needless attribute writes)
              if (
                n.getAttribute("contenteditable") === "false" &&
                n.getAttribute("aria-disabled") === "true"
              ) {
                return;
              }
              if (!n.hasAttribute("data-abtest-original-ce")) {
                n.setAttribute(
                  "data-abtest-original-ce",
                  n.getAttribute("contenteditable") ?? "",
                );
              }
              n.setAttribute("contenteditable", "false");
              n.contentEditable = "false";
              n.setAttribute("aria-disabled", "true");
              n.style.caretColor = "transparent";
            } catch (_) {}
          });
        };

        const unclamp = () => {
          editables.forEach((n) => {
            try {
              const orig = n.getAttribute("data-abtest-original-ce");
              if (orig === "") {
                n.removeAttribute("contenteditable");
              } else if (orig !== null) {
                n.setAttribute("contenteditable", orig);
              }
              n.removeAttribute("data-abtest-original-ce");
              n.removeAttribute("aria-disabled");
              n.style.caretColor = "";
            } catch (_) {}
          });
        };

        // Apply now
        if (locked) clamp();
        else unclamp();

        // Re-assert if the block DOM mutates
        let mo;
        if (locked) {
          mo = new MutationObserver(() => clamp());
          // Do NOT observe attributes to avoid re-triggering on our own contenteditable/style changes
          try {
            mo.observe(blockEl, { subtree: true, childList: true });
          } catch (_) {}
        }

        return () => {
          if (mo) mo.disconnect();
          blockEl.classList.remove("abtest-locked-text");
          if (!locked) unclamp();
        };
      }, [attrs.abTestRunning, attrs.abTestWinner, clientId]);

      const locked = attrs.abTestRunning || !!attrs.abTestWinner;

      // Wrapper ONLY around THIS block’s editor output; capture events ONLY inside its own RichText fields
      return wp.element.createElement(
        "div",
        {
          style: {
            position: "relative",
            cursor: locked ? "default" : undefined,
          },
          onMouseDownCapture: (e) => {
            if (!locked) return;
            const t = e.target;
            // Only stop if the click is inside a RichText field within THIS wrapper
            if (
              t &&
              t.closest &&
              t.closest(".block-editor-rich-text__editable")
            ) {
              e.preventDefault();
              e.stopPropagation();
              try {
                (
                  wp.data.dispatch("core/block-editor") ||
                  wp.data.dispatch("core/editor")
                ).selectBlock(clientId);
              } catch (_) {}
            }
          },
          onFocusCapture: (e) => {
            if (!locked) return;
            const t = e.target;
            if (
              t &&
              t.closest &&
              t.closest(".block-editor-rich-text__editable")
            ) {
              e.preventDefault();
              e.stopPropagation();
              try {
                t.blur();
              } catch (_) {}
              try {
                (
                  wp.data.dispatch("core/block-editor") ||
                  wp.data.dispatch("core/editor")
                ).selectBlock(clientId);
              } catch (_) {}
            }
          },
          onBeforeInputCapture: (e) => {
            if (!locked) return;
            const t = e.target;
            if (
              t &&
              t.closest &&
              t.closest(".block-editor-rich-text__editable")
            ) {
              e.preventDefault();
              e.stopPropagation();
            }
          },
          onKeyDownCapture: (e) => {
            if (!locked) return;
            const ae = document.activeElement;
            if (
              ae &&
              ae.closest &&
              ae.closest(".block-editor-rich-text__editable")
            ) {
              e.preventDefault();
              e.stopPropagation();
            }
          },
          onPasteCapture: (e) => {
            if (!locked) return;
            const t = e.target;
            if (
              t &&
              t.closest &&
              t.closest(".block-editor-rich-text__editable")
            ) {
              e.preventDefault();
              e.stopPropagation();
            }
          },
          onDropCapture: (e) => {
            if (!locked) return;
            const t = e.target;
            if (
              t &&
              t.closest &&
              t.closest(".block-editor-rich-text__editable")
            ) {
              e.preventDefault();
              e.stopPropagation();
            }
          },
        },
        [
          wp.element.createElement(BlockEdit, props),
          locked &&
            wp.element.createElement(
              "div",
              {
                style: {
                  position: "absolute",
                  inset: 0,
                  background: "rgba(255,255,255,0.6)",
                  color: "#333",
                  fontSize: "14px",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  pointerEvents: "none",
                  zIndex: 2,
                  textAlign: "center",
                },
              },
              "🔒 A/B Test Running\nBlock Locked",
            ),
        ],
      );
    };
  }, "withInlineLock");

  const assignAbTestIdGlobally = createHigherOrderComponent((BlockEdit) => {
    return (props) => {
      const supportedBlocks = [
        "core/button",
        "core/heading",
        "core/paragraph",
        "core/image",
        "acf/bv-panel",
      ];
      const { name, attributes, setAttributes, clientId } = props;

      useEffect(() => {
        if (
          supportedBlocks.includes(name) &&
          clientId &&
          (!attributes.abTestId || attributes.abTestId.startsWith("auto-"))
        ) {
          const newId = "ab-" + Math.random().toString(36).slice(2, 11);
          setAttributes({ abTestId: newId });
          console.log(`🆔 Assigned abTestId to ${name}: ${newId}`);
        }
      }, [clientId]);

      return wp.element.createElement(BlockEdit, props);
    };
  }, "assignAbTestIdGlobally");

  wp.hooks.addFilter("editor.BlockEdit", "abtest/inline-lock", withInlineLock);

  /** 3) Variant syncing for acf/bv-panel inspector */
  const withVariantSidebarSync = createHigherOrderComponent((BlockEdit) => {
    return (props) => {
      if (props.name !== "acf/bv-panel") {
        return wp.element.createElement(BlockEdit, props);
      }

      const { attributes, setAttributes } = props;
      const abTestEnabled = attributes.abTestEnabled;
      const abTestVariants = attributes.abTestVariants || {};
      const abTestId = attributes.abTestId || "";
      const abTestRunning = attributes.abTestRunning;
      const abTestWinner = attributes.abTestWinner;

      const [previewVariant, setPreviewVariant] = useState("A");

      // Listen for variant preview switch events
      useEffect(() => {
        function handlePreviewChange(e) {
          if (e.detail && e.detail.abTestId === abTestId) {
            setPreviewVariant(e.detail.preview);
            const variantData =
              abTestVariants[abTestId]?.[e.detail.preview] || {};
            const fields = acf.getFields();
            ["myTitle", "myContent", "myButtonText", "myButtonURL"].forEach(
              (fieldName) => {
                if (variantData[fieldName] !== undefined) {
                  const field = fields.find((f) => f.get("name") === fieldName);
                  if (field) field.val(variantData[fieldName]);
                }
              },
            );
          }
        }
        window.addEventListener("abTestVariantChange", handlePreviewChange);
        return () =>
          window.removeEventListener(
            "abTestVariantChange",
            handlePreviewChange,
          );
      }, [abTestId]);

      // Initial sync when component mounts or preview variant changes
      useEffect(() => {
        const variantData = abTestVariants[abTestId]?.[previewVariant] || {};
        const fields = acf.getFields();
        ["myTitle", "myContent", "myButtonText", "myButtonURL"].forEach(
          (fieldName) => {
            if (variantData[fieldName] !== undefined) {
              const field = fields.find((f) => f.get("name") === fieldName);
              if (field) field.val(variantData[fieldName]);
            }
          },
        );
      }, [previewVariant, abTestVariants, abTestId]);

      const newAttributes = { ...attributes };
      if (abTestEnabled && abTestId && abTestVariants[abTestId]) {
        const variantData = abTestVariants[abTestId][previewVariant] || {};
        ["myTitle", "myContent", "myButtonText", "myButtonURL"].forEach(
          (fieldName) => {
            if (variantData[fieldName] !== undefined)
              newAttributes[fieldName] = variantData[fieldName];
          },
        );
      }

      const wrappedSetAttributes = (patch) => {
        const variantPatch = { ...abTestVariants };
        if (!variantPatch[abTestId]) variantPatch[abTestId] = { A: {}, B: {} };
        Object.keys(patch).forEach((key) => {
          if (
            ["myTitle", "myContent", "myButtonText", "myButtonURL"].includes(
              key,
            )
          ) {
            variantPatch[abTestId][previewVariant][key] = patch[key];
            delete patch[key];
          }
        });
        setAttributes({ ...patch, abTestVariants: variantPatch });
      };

      const locked = abTestRunning || !!abTestWinner;
      const effectiveSetAttributes = locked ? () => {} : wrappedSetAttributes;

      return wp.element.createElement(BlockEdit, {
        key: previewVariant,
        ...props,
        attributes: newAttributes,
        setAttributes: effectiveSetAttributes,
      });
    };
  }, "withVariantSidebarSync");

  /** 4) Disable native ACF inspector completely when A/B Test is enabled */
  const withInspectorDisable = createHigherOrderComponent((BlockEdit) => {
    return (props) => {
      if (props.name !== "acf/bv-panel") {
        return wp.element.createElement(BlockEdit, props);
      }
      const { attributes } = props;
      if (!attributes.abTestEnabled) {
        return wp.element.createElement(BlockEdit, props);
      }
      // A/B Test is enabled — disable native inspector
      return wp.element.createElement(
        wp.element.Fragment,
        null,
        wp.element.createElement(BlockEdit, {
          ...props,
          InspectorControls: () => null,
        }),
      );
    };
  }, "withInspectorDisable");
  wp.hooks.addFilter(
    "editor.BlockEdit",
    "abtest/assign-ab-id",
    assignAbTestIdGlobally,
  );
  wp.hooks.addFilter(
    "editor.BlockEdit",
    "abtest/inspector-disable",
    withInspectorDisable,
  );
})(window.wp);
