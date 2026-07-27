(function () {
  const { createElement: h, useState, useEffect, Fragment } = wp.element;
  const { Spinner, Button } = wp.components;
  const apiFetch = wp.apiFetch;

  function getHealthFromDashboardTest(test) {
    const health = test && typeof test === 'object' ? test.health : null;

    if (health && typeof health === 'object') {
      return {
        status: health.status || 'good',
        label: health.label || 'Good',
        summary: health.summary || '',
        checks: Array.isArray(health.checks) ? health.checks : [],
      };
    }

    return {
      status: 'good',
      label: 'Good',
      summary: '',
      checks: [],
    };
  }

  function getHealthStatusStyle(status) {
    const key = String(status || 'good');

    if (key === 'broken') {
      return {
        color: '#d63638',
        background: '#fcf0f1',
        border: '#f0b7bd',
        label: 'Broken',
      };
    }

    if (key === 'attention') {
      return {
        color: '#996800',
        background: '#fcf9e8',
        border: '#f0d98d',
        label: 'Needs attention',
      };
    }

    if (key === 'info') {
      return {
        color: '#50575e',
        background: '#f6f7f7',
        border: '#dcdcde',
        label: 'Info',
      };
    }

    return {
      color: '#008a20',
      background: '#edfaef',
      border: '#b8e6bf',
      label: 'Good',
    };
  }

  function addOpenHealthToPerformanceUrl(url) {
    const raw = String(url || '').trim();

    if (!raw) {
      return '';
    }

    try {
      const parsed = new URL(raw, window.location.href);
      parsed.searchParams.set('open_health', '1');
      parsed.hash = '';
      return parsed.toString();
    } catch (_) {
      const base = raw.split('#')[0];
      const separator = base.indexOf('?') === -1 ? '?' : '&';
      return `${base}${separator}open_health=1`;
    }
  }

  function HealthStatusPill(props) {
    const health = props.health || {};
    const style = getHealthStatusStyle(health.status);
    const href = String(props.href || '').trim();
    const tag = href ? 'a' : 'span';

    return h(
      tag,
      {
        href: href || undefined,
        title: health.summary || health.label || style.label,
        'aria-label': href ? 'Open test health details' : undefined,
        style: {
          display: 'inline-flex',
          alignItems: 'center',
          gap: '6px',
          padding: '4px 9px',
          borderRadius: '999px',
          border: `1px solid ${style.border}`,
          background: style.background,
          color: style.color,
          fontSize: '12px',
          fontWeight: '700',
          lineHeight: '1.2',
          whiteSpace: 'nowrap',
          textDecoration: 'none',
          cursor: href ? 'pointer' : 'default',
        },
      },
      [
        h('span', {
          style: {
            width: '8px',
            height: '8px',
            borderRadius: '999px',
            background: style.color,
            display: 'inline-block',
            flex: '0 0 auto',
          },
        }),
        `Test Health: ${health.label || style.label}`,
      ]
    );
  }

  function getDaysSinceTimestamp(ts) {
    const timestamp = Number(ts || 0);

    if (!timestamp) {
      return null;
    }

    const nowTs = Math.floor(Date.now() / 1000);
    return Math.max(0, (nowTs - timestamp) / 86400);
  }

  function isNoVisitorDataHealthCheck(check) {
    const label = [
      check && check.id,
      check && check.title,
      check && check.description,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();

    return (
      /\bno\s+(visitor|visit|traffic|impression|timeline)\s+(data|yet|recorded)\b/.test(label) ||
      /\b(visitor|visit|traffic|impression|timeline)\s+(data\s+)?(has\s+)?not\s+been\s+recorded\b/.test(label) ||
      /\b0\s+(visitors|visits|impressions)\b/.test(label)
    );
  }

  function shouldSuppressNoVisitorDataCheck(check, startedAt) {
    const status = String((check && check.status) || '').toLowerCase();

    if (status === 'good' || !isNoVisitorDataHealthCheck(check)) {
      return false;
    }

    const daysSinceStart = getDaysSinceTimestamp(startedAt);

    if (daysSinceStart === null) {
      return false;
    }

    return daysSinceStart < 5;
  }

  function getDisplayHealthForVisitorGracePeriod(health, startedAt) {
    if (!health || typeof health !== 'object') {
      return health;
    }

    const checks = Array.isArray(health.checks) ? health.checks : [];
    const filteredChecks = checks.filter((check) => !shouldSuppressNoVisitorDataCheck(check, startedAt));

    if (filteredChecks.length === checks.length) {
      return health;
    }

    const remainingIssueChecks = filteredChecks.filter((check) => {
      return String((check && check.status) || '').toLowerCase() !== 'good';
    });

    if (remainingIssueChecks.length) {
      return {
        ...health,
        checks: filteredChecks,
      };
    }

    return {
      ...health,
      status: 'good',
      label: 'Good',
      summary: 'Core setup looks ready. Keep an eye on the results as traffic builds.',
      checks: filteredChecks,
    };
  }

  function DashboardApp() {
    const [tests, setTests] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [banner, setBanner] = useState(
      (abtestkitDashboard && abtestkitDashboard.conflictBanner) || ''
    );
    const [activeTab, setActiveTab] = useState('all'); // all, running, paused, draft, complete
    const [openHelp, setOpenHelp] = useState(null);   // which help box is open
    const [openActionsRow, setOpenActionsRow] = useState(null);

    // Row expand/collapse for per-test details
    const [expandedRows, setExpandedRows] = useState({}); // { [testId]: true/false }



    useEffect(() => {
      const handleDocumentClick = () => {
        setOpenHelp(null);
        setOpenActionsRow(null);
      };

      document.addEventListener('click', handleDocumentClick);
      return () => document.removeEventListener('click', handleDocumentClick);
    }, []);

    useEffect(() => {
      const endpoint =
        abtestkitDashboard.rest.replace(/\/$/, '') +
        '/pt?_=' +
        String(Date.now());

      apiFetch({
        url: endpoint,
        headers: {
          'X-WP-Nonce': abtestkitDashboard.nonce,
          'Cache-Control': 'no-cache',
          Pragma: 'no-cache',
        },
      })
        .then((data) => {
          const list = Array.isArray(data) ? data : [];
          setTests(list);
          setLoading(false);

          if (list.length > 0 && window.ABTESTKIT_ONBOARDING?.rest) {
            apiFetch({
              path:
                ABTESTKIT_ONBOARDING.rest.replace(/https?:\/\/[^/]+/, '') +
                '/onboarding',
              method: 'POST',
              data: { done: true },
            }).catch(() => {
              // Non-fatal
            });
          }
        })
        .catch((err) => {
          console.error('abtestkit dashboard error', err);
          setError(
            err && err.message
              ? 'Failed to load tests: ' + err.message
              : 'Failed to load tests.'
          );
          setLoading(false);
        });
    }, []);

    if (loading) {
      return h(
        'div',
        { className: 'abtestkit-dashboard-loading' },
        h(Spinner, null),
        ' Loading tests…'
      );
    }

    if (error) {
      return h('div', { className: 'notice notice-error' }, error);
    }

    if (!tests || tests.length === 0) {
      return h(
        'div',
        { className: 'abtestkit-dashboard-empty' },
        h('p', null, 'Lets create your first test and start optimizing your site.'),
        h(
          Button,
          {
            isPrimary: true,
            href: abtestkitDashboard.createUrl,
          },
          '+ Create New Test'
        )
      );
    }
    const tabCounts = {
          all: tests.length,
          running: tests.filter((t) => t.status === 'running').length,
          paused: tests.filter((t) => t.status === 'paused' || t.status === 'winner').length,
          draft: tests.filter((t) => t.status === 'draft').length,
          complete: tests.filter((t) => t.status === 'complete').length,
        };


    const visibleTests =
      activeTab === 'all'
        ? tests
        : tests.filter((t) => {
            if (activeTab === 'running') return t.status === 'running';
            if (activeTab === 'paused') return t.status === 'paused' || t.status === 'winner';
            if (activeTab === 'draft') return t.status === 'draft';
            if (activeTab === 'complete') return t.status === 'complete';
            return true;
          });

    const helpTip = (id, content) => {
      const aria = Array.isArray(content) ? content.join(' ') : content;

      return h(
        'span',
        {
          style: {
            position: 'relative',
            display: 'inline-block',
            marginLeft: '6px',
          },
        },
        [
          h(
            'span',
            {
              onClick: (e) => {
                e.stopPropagation();
                setOpenHelp(openHelp === id ? null : id);
              },
              style: {
                display: 'inline-flex',
                alignItems: 'center',
                justifyContent: 'center',
                width: '16px',
                height: '16px',
                borderRadius: '999px',
                border: '1px solid #c3c4c7',
                color: '#50575e',
                fontSize: '11px',
                fontWeight: '700',
                lineHeight: '16px',
                cursor: 'pointer',
                userSelect: 'none',
                background: '#fff',
              },
              'aria-label': aria,
            },
            '?'
          ),

          openHelp === id &&
            h(
              'div',
              {
                style: {
                  position: 'absolute',
                  top: '22px',
                  left: '50%',
                  transform: 'translateX(-50%)',
                  zIndex: 1000,
                  width: '220px',
                  padding: '8px 10px',
                  background: '#fff',
                  border: '1px solid #ccd0d4',
                  borderRadius: '4px',
                  boxShadow: '0 4px 12px rgba(0,0,0,0.08)',
                  fontSize: '12px',
                  lineHeight: '1.4',
                  color: '#1d2327',
                  textAlign: 'left',
                },
              },
              Array.isArray(content)
                ? h(
                    'ul',
                    {
                      style: {
                        margin: '0',
                        paddingLeft: '16px',
                        listStyle: 'disc',
                      },
                    },
                    content.map((line, i) => h('li', { key: `${id}-${i}` }, line))
                  )
                : content
            ),
        ]
      );
    };

    const confirmBrokenResume = (test) => {
      if (!test || !test.auto_paused_broken) {
        return true;
      }

      return window.confirm(
        'This test was paused because it was broken.\n\nAre you sure you want to resume the test?\n\nBroken tests will be paused automatically.'
      );
    };

    const submitPtAction = (testId, action, extraFields = {}) => {
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = abtestkitDashboard.adminAction;

      const nonceInput = document.createElement('input');
      nonceInput.type = 'hidden';
      nonceInput.name = '_wpnonce';
      nonceInput.value = abtestkitDashboard.adminNonce;
      form.appendChild(nonceInput);

      const doInput = document.createElement('input');
      doInput.type = 'hidden';
      doInput.name = 'do';
      doInput.value = action;
      form.appendChild(doInput);

      const idInput = document.createElement('input');
      idInput.type = 'hidden';
      idInput.name = 'id';
      idInput.value = testId;
      form.appendChild(idInput);

      Object.entries(extraFields).forEach(([key, value]) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = value;
        form.appendChild(input);
      });

      document.body.appendChild(form);
      form.submit();
    };

    const getDeleteConfirmation = (test) => {
      const kind = String((test && test.kind) || '');
      const hasOutcome = !!(test && test.winner) || (test && test.status) === 'complete';
      const hasPhysicalVersionB =
        !hasOutcome &&
        (
          kind === 'page' ||
          kind === 'post' ||
          kind === 'reusable_section' ||
          kind === 'product'
        );

      if (!hasPhysicalVersionB) {
        return {
          message: 'Delete this test? This cannot be undone.',
          extraFields: {},
        };
      }

      if (kind === 'product') {
        return {
          message:
            'Delete this test and its Version B shadow product? Version A will not be changed. This cannot be undone.',
          extraFields: { trash_b: '1' },
        };
      }

      return {
        message:
          'Delete this test? Any Version B created by abtestkit will also be deleted. Existing content selected as Version B will not be deleted. This cannot be undone.',
        extraFields: { trash_b: '1' },
      };
    };

    const resetDashboardTest = (testId) => {
      const confirmed = window.confirm(
        'Are you sure? Resetting the test will set all metrics to zero.'
      );

      if (!confirmed) {
        return;
      }

      apiFetch({
        url: abtestkitDashboard.rest.replace(/\/$/, '') + '/pt/reset-test',
        method: 'POST',
        headers: {
          'X-WP-Nonce': abtestkitDashboard.nonce,
          'Content-Type': 'application/json',
        },
        data: {
          test_id: testId,
        },
      })
        .then((res) => {
          if (!res || !res.ok) {
            throw new Error('Unable to reset test.');
          }

          setTests((prev) =>
            (prev || []).map((test) => {
              if (test.id !== testId) return test;

              return {
                ...test,
                stats: {
                  A: {
                    impressions: 0,
                    clicks: 0,
                    purchases: 0,
                    revenue: 0,
                    revenue_per_customer: 0,
                  },
                  B: {
                    impressions: 0,
                    clicks: 0,
                    purchases: 0,
                    revenue: 0,
                    revenue_per_customer: 0,
                  },
                },
              };
            })
          );
        })
        .catch((err) => {
          console.error('abtestkit dashboard reset error', err);
          window.alert('Failed to reset test metrics.');
        });
    };

    const makeTab = (label, value) =>
        h(
          'button',
          {
            type: 'button',
            className:
              'nav-tab' + (activeTab === value ? ' nav-tab-active' : ''),
            onClick: () => setActiveTab(value),
            style: {
              cursor: 'pointer',
              flex: 1,
              textAlign: 'center',
              padding: '8px 16px',
            },
          },
          tabCounts[value] > 0 ? `${label} (${tabCounts[value]})` : label
        );


    return h(
      'div',
      { className: 'abtestkit-dashboard-app' },
      banner
        ? h(
            'div',
            {
              className: 'notice notice-error',
              style: { marginBottom: '12px' },
            },
            h('p', null, banner)
          )
        : null,
      h(
        'div',
        {
          className: 'abtestkit-dashboard-tabs nav-tab-wrapper',
          style: { marginBottom: '12px' },
        },
        [
          makeTab('All', 'all'),
          makeTab('Running', 'running'),
          makeTab('Paused', 'paused'),
          makeTab('Drafts', 'draft'),
          makeTab('Completed', 'complete'),
        ]
      ),
      h(
        'table',
        { className: 'widefat striped' },
        h(
        'thead',
        null,
        h(
            'tr',
            null,
            h('th', { style: { paddingLeft: '30px' } }, 'Title'),
            h('th', null, 'Type'),
            h('th', null, 'Status'),
            h(
              'th',
              { style: { textAlign: 'center' } },
              [
                'Impressions',
                helpTip('impressions', [
                  'Impressions are the number of times each version was shown to visitors.',
                  'Traffic is split randomly across versions.',
                  'Minimum impressions are set per test (Fast / Balanced / Precise).',
                  'Manual mode never auto-declares a winner.',
                ])
              ]
            ),
            h(
              'th',
              { style: { textAlign: 'center' } },
              [
                'Conversions',
                helpTip('conversions', [
                  'Conversions are tracked actions recorded for each version.',
                  'This is based on the conversion goal you selected (e.g. click, add to cart, scroll depth, or order).',
                  'A winner is decided when abtestkit is confident.',
                ])
              ]
            ),
            h('th', null, '')
        )
        ),
        h(
          'tbody',
          null,
          visibleTests.length === 0
            ? h(
                'tr',
                { key: 'empty' },
                h(
                  'td',
                  {
                    colSpan: 6,
                    style: {
                      textAlign: 'center',
                      padding: '16px',
                    },
                  },
                  'No tests in this view yet.'
                )
              )
            : visibleTests.map((t) => {
                const isRunning  = t.status === 'running';
                const isPaused   = t.status === 'paused';
                const isDraft    = t.status === 'draft';
                const isComplete = t.status === 'complete';

                // Winner = test has an auto-declared winner and should only offer Apply/Delete.
                const isWinner =
                  t.status === 'winner' || (isPaused && !!t.winner && !isComplete);

                const hasWinner  = !!t.winner && !isComplete;

                const previewUrl =
                  t.preview_url || t.url || t.permalink || null;

                const previewA = t.preview_a || previewUrl || null;
                const previewB = t.preview_b || null;

                const titleUrl = t.performance_url || t.url || null;
                const performanceUrl = t.performance_url || null;
                const healthPerformanceUrl = addOpenHealthToPerformanceUrl(performanceUrl);

                const title = t.name || t.title || '(untitled)';

                const stats = t.stats || { A: {}, B: {} };
                const health = getDisplayHealthForVisitorGracePeriod(getHealthFromDashboardTest(t), t.started_at);
                const goalKey = String(t.goal || '').toLowerCase();
                const isRevenueGoal = goalKey === 'purchase';
                const isScrollDepthGoal = goalKey === 'scroll_depth' || goalKey === 'scroll-depth';
                const conversionKey = isRevenueGoal ? 'purchases' : 'clicks';
                const rawScrollDepth =
                  t.scroll_depth ||
                  t.scrollDepth ||
                  (t.settings && (t.settings.scroll_depth || t.settings.scrollDepth)) ||
                  (t.config && (t.config.scroll_depth || t.config.scrollDepth)) ||
                  (t.meta && (t.meta.scroll_depth || t.meta.scrollDepth)) ||
                  50;
                const parsedScrollDepth = parseInt(rawScrollDepth, 10);
                const scrollDepth = [25, 50, 75, 90].includes(parsedScrollDepth) ? parsedScrollDepth : 50;

                const impressionsA = (stats.A && stats.A.impressions) || 0;
                const impressionsB = (stats.B && stats.B.impressions) || 0;
                const clicksA = (stats.A && stats.A[conversionKey]) || 0;
                const clicksB = (stats.B && stats.B[conversionKey]) || 0;

                // Shared mini A/B stat block styles (keeps alignment consistent)
                const abMiniWrapStyle = {
                  width: '100%',
                  maxWidth: '180px',
                  margin: '0 auto',
                };

                const abMiniHeaderStyle = {
                  display: 'grid',
                  gridTemplateColumns: '1fr 1fr',
                  gap: '8px',
                  textAlign: 'center',
                  fontSize: '11px',
                  fontWeight: '600',
                  color: '#6b7280',
                  lineHeight: '14px',
                  letterSpacing: '0.02em',
                };

                const abMiniValueStyle = {
                  display: 'grid',
                  gridTemplateColumns: '1fr 1fr',
                  gap: '8px',
                  textAlign: 'center',
                  marginTop: '2px',
                  fontSize: '13px',
                  fontWeight: '700',
                  color: '#111',
                  lineHeight: '16px',
                };

                const revenueA = Number((stats.A && stats.A.revenue) || 0);
                const revenueB = Number((stats.B && stats.B.revenue) || 0);

                const leaderMetricA = isRevenueGoal
                  ? (impressionsA > 0 ? revenueA / impressionsA : 0)
                  : (impressionsA > 0 ? clicksA / impressionsA : 0);

                const leaderMetricB = isRevenueGoal
                  ? (impressionsB > 0 ? revenueB / impressionsB : 0)
                  : (impressionsB > 0 ? clicksB / impressionsB : 0);

                let percentA = 50;
                let percentB = 50;

                if (leaderMetricA > 0 || leaderMetricB > 0) {
                  const sum = leaderMetricA + leaderMetricB;
                  percentA = Math.round((leaderMetricA / sum) * 100);
                  percentB = 100 - percentA;
                }

                const confidenceContent = h(
                  Fragment,
                  null,
                  h(
                    'div',
                    {
                      style: {
                        display: 'flex',
                        justifyContent: 'space-between',
                        marginBottom: '4px',
                        fontSize: '11px',
                        fontWeight: 'bold',
                        width: '100%',
                      },
                    },
                    [
                      h('span', null, `A: ${percentA}%`),
                      h('span', null, `B: ${percentB}%`),
                    ]
                  ),
                  h(
                    'div',
                    {
                      style: {
                        position: 'relative',
                        height: '16px',
                        borderRadius: '6px',
                        overflow: 'hidden',
                        background: '#f9f9f9',
                        width: '100%',
                      },
                    },
                    [
                      h(
                        'div',
                        {
                          style: {
                            display: 'flex',
                            width: '100%',
                            height: '100%',
                          },
                        },
                        [
                          h('div', {
                            style: {
                              width: `${percentA}%`,
                              backgroundColor: '#cfe2f3',
                              transition: 'width 0.4s ease',
                            },
                          }),
                          h('div', {
                            style: {
                              width: `${percentB}%`,
                              backgroundColor: '#f4cccc',
                              transition: 'width 0.4s ease',
                            },
                          }),
                        ]
                      ),
                      h('div', {
                        style: {
                          position: 'absolute',
                          top: 0,
                          bottom: 0,
                          left: `${percentA}%`,
                          width: '2px',
                          background: '#333',
                          transition: 'left 0.4s ease',
                        },
                      }),
                    ]
                  )
                );

                // ── Expand/collapse helpers ────────────────────────────────
                const isExpanded = !!expandedRows[t.id];

                const toggleExpanded = (e) => {
                  e.preventDefault();
                  e.stopPropagation();
                  setExpandedRows((prev) => ({
                    ...prev,
                    [t.id]: !prev[t.id],
                  }));
                };

                const safe = (v) => (v === undefined || v === null || v === '' ? '—' : v);
                const isCustomCss = String(t.kind || '') === 'custom_css';
                const customCss = String(t.custom_css || '').trim();
                const customCssMarkers = Array.isArray(t.css_markers) ? t.css_markers : [];
                const customCssScopeLabel =
                  t.css_scope === 'product'
                    ? 'WooCommerce product'
                    : t.css_scope === 'post'
                    ? 'Post'
                    : t.css_scope === 'page'
                    ? 'Page'
                    : '—';
                const isCustomHtml = String(t.kind || '') === 'custom_html';
                const customHtmlChanges = Array.isArray(t.html_changes) ? t.html_changes : [];
                const customHtmlScopeLabel =
                  t.html_scope === 'product'
                    ? 'WooCommerce product'
                    : t.html_scope === 'post'
                    ? 'Post'
                    : t.html_scope === 'page'
                    ? 'Page'
                    : '—';

                const decisionProfile =
                  t.decision_profile ||
                  t.rule_profile ||
                  t.profile ||
                  t.decision_speed ||
                  t.decision_rule ||
                  t.rule ||
                  (isManual ? 'Manual' : 'Auto');

                const pickFirst = (...vals) => {
                  for (let i = 0; i < vals.length; i++) {
                    const v = vals[i];
                    if (v !== undefined && v !== null && v !== '') return v;
                  }
                  return null;
                };

                const normalizeGoalLabel = (raw) => {
                  const s = String(raw).trim().toLowerCase();

                  // common canonical labels
                  if (s === 'click' || s === 'clicks' || s.includes('click')) return 'Click';
                  if (s === 'destination_url' || s === 'destination-url' || s === 'destination' || s.includes('destination url')) return 'Destination URL';
                  if (s === 'scroll_depth' || s === 'scroll-depth' || s.includes('scroll depth')) return 'Scroll depth';
                  if (s === 'add_to_cart' || s === 'add-to-cart' || s.includes('add to cart')) return 'Add to cart';
                  if (s === 'submit' || s.includes('form')) return 'Form submission';
                  if (s === 'purchase' || s.includes('order')) return 'Completed Orders (Revenue)';

                  // fallback: Title Case the raw value
                  return String(raw)
                    .replace(/[_-]+/g, ' ')
                    .replace(/\s+/g, ' ')
                    .trim()
                    .replace(/\b\w/g, (c) => c.toUpperCase());
                };

                const conversionGoalRaw = pickFirst(
                  // flat keys
                  t.conversion_goal,
                  t.conversionGoal,
                  t.goal,
                  t.goal_type,
                  t.conversion_type,
                  t.conversion,
                  t.conversion_event,
                  t.conversionEvent,
                  t.event,
                  t.event_name,
                  t.eventName,
                  t.cta_type,
                  t.cta,

                  // nested keys (very common in your plugin patterns)
                  t.rules && (t.rules.conversion_goal || t.rules.goal || t.rules.goal_type || t.rules.conversion_type || t.rules.conversion),
                  t.settings && (t.settings.conversion_goal || t.settings.goal || t.settings.conversion_type),
                  t.config && (t.config.conversion_goal || t.config.goal || t.config.conversion_type),
                  t.meta && (t.meta.conversion_goal || t.meta.goal || t.meta.conversion_type),

                  // sometimes stored inside a “decision” object
                  t.decision && (t.decision.conversion_goal || t.decision.goal || t.decision.conversion_type)
                );

                // If we still can't find it in the payload, default based on test type
                const conversionGoal =
                  conversionGoalRaw
                    ? normalizeGoalLabel(conversionGoalRaw)
                    : (t.kind === 'product' ? 'Add to cart' : 'Click');

                const minImpressions =
                  t.min_impressions ||
                  t.minimum_impressions ||
                  (t.thresholds && t.thresholds.min_impressions) ||
                  (t.rules && t.rules.min_impressions) ||
                  '—';

                const minConversions =
                  t.min_conversions ||
                  t.minimum_conversions ||
                  t.min_clicks ||
                  t.minimum_clicks ||
                  (t.thresholds && (t.thresholds.min_conversions || t.thresholds.min_clicks)) ||
                  (t.rules && (t.rules.min_conversions || t.rules.min_clicks)) ||
                  '—';

                const normalizeConfidence = (raw) => {
                  // Accept: 0.95, 95, "95", "95%", "0.95"
                  const s = String(raw).trim();
                  if (!s) return null;

                  // Already looks like "95%"
                  if (/%$/.test(s)) return s;

                  const n = Number(s);
                  if (!Number.isFinite(n)) return null;

                  // 0.95 -> 95%
                  if (n > 0 && n <= 1) return `${Math.round(n * 100)}%`;

                  // 95 -> 95%
                  if (n > 1 && n <= 100) return `${Math.round(n)}%`;

                  return null;
                };

                const confidenceRaw = pickFirst(
                  // flat keys
                  t.confidence,
                  t.confidence_target,
                  t.confidenceTarget,
                  t.confidence_threshold,
                  t.confidenceThreshold,
                  t.target_confidence,
                  t.targetConfidence,

                  // nested keys
                  t.rules &&
                    (t.rules.confidence ||
                      t.rules.confidence_target ||
                      t.rules.confidence_threshold ||
                      t.rules.target_confidence),
                  t.thresholds &&
                    (t.thresholds.confidence ||
                      t.thresholds.confidence_target ||
                      t.thresholds.confidence_threshold),
                  t.settings &&
                    (t.settings.confidence ||
                      t.settings.confidence_target ||
                      t.settings.confidence_threshold),
                  t.decision &&
                    (t.decision.confidence ||
                      t.decision.confidence_target ||
                      t.decision.confidence_threshold)
                );

                // If API doesn't supply it, derive from decision profile (your rules)
                const profileKey = String(decisionProfile || '')
                  .trim()
                  .toLowerCase();

                const derivedConfidence =
                  profileKey === 'fast' ? '90%' : '95%';

                const confidenceTarget =
                  normalizeConfidence(confidenceRaw) || derivedConfidence;

                const detailsBox = h(
                  'div',
                  {
                    style: {
                      border: '1px solid #e5e7eb',
                      background: '#fff',
                      borderRadius: '8px',
                      padding: '12px',
                      boxShadow: '0 2px 10px rgba(0,0,0,0.04)',
                      display: 'grid',
                      gridTemplateColumns: 'repeat(3, minmax(0, 1fr))',
                      gap: '10px 14px',
                      fontSize: '12px',
                      color: '#1d2327',
                    },
                  },
                  [
                    h('div', null, [
                      h('div', { style: { fontWeight: 700, marginBottom: '2px' } }, 'Goal'),
                      h('div', null, safe(conversionGoal)),
                    ]),
                    isCustomCss && h('div', null, [
                      h('div', { style: { fontWeight: 700, marginBottom: '2px' } }, 'Location'),
                      h('div', null, customCssScopeLabel),
                    ]),
                    isCustomCss && h('div', null, [
                      h('div', { style: { fontWeight: 700, marginBottom: '2px' } }, 'Version B CSS'),
                      h('div', null, customCss ? 'Saved' : 'Missing'),
                    ]),
                    isCustomCss && h('div', null, [
                      h('div', { style: { fontWeight: 700, marginBottom: '2px' } }, 'B-only markers'),
                      h('div', null, customCssMarkers.length ? `${customCssMarkers.length} saved` : 'None'),
                    ]),
                    isCustomHtml && h('div', null, [
                      h('div', { style: { fontWeight: 700, marginBottom: '2px' } }, 'Location'),
                      h('div', null, customHtmlScopeLabel),
                    ]),
                    isCustomHtml && h('div', null, [
                      h('div', { style: { fontWeight: 700, marginBottom: '2px' } }, 'Version B HTML'),
                      h(
                        'div',
                        null,
                        customHtmlChanges.length
                          ? `${customHtmlChanges.length} ${customHtmlChanges.length === 1 ? 'change' : 'changes'} saved`
                          : 'Missing'
                      ),
                    ]),
                    isCustomHtml && h('div', { style: { gridColumn: '1 / -1' } }, [
                      h('div', { style: { fontWeight: 700, marginBottom: '4px' } }, 'HTML selectors'),
                      customHtmlChanges.length
                        ? h(
                            'div',
                            {
                              style: {
                                display: 'flex',
                                flexWrap: 'wrap',
                                gap: '6px',
                              },
                            },
                            customHtmlChanges.map((change, index) => {
                              const operationLabel = {
                                replace_contents: 'Replace contents',
                                insert_before: 'Insert before',
                                insert_after: 'Insert after',
                                prepend_inside: 'Prepend inside',
                                append_inside: 'Append inside',
                              }[String(change.operation || 'replace_contents')] || 'Replace contents';
                              const matchLabel = String(change.match_mode || 'all') === 'first'
                                ? 'First match'
                                : 'All matches';

                              return h(
                                'div',
                                {
                                  key: `${change.selector || 'selector'}-${index}`,
                                  style: { display: 'inline-flex', alignItems: 'center', gap: '5px' },
                                },
                                [
                                  h('code', null, change.selector || '—'),
                                  h('span', { style: { color: '#6b7280' } }, `${operationLabel} · ${matchLabel}`),
                                ]
                              );
                            })
                          )
                        : h('div', null, 'None'),
                    ]),
                    isScrollDepthGoal && h('div', null, [
                      h('div', { style: { fontWeight: 700, marginBottom: '2px' } }, 'Scroll Depth'),
                      h('div', null, `${scrollDepth}% of the page`),
                    ]),
                    h('div', null, [
                      h('div', { style: { fontWeight: 700, marginBottom: '2px' } }, 'Auto Test Mode'),
                      h('div', null, safe(decisionProfile)),
                    ]),
                    h('div', null, [
                      h('div', { style: { fontWeight: 700, marginBottom: '2px' } }, 'Confidence Target'),
                      h('div', null, safe(confidenceTarget)),
                    ]),
                    h('div', null, [
                      h('div', { style: { fontWeight: 700, marginBottom: '2px' } }, 'Min. Impressions'),
                      h('div', null, safe(minImpressions)),
                    ]),
                    h('div', null, [
                      h('div', { style: { fontWeight: 700, marginBottom: '2px' } }, 'Min. Conversions'),
                      h('div', null, safe(minConversions)),
                    ]),
                    h('div', null, [
                      h('div', { style: { fontWeight: 700, marginBottom: '2px' } }, 'Winner'),
                      h('div', null, safe(t.winner ? String(t.winner).toUpperCase() : 'Awaiting result...')),
                    ]),
                  ]
                );

                return h(
                  Fragment,
                  { key: `rowwrap-${t.id}` },
                  [
                    h(
                      'tr',
                      { key: t.id },
                      h(
                        'td',
                        { style: { textAlign: 'left', verticalAlign: 'middle' } },
                        h(
                          Fragment,
                          null,
                          h(
                            'div',
                            {
                              style: {
                                display: 'flex',
                                alignItems: 'center',
                                gap: '8px',
                              },
                            },
                            [
                              h(
                                'button',
                                {
                                  type: 'button',
                                  onClick: toggleExpanded,
                                  'aria-label': isExpanded ? 'Collapse details' : 'Expand details',
                                  style: {
                                    width: '22px',
                                    height: '22px',
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    border: '1px solid #d0d7de',
                                    borderRadius: '6px',
                                    background: '#fff',
                                    cursor: 'pointer',
                                    padding: 0,
                                    lineHeight: '1',
                                    color: '#2271b1',
                                  },
                                },
                                isExpanded ? '▾' : '▸'
                              ),

                              titleUrl
                                ? h(
                                    'a',
                                    {
                                      href: titleUrl,
                                      target: '_blank',
                                      rel: 'noopener noreferrer',
                                      style: { fontWeight: 600 },
                                    },
                                    title
                                  )
                                : h('span', { style: { fontWeight: 600 } }, title),
                            ]
                          ),

                          (t.kind === 'reusable_section' || t.kind === 'custom_css' || t.kind === 'custom_html') &&
                            t.testing_title &&
                            h(
                              'div',
                              {
                                style: {
                                  marginTop: '4px',
                                  fontSize: '11px',
                                  paddingLeft: '30px',
                                  color: '#50575e',
                                },
                              },
                              (t.kind === 'custom_css' || t.kind === 'custom_html' ? 'Location: ' : 'Testing: ') + t.testing_title
                            ),

                          (previewA || previewB) &&
                            h(
                              'div',
                              {
                                style: {
                                  marginTop: '4px',
                                  fontSize: '11px',
                                  paddingLeft: '30px',
                                },
                              },
                              [
                                previewA &&
                                  h(
                                    'a',
                                    {
                                      href: previewA,
                                      target: '_blank',
                                      rel: 'noopener noreferrer',
                                    },
                                    'Preview Version A'
                                  ),
                                previewA &&
                                  previewB &&
                                  h(
                                    'span',
                                    { style: { margin: '0 4px' } },
                                    '|'
                                  ),
                                previewB &&
                                  h(
                                    'a',
                                    {
                                      href: previewB,
                                      target: '_blank',
                                      rel: 'noopener noreferrer',
                                    },
                                    'Preview Version B'
                                  ),
                              ]
                            ),

                          !isComplete &&
                            h(
                              'div',
                              {
                                style: {
                                  marginTop: '6px',
                                  paddingLeft: '30px',
                                },
                              },
                              h(HealthStatusPill, {
                                health,
                                href: healthPerformanceUrl,
                              })
                            )
                        )
                      ),
                      h(
                        'td',
                        {
                          style: {
                            textAlign: 'left',
                            verticalAlign: 'middle',
                          },
                        },
                        t.kind_label || (t.kind === 'reusable_section' ? 'Reusable Section' : (t.kind || 'page'))
                      ),
                      h(
                        'td',
                        {
                          style: {
                            textAlign: 'left',
                            verticalAlign: 'middle',
                          },
                        },
                        isWinner
                          ? 'Winner'
                          : (t.status === 'running' &&
                              ((t.decision_mode || '') === 'manual' ||
                                (t.decision_rule || '') === 'manual'))
                          ? 'Running (Manual)'
                          : (t.status || 'paused')
                      ),
                      h(
                        'td',
                        {
                          style: {
                            textAlign: 'center',
                            verticalAlign: 'middle',
                          },
                        },
                        h(Fragment, null, [
                          h(
                            'div',
                            { style: { marginTop: isRunning ? '6px' : '0px' } },
                            h(
                              'div',
                              { style: abMiniWrapStyle },
                              [
                                h(
                                  'div',
                                  { style: abMiniHeaderStyle },
                                  [h('div', null, 'A'), h('div', null, 'B')]
                                ),
                                h(
                                  'div',
                                  { style: abMiniValueStyle },
                                  [h('div', null, `${impressionsA}`), h('div', null, `${impressionsB}`)]
                                ),
                              ]
                            )
                          ),
                        ])
                      ),
                      h(
                        'td',
                        {
                          style: {
                            textAlign: 'center',
                            verticalAlign: 'middle',
                          },
                        },
                        h(
                          'div',
                          {
                            style: {
                              display: 'flex',
                              flexDirection: 'column',
                              alignItems: 'center',
                              width: '100%',
                            },
                          },
                          [
                            isRunning &&
                              h(
                                'div',
                                { style: { width: '100%', marginBottom: '4px' } },
                                confidenceContent
                              ),

                            h(
                              'div',
                              { style: { marginTop: isRunning ? '6px' : '0px', width: '100%' } },
                              h(
                                'div',
                                { style: abMiniWrapStyle },
                                [
                                  h(
                                    'div',
                                    { style: abMiniHeaderStyle },
                                    [h('div', null, 'A'), h('div', null, 'B')]
                                  ),
                                  h(
                                    'div',
                                    { style: abMiniValueStyle },
                                    [h('div', null, `${clicksA}`), h('div', null, `${clicksB}`)]
                                  ),
                                ]
                              )
                            ),
                          ]
                        )
                      ),
                      h(
                        'td',
                        {
                          style: {
                            textAlign: 'right',
                            verticalAlign: 'middle',
                            whiteSpace: 'nowrap',
                          },
                        },
                        h(
                          'div',
                          {
                            style: {
                              position: 'relative',
                              display: 'inline-block',
                            },
                            onClick: (e) => e.stopPropagation(),
                          },
                          [
                            h(
                              'button',
                              {
                                type: 'button',
                                className: 'button',
                                onClick: (e) => {
                                  e.stopPropagation();
                                  setOpenActionsRow(openActionsRow === t.id ? null : t.id);
                                },
                                style: {
                                  background: 'transparent',
                                  border: '1px solid #2271b1',
                                  color: '#2271b1',
                                  fontWeight: '500',
                                  padding: '0 12px',
                                  fontSize: '13px',
                                  height: '32px',
                                  lineHeight: '30px',
                                  borderRadius: '3px',
                                  cursor: 'pointer',
                                  whiteSpace: 'nowrap',
                                  textDecoration: 'none',
                                  display: 'inline-flex',
                                  alignItems: 'center',
                                  gap: '6px',
                                },
                              },
                              [
                                'Actions',
                                h(
                                  'span',
                                  {
                                    style: {
                                      display: 'inline-flex',
                                      alignItems: 'center',
                                      justifyContent: 'center',
                                    },
                                  },
                                  h(
                                    'svg',
                                    {
                                      width: 12,
                                      height: 12,
                                      viewBox: '0 0 20 20',
                                      fill: 'none',
                                      style: {
                                        transform: openActionsRow === t.id ? 'rotate(180deg)' : 'rotate(0deg)',
                                        transition: 'transform 0.15s ease',
                                      },
                                    },
                                    h('path', {
                                      d: 'M5 7l5 5 5-5',
                                      stroke: 'currentColor',
                                      strokeWidth: 2,
                                      strokeLinecap: 'round',
                                      strokeLinejoin: 'round',
                                    })
                                  )
                                ),
                              ]
                            ),

                            openActionsRow === t.id &&
                              h(
                                'div',
                                {
                                  style: {
                                    position: 'absolute',
                                    top: 'calc(100% + 8px)',
                                    right: 0,
                                    minWidth: '150px',
                                    background: '#fff',
                                    border: '1px solid #dcdcde',
                                    borderRadius: '6px',
                                    boxShadow: '0 8px 24px rgba(0,0,0,0.12)',
                                    padding: '6px 0',
                                    zIndex: 1000,
                                  },
                                },
                                [
                                  performanceUrl &&
                                    h(
                                      'a',
                                      {
                                        href: performanceUrl,
                                        onClick: () => setOpenActionsRow(null),
                                        style: {
                                          display: 'block',
                                          width: '100%',
                                          textAlign: 'left',
                                          padding: '10px 14px',
                                          background: '#fff',
                                          color: '#1d2327',
                                          textDecoration: 'none',
                                          fontSize: '13px',
                                          boxSizing: 'border-box',
                                        },
                                      },
                                      'View Performance'
                                    ),

                                  !isComplete &&
                                    h(
                                      'button',
                                      {
                                        type: 'button',
                                        onClick: () => {
                                          setOpenActionsRow(null);
                                          if (isRunning) {
                                            submitPtAction(t.id, 'pause');
                                          } else {
                                            if (!confirmBrokenResume(t)) {
                                              return;
                                            }

                                            submitPtAction(
                                              t.id,
                                              'start',
                                              t.auto_paused_broken ? { confirm_broken_resume: '1' } : {}
                                            );
                                          }
                                        },
                                        style: {
                                          display: 'block',
                                          width: '100%',
                                          textAlign: 'left',
                                          padding: '10px 14px',
                                          border: '0',
                                          background: '#fff',
                                          cursor: 'pointer',
                                          fontSize: '13px',
                                        },
                                      },
                                      isRunning ? 'Pause' : 'Resume'
                                    ),

                                  !isComplete &&
                                    h(
                                      'button',
                                      {
                                        type: 'button',
                                        onClick: () => {
                                          setOpenActionsRow(null);
                                          resetDashboardTest(t.id);
                                        },
                                        style: {
                                          display: 'block',
                                          width: '100%',
                                          textAlign: 'left',
                                          padding: '10px 14px',
                                          border: '0',
                                          background: '#fff',
                                          cursor: 'pointer',
                                          fontSize: '13px',
                                        },
                                      },
                                      'Reset'
                                    ),

                                  h(
                                    'button',
                                    {
                                      type: 'button',
                                      onClick: () => {
                                        setOpenActionsRow(null);

                                        const confirmation = getDeleteConfirmation(t);

                                        if (!window.confirm(confirmation.message)) {
                                          return;
                                        }

                                        submitPtAction(t.id, 'delete', confirmation.extraFields);
                                      },
                                      style: {
                                        display: 'block',
                                        width: '100%',
                                        textAlign: 'left',
                                        padding: '10px 14px',
                                        border: '0',
                                        background: '#fff',
                                        cursor: 'pointer',
                                        fontSize: '13px',
                                        color: '#b32d2e',
                                      },
                                    },
                                    'Delete'
                                  ),
                                ].filter(Boolean)
                              ),
                          ]
                        )
                      )
                    ),

                    isExpanded &&
                      h(
                        'tr',
                        { key: `details-${t.id}` },
                        h(
                          'td',
                          {
                            colSpan: 6,
                            style: {
                              padding: '10px 12px 14px',
                              background: '#f6f7f7',
                            },
                          },
                          detailsBox
                        )
                      ),
                  ]
                );
              })
        )
      ),

      // ── Dashboard footer links ─────────────────────────────
      h(
        'div',
        {
          style: {
            marginTop: '24px',
            paddingTop: '12px',
            borderTop: '1px solid #e5e7eb',
            display: 'flex',
            justifyContent: 'center',
            gap: '16px',
            fontSize: '13px',
          },
        },
        [
          h(
            'a',
            {
              href:
                'mailto:abtestkit@gmail.com' +
                '?subject=' +
                encodeURIComponent('Feature request') +
                '&body=' +
                encodeURIComponent(
                  'Hi abtestkit team,\n\nI have a feature request:\n\n'
                ),
              style: {
                textDecoration: 'none',
                color: '#2271b1',
                cursor: 'pointer',
              },
            },
            'Request a feature'
          ),
          h(
            'a',
            {
              href:
                'mailto:abtestkit@gmail.com' +
                '?subject=' +
                encodeURIComponent('Support request') +
                '&body=' +
                encodeURIComponent(
                  'Hi abtestkit team,\n\nI need help with\n\n'
                ),
              style: {
                textDecoration: 'none',
                color: '#2271b1',
                cursor: 'pointer',
              },
            },
            'Support'
          ),
        ]
      )
    );
  }

    document.addEventListener('DOMContentLoaded', function () {
    var root = document.getElementById('abtestkit-dashboard-root');
    if (!root) return;

    // Add "Create New Test +" aligned to the right of the page heading
    var heading = document.querySelector('.wrap h1');
    if (heading && !document.getElementById('abtestkit-create-test-button')) {
        heading.style.display = 'flex';
        heading.style.alignItems = 'center';
        heading.style.justifyContent = 'space-between';

        var link = document.createElement('a');
        link.id = 'abtestkit-create-test-button';
        link.href = abtestkitDashboard.createUrl;
        link.className = 'page-title-action';
        link.textContent = 'Create New Test +';

        heading.appendChild(link);
    }

    wp.element.render(h(DashboardApp), root);
    });
})();
