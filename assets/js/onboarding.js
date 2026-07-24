/* global ABTESTKIT_ONBOARDING, wp */
(function () {
  const { createElement: h, useState } = wp.element;
  const { Button, Card, CardBody, Notice, Spinner, RadioControl } = wp.components;
  const apiFetch = wp.apiFetch;

  // Wire the REST nonce
  apiFetch.use(apiFetch.createNonceMiddleware(ABTESTKIT_ONBOARDING.nonce));

  // Small helper to get an asset URL safely
  function getAsset(key) {
    if (
      ABTESTKIT_ONBOARDING.assets &&
      Object.prototype.hasOwnProperty.call(ABTESTKIT_ONBOARDING.assets, key)
    ) {
      return ABTESTKIT_ONBOARDING.assets[key];
    }
    return null;
  }

  function Step1CreateTest({ next }) {
    return h(
      Card,
      { className: "abtestkit-card" },
      h(
        CardBody,
        null,

        h(
          "div",
          {
            style: {
              maxWidth: 760,
            },
          },
          h(
            "div",
            {
              style: {
                fontSize: 12,
                fontWeight: 700,
                letterSpacing: "0.04em",
                textTransform: "uppercase",
                opacity: 0.65,
                marginBottom: 8,
              },
            },
            "Start here",
          ),
          h(
            "h2",
            {
              style: {
                marginTop: 0,
                marginBottom: 10,
                fontSize: 28,
                lineHeight: 1.2,
              },
            },
            "Understand Version B before you launch your first test",
          ),
          h(
            "p",
            {
              style: {
                marginTop: 0,
                marginBottom: 28,
                lineHeight: 1.6,
                fontSize: 16,
              },
            },
            "abtestkit works by showing some visitors Version A and some visitors Version B, then measuring which version performs better.",
          ),
        ),

        h(
          "div",
          {
            style: {
              display: "grid",
              gap: 14,
              maxWidth: 820,
            },
          },

          h(
            "div",
            {
              style: {
                display: "grid",
                gridTemplateColumns: "44px minmax(0, 1fr)",
                gap: 14,
                alignItems: "start",
                padding: "16px 0",
                borderTop: "1px solid rgba(0,0,0,0.08)",
              },
            },
            h(
              "div",
              {
                style: {
                  width: 34,
                  height: 34,
                  borderRadius: 999,
                  display: "grid",
                  placeItems: "center",
                  background: "#f0f4ff",
                  color: "#3858e9",
                  fontWeight: 700,
                },
              },
              "1",
            ),
            h(
              "div",
              null,
              h("h3", { style: { margin: "0 0 4px 0" } }, "Create a test version"),
              h(
                "p",
                { style: { margin: 0, lineHeight: 1.55 } },
                "Create a Version B for a page, post, WooCommerce product, or reusable section.",
              ),
            ),
          ),

          h(
            "div",
            {
              style: {
                display: "grid",
                gridTemplateColumns: "44px minmax(0, 1fr)",
                gap: 14,
                alignItems: "start",
                padding: "16px 0",
                borderTop: "1px solid rgba(0,0,0,0.08)",
              },
            },
            h(
              "div",
              {
                style: {
                  width: 34,
                  height: 34,
                  borderRadius: 999,
                  display: "grid",
                  placeItems: "center",
                  background: "#f0f4ff",
                  color: "#3858e9",
                  fontWeight: 700,
                },
              },
              "2",
            ),
            h(
              "div",
              null,
              h("h3", { style: { margin: "0 0 4px 0" } }, "Show visitors A or B"),
              h(
                "p",
                { style: { margin: 0, lineHeight: 1.55 } },
                "Each visitor is assigned to one version and kept on that version while the test runs.",
              ),
            ),
          ),

          h(
            "div",
            {
              style: {
                display: "grid",
                gridTemplateColumns: "44px minmax(0, 1fr)",
                gap: 14,
                alignItems: "start",
                padding: "16px 0",
                borderTop: "1px solid rgba(0,0,0,0.08)",
                borderBottom: "1px solid rgba(0,0,0,0.08)",
              },
            },
            h(
              "div",
              {
                style: {
                  width: 34,
                  height: 34,
                  borderRadius: 999,
                  display: "grid",
                  placeItems: "center",
                  background: "#f0f4ff",
                  color: "#3858e9",
                  fontWeight: 700,
                },
              },
              "3",
            ),
            h(
              "div",
              null,
              h("h3", { style: { margin: "0 0 4px 0" } }, "Measure the outcome"),
              h(
                "p",
                { style: { margin: 0, lineHeight: 1.55 } },
                "Track clicks, forms, add-to-carts, or revenue so you can compare performance.",
              ),
            ),
          ),
        ),

        h(
          "div",
          {
            style: {
              marginTop: 24,
              padding: 18,
              border: "1px solid rgba(56,88,233,0.18)",
              borderRadius: 12,
              background: "#f8fafc",
              maxWidth: 820,
            },
          },
          h(
            "h3",
            { style: { margin: "0 0 8px 0" } },
            "What a shadow product does",
          ),
          h(
            "p",
            { style: { margin: 0, lineHeight: 1.55 } },
            "For WooCommerce product tests, Version B is created as a shadow product. It can change the visitor-facing product experience while SKU, stock, and inventory stay handled by the original product.",
          ),
        ),

        h(
          "div",
          {
            style: {
              marginTop: 28,
              maxWidth: 820,
              display: "flex",
              justifyContent: "flex-end",
            },
          },
          h(
            Button,
            { variant: "primary", onClick: next },
            "Next: What shadow versions do",
          ),
        ),
      ),
    );
  }

  function Step2ShadowVersions({ next, back }) {
    const iconStyle = {
      width: 42,
      height: 42,
      borderRadius: 12,
      display: "grid",
      placeItems: "center",
      background: "#f0f4ff",
      color: "#3858e9",
      flexShrink: 0,
    };

    const iconClassStyle = {
      fontSize: 22,
      width: 22,
      height: 22,
    };

    return h(
      Card,
      { className: "abtestkit-card" },
      h(
        CardBody,
        null,

        h(
          "div",
          {
            style: {
              maxWidth: 760,
            },
          },
          h(
            "div",
            {
              style: {
                fontSize: 12,
                fontWeight: 700,
                letterSpacing: "0.04em",
                textTransform: "uppercase",
                opacity: 0.65,
                marginBottom: 8,
              },
            },
            "Shadow versions",
          ),
          h(
            "h2",
            {
              style: {
                marginTop: 0,
                marginBottom: 10,
                fontSize: 28,
                lineHeight: 1.2,
              },
            },
            "What shadow versions do",
          ),
          h(
            "p",
            {
              style: {
                marginTop: 0,
                marginBottom: 28,
                lineHeight: 1.6,
                fontSize: 16,
              },
            },
            "A shadow version is a draft test version. It lets abtestkit show Version B to assigned visitors without publishing a second live page, post, product, or section.",
          ),
        ),

        h(
          "div",
          {
            style: {
              display: "grid",
              gap: 12,
              maxWidth: 820,
            },
          },

          h(
            "div",
            {
              style: {
                display: "flex",
                gap: 16,
                alignItems: "flex-start",
                padding: 18,
                border: "1px solid rgba(56,88,233,0.18)",
                borderRadius: 12,
                background: "#f8fafc",
              },
            },
            h(
              "div",
              { style: iconStyle },
              h("span", {
                className: "dashicons dashicons-cart",
                style: iconClassStyle,
              }),
            ),
            h(
              "div",
              null,
              h("h3", { style: { margin: "0 0 6px 0" } }, "WooCommerce products"),
              h(
                "p",
                { style: { margin: 0, lineHeight: 1.55 } },
                "Product tests always use a shadow Version B. It can change the visitor-facing product experience while the original product continues handling SKU, stock, and inventory.",
              ),
            ),
          ),

          h(
            "div",
            {
              style: {
                display: "flex",
                gap: 16,
                alignItems: "flex-start",
                padding: 18,
                border: "1px solid rgba(0,0,0,0.08)",
                borderRadius: 12,
                background: "#fff",
              },
            },
            h(
              "div",
              { style: iconStyle },
              h("span", {
                className: "dashicons dashicons-admin-page",
                style: iconClassStyle,
              }),
            ),
            h(
              "div",
              null,
              h("h3", { style: { margin: "0 0 6px 0" } }, "Pages and posts"),
              h(
                "p",
                { style: { margin: 0, lineHeight: 1.55 } },
                "For pages and posts, you can create a new shadow Version B or choose an existing page/post to test against Version A.",
              ),
            ),
          ),

          h(
            "div",
            {
              style: {
                display: "flex",
                gap: 16,
                alignItems: "flex-start",
                padding: 18,
                border: "1px solid rgba(0,0,0,0.08)",
                borderRadius: 12,
                background: "#fff",
              },
            },
            h(
              "div",
              { style: iconStyle },
              h("span", {
                className: "dashicons dashicons-screenoptions",
                style: iconClassStyle,
              }),
            ),
            h(
              "div",
              null,
              h("h3", { style: { margin: "0 0 6px 0" } }, "Reusable sections"),
              h(
                "p",
                { style: { margin: 0, lineHeight: 1.55 } },
                "For reusable sections, Version B is shown only where the test is running, so the rest of your site stays unchanged.",
              ),
            ),
          ),
        ),

        h(
          "div",
          {
            style: {
              marginTop: 24,
              display: "grid",
              gap: 12,
              maxWidth: 820,
            },
          },
          h(
            "div",
            {
              style: {
                padding: 18,
                border: "1px solid rgba(0,0,0,0.08)",
                borderRadius: 12,
                background: "#fff",
              },
            },
            h("h3", { style: { margin: "0 0 8px 0" } }, "Hidden by default"),
            h(
              "p",
              { style: { margin: 0, lineHeight: 1.55 } },
              "Shadow versions stay as drafts, so they do not appear in normal menus, product catalogues, search results, or public browsing unless a visitor is assigned to that test.",
            ),
          ),
        ),

        h(
          "div",
          {
            style: {
              marginTop: 28,
              maxWidth: 820,
              display: "flex",
              justifyContent: "space-between",
              gap: 8,
            },
          },
          h(Button, { variant: "secondary", onClick: back }, "Back"),
          h(
            Button,
            { variant: "primary", onClick: next },
            "Next: How results stay clean",
          ),
        ),
      ),
    );
  }

  function Step3ResultsClean({ next, back }) {
    return h(
      Card,
      { className: "abtestkit-card" },
      h(
        CardBody,
        null,

        h(
          "div",
          {
            style: {
              maxWidth: 760,
            },
          },
          h(
            "div",
            {
              style: {
                fontSize: 12,
                fontWeight: 700,
                letterSpacing: "0.04em",
                textTransform: "uppercase",
                opacity: 0.65,
                marginBottom: 8,
              },
            },
            "Clean results",
          ),
          h(
            "h2",
            {
              style: {
                marginTop: 0,
                marginBottom: 10,
                fontSize: 28,
                lineHeight: 1.2,
              },
            },
            "How results stay clean",
          ),
          h(
            "p",
            {
              style: {
                marginTop: 0,
                marginBottom: 28,
                lineHeight: 1.6,
                fontSize: 16,
              },
            },
            "abtestkit keeps testing and tracking as clean as possible before it recommends or declares a winner.",
          ),
        ),

        h(
          "div",
          {
            style: {
              display: "grid",
              gap: 14,
              maxWidth: 820,
            },
          },

          h(
            "div",
            {
              style: {
                display: "grid",
                gridTemplateColumns: "44px minmax(0, 1fr)",
                gap: 14,
                alignItems: "start",
                padding: "16px 0",
                borderTop: "1px solid rgba(0,0,0,0.08)",
              },
            },
            h(
              "div",
              {
                style: {
                  width: 34,
                  height: 34,
                  borderRadius: 999,
                  display: "grid",
                  placeItems: "center",
                  background: "#f0f4ff",
                  color: "#3858e9",
                  fontWeight: 700,
                },
              },
              "1",
            ),
            h(
              "div",
              null,
              h("h3", { style: { margin: "0 0 4px 0" } }, "Random split"),
              h(
                "p",
                { style: { margin: 0, lineHeight: 1.55 } },
                "Visitors are assigned Version A or B and kept consistent using a cookie.",
              ),
            ),
          ),

          h(
            "div",
            {
              style: {
                display: "grid",
                gridTemplateColumns: "44px minmax(0, 1fr)",
                gap: 14,
                alignItems: "start",
                padding: "16px 0",
                borderTop: "1px solid rgba(0,0,0,0.08)",
              },
            },
            h(
              "div",
              {
                style: {
                  width: 34,
                  height: 34,
                  borderRadius: 999,
                  display: "grid",
                  placeItems: "center",
                  background: "#f0f4ff",
                  color: "#3858e9",
                  fontWeight: 700,
                },
              },
              "2",
            ),
            h(
              "div",
              null,
              h("h3", { style: { margin: "0 0 4px 0" } }, "Cleaner impressions"),
              h(
                "p",
                { style: { margin: 0, lineHeight: 1.55 } },
                "Logged-in admins, known bots, and plain HTTP visits are ignored so admin previews, bot traffic, and cache-related edge cases do not skew results.",
              ),
            ),
          ),

          h(
            "div",
            {
              style: {
                display: "grid",
                gridTemplateColumns: "44px minmax(0, 1fr)",
                gap: 14,
                alignItems: "start",
                padding: "16px 0",
                borderTop: "1px solid rgba(0,0,0,0.08)",
              },
            },
            h(
              "div",
              {
                style: {
                  width: 34,
                  height: 34,
                  borderRadius: 999,
                  display: "grid",
                  placeItems: "center",
                  background: "#f0f4ff",
                  color: "#3858e9",
                  fontWeight: 700,
                },
              },
              "3",
            ),
            h(
              "div",
              null,
              h("h3", { style: { margin: "0 0 4px 0" } }, "Session de-duplication"),
              h(
                "p",
                { style: { margin: 0, lineHeight: 1.55 } },
                "Only one impression/conversion is recorded on each test per visitor per session to reduce spammy inflation.",
              ),
            ),
          ),

          h(
            "div",
            {
              style: {
                display: "grid",
                gridTemplateColumns: "44px minmax(0, 1fr)",
                gap: 14,
                alignItems: "start",
                padding: "16px 0",
                borderTop: "1px solid rgba(0,0,0,0.08)",
                borderBottom: "1px solid rgba(0,0,0,0.08)",
              },
            },
            h(
              "div",
              {
                style: {
                  width: 34,
                  height: 34,
                  borderRadius: 999,
                  display: "grid",
                  placeItems: "center",
                  background: "#f0f4ff",
                  color: "#3858e9",
                  fontWeight: 700,
                },
              },
              "4",
            ),
            h(
              "div",
              null,
              h("h3", { style: { margin: "0 0 4px 0" } }, "Minimum data"),
              h(
                "p",
                { style: { margin: 0, lineHeight: 1.55 } },
                "A winner will not be declared until the selected impressions and conversions minimums are met.",
              ),
            ),
          ),
        ),

        h(
          "div",
          {
            style: {
              marginTop: 24,
              padding: 18,
              border: "1px solid rgba(214,54,56,0.2)",
              borderRadius: 12,
              background: "#fff8f8",
              maxWidth: 820,
            },
          },
          h("h3", { style: { margin: "0 0 8px 0" } }, "Use HTTPS for live tests"),
          h(
            "p",
            { style: { margin: 0, lineHeight: 1.55 } },
            "Plain HTTP traffic is skipped for testing because cached HTTP responses can make variation assignment unreliable.",
          ),
        ),

        h(
          "div",
          {
            style: {
              marginTop: 28,
              maxWidth: 820,
              display: "flex",
              justifyContent: "space-between",
              gap: 8,
            },
          },
          h(Button, { variant: "secondary", onClick: back }, "Back"),
          h(Button, { variant: "primary", onClick: next }, "Next: You’re ready"),
        ),
      ),
    );
  }

  function Step4Ready({ back, finish, telemetryChoice, setTelemetryChoice }) {
    const canFinish = telemetryChoice === "yes" || telemetryChoice === "no";

    return h(
      Card,
      { className: "abtestkit-card" },
      h(
        CardBody,
        null,

        h(
          "div",
          {
            style: {
              maxWidth: 760,
            },
          },
          h(
            "div",
            {
              style: {
                fontSize: 12,
                fontWeight: 700,
                letterSpacing: "0.04em",
                textTransform: "uppercase",
                opacity: 0.65,
                marginBottom: 8,
              },
            },
            "Start testing",
          ),
          h(
            "h2",
            {
              style: {
                marginTop: 0,
                marginBottom: 10,
                fontSize: 28,
                lineHeight: 1.2,
              },
            },
            "You’re ready",
          ),
          h(
            "p",
            {
              style: {
                marginTop: 0,
                marginBottom: 28,
                lineHeight: 1.6,
                fontSize: 16,
              },
            },
            "The fastest way to learn abtestkit is to run your first test. Start with a simple change, then let real visitor behaviour guide the next decision.",
          ),
        ),

        h(
          "div",
          {
            style: {
              display: "grid",
              gap: 14,
              maxWidth: 820,
            },
          },

          h(
            "div",
            {
              style: {
                display: "grid",
                gridTemplateColumns: "44px minmax(0, 1fr)",
                gap: 14,
                alignItems: "start",
                padding: "16px 0",
                borderTop: "1px solid rgba(0,0,0,0.08)",
              },
            },
            h(
              "div",
              {
                style: {
                  width: 34,
                  height: 34,
                  borderRadius: 999,
                  display: "grid",
                  placeItems: "center",
                  background: "#f0f4ff",
                  color: "#3858e9",
                  fontWeight: 700,
                },
              },
              "1",
            ),
            h(
              "div",
              null,
              h("h3", { style: { margin: "0 0 4px 0" } }, "Start with one clear change"),
              h(
                "p",
                { style: { margin: 0, lineHeight: 1.55 } },
                "Try a headline, CTA, product image, button position, reviews placement, or add-to-cart section.",
              ),
            ),
          ),

          h(
            "div",
            {
              style: {
                display: "grid",
                gridTemplateColumns: "44px minmax(0, 1fr)",
                gap: 14,
                alignItems: "start",
                padding: "16px 0",
                borderTop: "1px solid rgba(0,0,0,0.08)",
              },
            },
            h(
              "div",
              {
                style: {
                  width: 34,
                  height: 34,
                  borderRadius: 999,
                  display: "grid",
                  placeItems: "center",
                  background: "#f0f4ff",
                  color: "#3858e9",
                  fontWeight: 700,
                },
              },
              "2",
            ),
            h(
              "div",
              null,
              h("h3", { style: { margin: "0 0 4px 0" } }, "Let it collect data"),
              h(
                "p",
                { style: { margin: 0, lineHeight: 1.55 } },
                "Let the test collect enough impressions and conversions before calling a winner.",
              ),
            ),
          ),

          h(
            "div",
            {
              style: {
                display: "grid",
                gridTemplateColumns: "44px minmax(0, 1fr)",
                gap: 14,
                alignItems: "start",
                padding: "16px 0",
                borderTop: "1px solid rgba(0,0,0,0.08)",
                borderBottom: "1px solid rgba(0,0,0,0.08)",
              },
            },
            h(
              "div",
              {
                style: {
                  width: 34,
                  height: 34,
                  borderRadius: 999,
                  display: "grid",
                  placeItems: "center",
                  background: "#f0f4ff",
                  color: "#3858e9",
                  fontWeight: 700,
                },
              },
              "3",
            ),
            h(
              "div",
              null,
              h("h3", { style: { margin: "0 0 4px 0" } }, "Run the next test"),
              h(
                "p",
                { style: { margin: 0, lineHeight: 1.55 } },
                "Ship the winner, then use what you learned to choose the next test.",
              ),
            ),
          ),
        ),

        h(
          "div",
          {
            style: {
              marginTop: 24,
              padding: 18,
              border: "1px solid rgba(56,88,233,0.18)",
              borderRadius: 12,
              background: "#f8fafc",
              maxWidth: 820,
            },
          },
          h("h3", { style: { margin: "0 0 8px 0" } }, "Help improve compatibility"),
          h(
            "p",
            { style: { margin: "0 0 14px 0", lineHeight: 1.55 } },
            "Share anonymous usage signals to help improve abtestkit across different themes, builders, caches, and WooCommerce setups.",
          ),
          h(RadioControl, {
            label: "Anonymous telemetry",
            selected: telemetryChoice || "",
            options: [
              { label: "Yes - I'm happy to help", value: "yes" },
              { label: "No.", value: "no" },
            ],
            onChange: (v) => setTelemetryChoice(v),
          }),
        ),

        h(
          "div",
          {
            style: {
              marginTop: 28,
              maxWidth: 820,
              display: "flex",
              justifyContent: "space-between",
              gap: 8,
            },
          },
          h(Button, { variant: "secondary", onClick: back }, "Back"),
          h(
            Button,
            { variant: "primary", onClick: finish, disabled: !canFinish },
            "Create first test +",
          ),
        ),
      ),
    );
  }

  function WizardApp() {
    const [step, setStep] = useState(0);
    const [busy, setBusy] = useState(false);

    const initialTelemetryChoice =
      ABTESTKIT_ONBOARDING &&
      typeof ABTESTKIT_ONBOARDING.telemetryOptedIn === "boolean"
        ? (ABTESTKIT_ONBOARDING.telemetryOptedIn ? "yes" : "no")
        : null;

    const [telemetryChoice, setTelemetryChoice] = useState(initialTelemetryChoice);

    const finish = async () => {
      try {
        setBusy(true);
        await apiFetch({
          path: "/abtestkit/v1/onboarding",
          method: "POST",
          data: {
            done: true,
            telemetry: telemetryChoice === "yes",
          },
        });
      } catch (e) {
        // Non-fatal, just continue
      } finally {
        setBusy(false);
        window.location =
          (ABTESTKIT_ONBOARDING.links && ABTESTKIT_ONBOARDING.links.createUrl)
            ? ABTESTKIT_ONBOARDING.links.createUrl
            : ((window.abtestkitDashboard && abtestkitDashboard.createUrl)
                ? abtestkitDashboard.createUrl
                : ABTESTKIT_ONBOARDING.links.plugins);
      }
    };

    const titles = [
      "How tests work",
      "Shadow versions",
      "Clean results",
      "Start testing",
    ];

    return h(
      "div",
      {
        className: "abtestkit-onboarding-page",
        style: {
          position: "relative",
          maxWidth: 900,
          margin: "32px auto",
        },
      },
      busy &&
        h(
          "div",
          {
            style: {
              position: "absolute",
              inset: 0,
              display: "grid",
              placeItems: "center",
              background: "rgba(255,255,255,0.6)",
              zIndex: 10,
              borderRadius: 12,
            },
          },
          h(Spinner),
        ),
      h(
        "div",
        {
          style: {
            display: "flex",
            justifyContent: "space-between",
            alignItems: "baseline",
            marginBottom: 16,
          },
        },
        h("h1", { style: { margin: 0 } }, "Welcome to abtestkit 👋"),
        h(
          "div",
          {
            style: {
              fontSize: 12,
              opacity: 0.8,
            },
          },
          "Step ",
          step + 1,
          " of ",
          titles.length,
          " – ",
          titles[step],
        ),
      ),
      step === 0 && h(Step1CreateTest, { next: () => setStep(1) }),
      step === 1 &&
        h(Step2ShadowVersions, {
          next: () => setStep(2),
          back: () => setStep(0),
        }),
      step === 2 &&
        h(Step3ResultsClean, {
          next: () => setStep(3),
          back: () => setStep(1),
        }),
      step === 3 &&
        h(Step4Ready, {
          back: () => setStep(2),
          finish,
          telemetryChoice,
          setTelemetryChoice,
        }),
    );
  }

  // Mount
  document.addEventListener("DOMContentLoaded", () => {
    // If onboarding already completed, don’t show this screen again.
    if (
      ABTESTKIT_ONBOARDING &&
      ABTESTKIT_ONBOARDING.done &&
      ABTESTKIT_ONBOARDING.links &&
      ABTESTKIT_ONBOARDING.links.dashboard
    ) {
      window.location = ABTESTKIT_ONBOARDING.links.dashboard;
      return;
    }

    const root = document.getElementById("abtestkit-onboarding-root");
    if (root) {
      wp.element.render(h(WizardApp), root);
    }
  });
})();