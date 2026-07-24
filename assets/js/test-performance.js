(function () {
    const { createElement: h, useState, useEffect, useRef, Fragment } = wp.element;
  const { Spinner, Button } = wp.components;
  const apiFetch = wp.apiFetch;

  function formatGoal(goal) {
    const map = {
      clicks: 'Click',
      click: 'Click',
      form: 'Form submission',
      add_to_cart: 'Add to cart',
      purchase: 'Orders (Revenue)',
      destination_url: 'Destination URL',
      scroll_depth: 'Scroll depth',
    };
    return map[goal] || (goal ? String(goal).replace(/[_-]+/g, ' ') : '—');
  }

  function formatDecision(rule, mode) {
    if (mode === 'manual' || rule === 'manual') {
      return 'Manual';
    }
    if (!rule) return 'Balanced';
    return String(rule).charAt(0).toUpperCase() + String(rule).slice(1);
  }

  function getClickTargetsFromPayload(test) {
    if (!test || typeof test !== 'object') {
      return [];
    }

    const candidates = [
      test.links,
      test.click_targets,
      test.clickTargets,
      test.targets,
      test.conversion_targets,
      test.conversionTargets,
      test.target_urls,
      test.targetUrls,
      test.link_targets,
      test.linkTargets,
    ];

    const normalize = (value) => {
      if (Array.isArray(value)) {
        return value
          .map((item) => {
            if (typeof item === 'string') {
              return item;
            }

            if (item && typeof item === 'object') {
              return (
                item.target ||
                item.url ||
                item.href ||
                item.selector ||
                item.value ||
                ''
              );
            }

            return '';
          })
          .map((item) => String(item || '').trim())
          .filter(Boolean);
      }

      if (typeof value === 'string') {
        return value
          .split(',')
          .map((item) => item.trim())
          .filter(Boolean);
      }

      return [];
    };

    for (let i = 0; i < candidates.length; i++) {
      const targets = normalize(candidates[i]);
      if (targets.length) {
        return targets;
      }
    }

    return [];
  }

  function prettifySelectorPart(part) {
    return String(part || '')
      .replace(/:nth-of-type\([^)]*\)/g, '')
      .replace(/[.#>]/g, ' ')
      .replace(/\[[^\]]*\]/g, ' ')
      .replace(/\bwp\b/gi, '')
      .replace(/\bblock\b/gi, '')
      .replace(/\bbutton\b/gi, '')
      .replace(/\blink\b/gi, '')
      .replace(/[_-]+/g, ' ')
      .replace(/\s+/g, ' ')
      .trim()
      .replace(/\b\w/g, (m) => m.toUpperCase());
  }

  function getSelectorEnding(target) {
    const parts = String(target || '')
      .split('>')
      .map((part) => part.trim())
      .filter(Boolean);

    return parts.length ? parts[parts.length - 1] : String(target || '').trim();
  }

  function formatClickTargetForDisplay(target, index) {
    const raw = String(target || '').trim();
    const withoutWildcard = raw.replace(/\*$/, '');
    const lower = raw.toLowerCase();

    // Important:
    // A leading "#" is normally a CSS ID selector in our saved click targets,
    // not a URL/hash link. Do not treat "#" as a URL here.
    const looksLikeUrl =
      /^https?:\/\//i.test(withoutWildcard) ||
      withoutWildcard.charAt(0) === '/' ||
      withoutWildcard.charAt(0) === '?';

    if (looksLikeUrl) {
      let label = withoutWildcard || raw;

      try {
        const url = /^https?:\/\//i.test(withoutWildcard)
          ? new URL(withoutWildcard)
          : null;

        if (url) {
          label = `${url.pathname || '/'}${url.search || ''}${url.hash || ''}`;
        }
      } catch (_) {}

      if (label === '/') {
        return {
          title: 'Homepage link',
          description: 'Clicks on links that point to the homepage count as conversions.',
          technicalLabel: 'Tracked URL',
        };
      }

      return {
        title: raw.endsWith('*') ? `Links to ${label}` : `Exact link to ${label}`,
        description: raw.endsWith('*')
          ? 'Any clicked link whose destination starts with this URL/path counts as a conversion.'
          : 'Only clicks matching this exact URL/path count as conversions.',
        technicalLabel: 'Tracked URL',
      };
    }

    const ending = getSelectorEnding(raw);

    const rules = [
      {
        match: /(single_add_to_cart_button|add-to-cart|add_to_cart|ajax_add_to_cart|variations_button)/,
        title: 'Add to cart button',
        description: 'Clicks on the WooCommerce add-to-cart button count as conversions.',
      },
      {
        match: /(checkout|wc-proceed-to-checkout)/,
        title: 'Checkout link/button',
        description: 'Clicks on a checkout or proceed-to-checkout target count as conversions.',
      },
      {
        match: /(reviews|tab-title-reviews|reviews_tab)/,
        title: 'Reviews tab',
        description: 'Clicks on the product reviews tab count as conversions.',
      },
      {
        match: /(site-logo|custom-logo|(^|[^a-z0-9_])logo([^a-z0-9_]|$))/,
        title: 'Site logo link',
        description: 'Clicks on the site logo count as conversions.',
      },
      {
        match: /(menu-item|nav-menu|main-navigation|navigation)/,
        title: 'Navigation menu item',
        description: 'Clicks on this menu/navigation item count as conversions.',
      },
      {
        match: /(wp-block-button__link|wp-block-button)/,
        title: 'Button link',
        description: 'Clicks on this WordPress button block count as conversions.',
      },
      {
        match: /(input\[type=['"]?submit|(^|[^a-z0-9_])submit([^a-z0-9_]|$))/,
        title: 'Submit button',
        description: 'Clicks on this submit button count as conversions.',
      },
      {
        match: /(^|[^a-z0-9_])button([^a-z0-9_]|$)/,
        title: 'Button',
        description: 'Clicks on this button count as conversions.',
      },
      {
        match: /(^|[\s>])a([.#:\[]|$)|(^|[^a-z0-9_])link([^a-z0-9_]|$)/,
        title: 'Link',
        description: 'Clicks on this link count as conversions.',
      },
      {
        match: /(^|[^a-z0-9_])form([^a-z0-9_]|$)/,
        title: 'Form element',
        description: 'Clicks on this selected form element count as conversions.',
      },
    ];

    const matched = rules.find((rule) => rule.match.test(lower));

    if (matched) {
      return {
        title: matched.title,
        description: matched.description,
        technicalLabel: 'Technical selector',
      };
    }

    if (raw.charAt(0) === '#') {
      const idOnly = raw
        .replace(/^#/, '')
        .split(/[ >.:[]/)[0]
        .trim();

      if (idOnly) {
        return {
          title: `Element ID: ${idOnly}`,
          description: 'Clicks on this selected page element count as conversions.',
          technicalLabel: 'Technical selector',
        };
      }
    }

    const readableEnding = prettifySelectorPart(ending);

    return {
      title: readableEnding || `Click target ${index + 1}`,
      description: 'Clicks on this selected page element count as conversions.',
      technicalLabel: 'Technical selector',
    };
  }

  function formatDate(ts) {
    if (!ts) return '—';
    const d = new Date(ts * 1000);
    return d.toLocaleString();
  }

  function sumTimeline(timeline, variant, key) {
    return (timeline || []).reduce((total, row) => {
      return total + (((row[variant] || {})[key]) || 0);
    }, 0);
  }

  function maxValue(series) {
    let max = 0;
    (series || []).forEach((n) => {
      if (n > max) max = n;
    });
    return max;
  }

  function formatBucketDate(bucket) {
    if (!bucket) return '—';
    const d = new Date(bucket + 'T00:00:00');
    return d.toLocaleDateString('en-GB');
  }

  function getWordPressAdminTitleSuffix() {
    const currentTitle = String(document.title || '');
    const wpSeparatorMatch = currentTitle.match(/\s[‹-]\s.+$/);

    if (wpSeparatorMatch && wpSeparatorMatch[0]) {
      return wpSeparatorMatch[0];
    }

    return ' ‹ WordPress';
  }

  function formatUrlForDocumentTitle(url) {
    const raw = String(url || '').trim();

    if (!raw) {
      return '';
    }

    try {
      const parsed = new URL(raw);
      const path = `${parsed.pathname || '/'}${parsed.search || ''}${parsed.hash || ''}`;
      const cleanPath = path === '/' ? '' : path.replace(/\/$/, '');
      return `${parsed.hostname}${cleanPath}`;
    } catch (_) {
      return raw.replace(/^https?:\/\//i, '').replace(/\/$/, '');
    }
  }

  function setPerformanceDocumentTitle(test) {
    const titleUrl = formatUrlForDocumentTitle(test && test.url);
    const titlePrefix = titleUrl ? `Test Performance (${titleUrl})` : 'Test Performance';

    document.title = `${titlePrefix}${getWordPressAdminTitleSuffix()}`;
  }

  function shouldOpenHealthFromUrl() {
    const params = new URLSearchParams(window.location.search || '');
    const hash = window.location.hash ? window.location.hash.replace(/^#/, '') : '';

    return (
      params.get('open_health') === '1' ||
      params.get('health') === '1' ||
      hash === 'abtestkit-test-health' ||
      hash === 'test-health' ||
      hash === 'health'
    );
  }

  function getHealthFromPayload(test) {
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
      summary: 'Core setup looks ready. Keep an eye on the results as traffic builds.',
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

  function HealthStatusPill(props) {
    const health = props.health || {};
    const style = getHealthStatusStyle(health.status);

    return h(
      'span',
      {
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
        health.label || style.label,
      ]
    );
  }

  function isPrimaryGoodHealthCheck(check) {
    const status = String((check && check.status) || '').toLowerCase();

    if (status !== 'good') {
      return false;
    }

    const label = [
      check && check.id,
      check && check.title,
      check && check.description,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();

    return (
      /\b(test|experiment)\b.*\b(running|active|live|started)\b/.test(label) ||
      /\b(running|active|live|started)\b.*\b(test|experiment)\b/.test(label) ||
      /\b(version|variant)\s*a\b.*\b(correct|ready|exists|found|available|set up|linked)\b/.test(label) ||
      /\b(version|variant)\s*b\b.*\b(correct|ready|exists|found|available|set up|linked)\b/.test(label) ||
      /\bversions?\b.*\b(correct|ready|exist|found|available|set up|linked)\b/.test(label) ||
      /\bvariants?\b.*\b(correct|ready|exist|found|available|set up|linked)\b/.test(label) ||
      /\bshadow\b.*\b(correct|ready|exists|found|available|set up|linked)\b/.test(label)
    );
  }

  function isProductShadowGoodCheck(check) {
    const status = String((check && check.status) || '').toLowerCase();

    if (status !== 'good') {
      return false;
    }

    const label = [
      check && check.id,
      check && check.title,
      check && check.description,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();

    return /\bshadow\s+product\b/.test(label) || /\bproduct\b.*\bshadow\b/.test(label);
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
      summary: 'Core setup looks ready.  Keep an eye on the results as traffic builds.',
      checks: filteredChecks,
    };
  }

  function TestHealthCard(props) {
    const health = props.health || getHealthFromPayload(null);
    const checks = Array.isArray(health.checks) ? health.checks : [];
    const compatibilityHelpButton = props.compatibilityHelpButton;
    const cardTone = getHealthStatusStyle(health.status);
    const [internalOpen, setInternalOpen] = useState(false);
    const isControlledOpen = typeof props.isOpen === 'boolean';
    const isOpen = isControlledOpen ? props.isOpen : internalOpen;

    const toggleOpen = () => {
      if (typeof props.onToggle === 'function') {
        props.onToggle(!isOpen);
        return;
      }

      setInternalOpen(!isOpen);
    };

    const statusRank = {
      broken: 0,
      attention: 1,
      good: 2,
      info: 3,
    };

    const sortedChecks = [...checks].sort((a, b) => {
      const aRank = Object.prototype.hasOwnProperty.call(statusRank, a.status) ? statusRank[a.status] : 3;
      const bRank = Object.prototype.hasOwnProperty.call(statusRank, b.status) ? statusRank[b.status] : 3;
      return aRank - bRank;
    });

    const issueChecks = sortedChecks.filter((check) => check.status !== 'good');
    const userFacingGoodChecks = sortedChecks.filter((check) => {
      return check.status === 'good' && !isProductShadowGoodCheck(check);
    });
    const primaryGoodChecks = userFacingGoodChecks.filter(isPrimaryGoodHealthCheck);
    const fallbackGoodChecks = userFacingGoodChecks.slice(0, 2);
    const visibleChecks = issueChecks.length
      ? issueChecks.concat(primaryGoodChecks)
      : (primaryGoodChecks.length ? primaryGoodChecks : fallbackGoodChecks);

    return h(
      'div',
      {
        id: 'abtestkit-test-health',
        style: {
          background: '#fff',
          border: `1px solid ${cardTone.border}`,
          borderRadius: '8px',
          marginBottom: '16px',
          boxShadow: '0 1px 2px rgba(0,0,0,0.03)',
          overflow: 'hidden',
        },
      },
      [
        h(
          'button',
          {
            type: 'button',
            onClick: toggleOpen,
            'aria-expanded': isOpen ? 'true' : 'false',
            'aria-controls': 'abtestkit-test-health-details',
            style: {
              width: '100%',
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'center',
              gap: '16px',
              padding: '14px 20px',
              border: '0',
              background: '#fff',
              cursor: 'pointer',
              textAlign: 'left',
            },
          },
          [
            h(
              'div',
              {
                style: {
                  display: 'flex',
                  alignItems: 'center',
                  gap: '10px',
                  minWidth: 0,
                },
              },
              [
                h(
                  'h2',
                  {
                    style: {
                      margin: 0,
                      fontSize: '16px',
                      lineHeight: '1.3',
                    },
                  },
                  'Test health'
                ),
                h(HealthStatusPill, { health }),
              ]
            ),
            h(
              'span',
              {
                style: {
                  display: 'inline-flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  width: '24px',
                  height: '24px',
                  color: '#50575e',
                  flex: '0 0 auto',
                },
              },
              h(
                'svg',
                {
                  width: 16,
                  height: 16,
                  viewBox: '0 0 20 20',
                  fill: 'none',
                  style: {
                    transform: isOpen ? 'rotate(180deg)' : 'rotate(0deg)',
                    transition: 'transform 0.15s ease',
                  },
                  'aria-hidden': 'true',
                  focusable: 'false',
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

        isOpen &&
          h(
            'div',
            {
              id: 'abtestkit-test-health-details',
              style: {
                padding: '0 20px 18px',
                borderTop: '1px solid #f0f0f1',
              },
            },
            [
              h(
                'p',
                {
                  style: {
                    margin: '14px 0',
                    color: '#50575e',
                    fontSize: '13px',
                    lineHeight: '1.5',
                    maxWidth: '760px',
                  },
                },
                health.summary || 'Core setup looks ready. Keep an eye on the results as traffic builds.'
              ),

              visibleChecks.length
                ? h(
                    'div',
                    {
                      style: {
                        display: 'grid',
                        gridTemplateColumns: 'repeat(2, minmax(0, 1fr))',
                        gap: '10px',
                      },
                    },
                    visibleChecks.map((check, index) => {
                      const checkTone = getHealthStatusStyle(check.status);
                      const symbol = check.status === 'broken'
                        ? '×'
                        : check.status === 'attention'
                        ? '!'
                        : check.status === 'good'
                        ? '✓'
                        : '•';

                      return h(
                        'div',
                        {
                          key: check.id || `health-check-${index}`,
                          style: {
                            display: 'flex',
                            alignItems: 'flex-start',
                            gap: '10px',
                            padding: '12px',
                            border: '1px solid #e5e7eb',
                            borderRadius: '6px',
                            background: '#fbfbfc',
                            minWidth: 0,
                          },
                        },
                        [
                          h(
                            'span',
                            {
                              style: {
                                width: '22px',
                                height: '22px',
                                borderRadius: '999px',
                                display: 'inline-flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                background: checkTone.background,
                                color: checkTone.color,
                                border: `1px solid ${checkTone.border}`,
                                fontSize: '13px',
                                fontWeight: '700',
                                lineHeight: '1',
                                flex: '0 0 auto',
                              },
                            },
                            symbol
                          ),
                          h(
                            'div',
                            {
                              style: {
                                minWidth: 0,
                              },
                            },
                            [
                              h(
                                'div',
                                {
                                  style: {
                                    fontSize: '13px',
                                    fontWeight: '700',
                                    color: '#111827',
                                    lineHeight: '1.35',
                                    marginBottom: check.description ? '3px' : 0,
                                  },
                                },
                                check.title || 'Health check'
                              ),
                              check.description
                                ? h(
                                    'div',
                                    {
                                      style: {
                                        fontSize: '12px',
                                        lineHeight: '1.45',
                                        color: '#6b7280',
                                      },
                                    },
                                    check.description
                                  )
                                : null,
                            ].filter(Boolean)
                          ),
                        ]
                      );
                    })
                  )
                : h(
                    'div',
                    {
                      style: {
                        color: '#6b7280',
                        fontSize: '13px',
                      },
                    },
                    'No health checks are available yet.'
                  ),

              typeof compatibilityHelpButton === 'function'
                ? h(
                    'div',
                    {
                      style: {
                        display: 'flex',
                        justifyContent: 'flex-end',
                        marginTop: '14px',
                      },
                    },
                    compatibilityHelpButton('test_health_card')
                  )
                : null,
            ].filter(Boolean)
          ),
      ].filter(Boolean)
    );
  }
  
  function SimpleLineChart(props) {
    const {
      title,
      labels,
      seriesA,
      seriesB,
      yLabelA,
      yLabelB,
    } = props;

    const [hoverIndex, setHoverIndex] = useState(null);
    const scrollRef = useRef(null);

    const height = 280;
    const padLeft = 44;
    const padRight = 16;
    const padTop = 18;
    const padBottom = 36;
    const visiblePointCount = Math.max(3, Number(props.visiblePointCount || 14));
    const pointGap = Math.max(42, Number(props.pointGap || 56));
    const count = Math.max(labels.length, 1);
    const isScrollable = count > visiblePointCount;
    const width = isScrollable
      ? Math.max(900, padLeft + padRight + (Math.max(count - 1, 1) * pointGap))
      : 900;

    const innerW = width - padLeft - padRight;
    const innerH = height - padTop - padBottom;

    const maxY = Math.max(1, maxValue(seriesA), maxValue(seriesB));

    const pointX = (index) => {
      if (count === 1) return padLeft + innerW / 2;
      return padLeft + (index * innerW) / (count - 1);
    };

    const pointY = (value) => {
      const ratio = value / maxY;
      return padTop + innerH - ratio * innerH;
    };

    const toPath = (series) =>
      (series || [])
        .map((value, index) => `${index === 0 ? 'M' : 'L'} ${pointX(index)} ${pointY(value)}`)
        .join(' ');

    const labelsKey = labels.join('|');

    useEffect(() => {
      const node = scrollRef.current;

      if (!node || !isScrollable) {
        return;
      }

      node.scrollLeft = node.scrollWidth;
    }, [labelsKey, isScrollable, title, width]);

    const gridLines = [0, 0.25, 0.5, 0.75, 1].map((r, i) => {
      const y = padTop + innerH - innerH * r;
      const label = Math.round(maxY * r);
      return h(
        Fragment,
        { key: `grid-${i}` },
        [
          h('line', {
            x1: padLeft,
            y1: y,
            x2: width - padRight,
            y2: y,
            stroke: '#e5e7eb',
            strokeWidth: 1,
          }),
          h(
            'text',
            {
              x: padLeft - 8,
              y: y + 4,
              textAnchor: 'end',
              fontSize: 11,
              fill: '#6b7280',
            },
            String(label)
          ),
        ]
      );
    });

    const xLabels = labels.map((label, i) =>
      h(
        'text',
        {
          key: `xlabel-${i}`,
          x: pointX(i),
          y: height - 12,
          textAnchor: 'middle',
          fontSize: 11,
          fill: '#6b7280',
        },
        label
      )
    );

    const pointsA = (seriesA || []).map((value, i) =>
      h('circle', {
        key: `a-${i}`,
        cx: pointX(i),
        cy: pointY(value),
        r: hoverIndex === i ? 5 : 3,
        fill: '#79caf5',
      })
    );

    const pointsB = (seriesB || []).map((value, i) =>
      h('circle', {
        key: `b-${i}`,
        cx: pointX(i),
        cy: pointY(value),
        r: hoverIndex === i ? 5 : 3,
        fill: '#fc510b',
      })
    );

    const hoverRegions = labels.map((label, i) => {
      const x = pointX(i);
      const prevX = i > 0 ? pointX(i - 1) : padLeft;
      const nextX = i < labels.length - 1 ? pointX(i + 1) : width - padRight;
      const regionX = i === 0 ? padLeft : (prevX + x) / 2;
      const regionNextX = i === labels.length - 1 ? (width - padRight) : (x + nextX) / 2;
      const regionWidth = Math.max(12, regionNextX - regionX);

      return h('rect', {
        key: `hover-${i}`,
        x: regionX,
        y: padTop,
        width: regionWidth,
        height: innerH,
        fill: 'transparent',
        onMouseEnter: () => setHoverIndex(i),
        onMouseMove: () => setHoverIndex(i),
        onMouseLeave: () => setHoverIndex(null),
      });
    });

    const tooltip = hoverIndex !== null
      ? h(
          'div',
          {
            style: {
              position: 'absolute',
              top: '16px',
              left: '16px',
              background: '#fff',
              border: '1px solid #dcdcde',
              borderRadius: '6px',
              boxShadow: '0 4px 14px rgba(0,0,0,0.08)',
              padding: '10px 12px',
              fontSize: '12px',
              lineHeight: '1.4',
              zIndex: 5,
              pointerEvents: 'none',
              minWidth: '100px',
            },
          },
          [
            h(
              'div',
              {
                key: 'label',
                style: {
                  fontWeight: '700',
                  marginBottom: '6px',
                },
              },
              labels[hoverIndex] || ''
            ),
            h(
              'div',
              {
                key: 'a',
                style: {
                  color: '#79caf5',
                  marginBottom: '4px',
                },
              },
              `${yLabelA || 'Version A'}: ${((seriesA || [])[hoverIndex]) || 0}`
            ),
            h(
              'div',
              {
                key: 'b',
                style: {
                  color: '#fc510b',
                },
              },
              `${yLabelB || 'Version B'}: ${((seriesB || [])[hoverIndex]) || 0}`
            ),
          ]
        )
      : null;

    return h(
      'div',
      {
        style: {
          background: '#fff',
          border: '1px solid #dcdcde',
          borderRadius: '8px',
          padding: '16px',
          marginTop: '16px',
          position: 'relative',
        },
      },
      [
        tooltip,
        h(
          'div',
          {
            key: 'header',
            style: {
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'center',
              marginBottom: '10px',
            },
          },
          [
            h(
              'h2',
              {
                style: {
                  margin: 0,
                  fontSize: '16px',
                },
              },
              title
            ),
            h(
              'div',
              {
                style: {
                  display: 'flex',
                  gap: '16px',
                  fontSize: '12px',
                },
              },
              [
                h(
                  'span',
                  { style: { display: 'inline-flex', alignItems: 'center', gap: '6px' } },
                  [
                    h('span', {
                      style: {
                        width: '10px',
                        height: '10px',
                        borderRadius: '999px',
                        background: '#79caf5',
                        display: 'inline-block',
                      },
                    }),
                    yLabelA || 'Version A',
                  ]
                ),
                h(
                  'span',
                  { style: { display: 'inline-flex', alignItems: 'center', gap: '6px' } },
                  [
                    h('span', {
                      style: {
                        width: '10px',
                        height: '10px',
                        borderRadius: '999px',
                        background: '#fc510b',
                        display: 'inline-block',
                      },
                    }),
                    yLabelB || 'Version B',
                  ]
                ),
              ]
            ),
          ]
        ),
        h(
          'div',
          {
            ref: scrollRef,
            style: {
              overflowX: isScrollable ? 'auto' : 'visible',
              overflowY: 'visible',
              paddingBottom: isScrollable ? '8px' : 0,
              WebkitOverflowScrolling: 'touch',
            },
            'aria-label': isScrollable
              ? 'Timeline chart. Scroll horizontally to view earlier periods.'
              : undefined,
          },
          h(
            'svg',
            {
              width: isScrollable ? width : '100%',
              viewBox: `0 0 ${width} ${height}`,
              style: {
                display: 'block',
                overflow: 'visible',
                minWidth: isScrollable ? `${width}px` : 0,
                maxWidth: isScrollable ? 'none' : '100%',
              },
            },
            [
              ...gridLines,
              hoverIndex !== null &&
                h('line', {
                  x1: pointX(hoverIndex),
                  y1: padTop,
                  x2: pointX(hoverIndex),
                  y2: padTop + innerH,
                  stroke: '#c3c4c7',
                  strokeWidth: 1,
                  strokeDasharray: '4 4',
                }),
              h('path', {
                d: toPath(seriesA),
                fill: 'none',
                stroke: '#79caf5',
                strokeWidth: 3,
                strokeLinecap: 'round',
                strokeLinejoin: 'round',
              }),
              h('path', {
                d: toPath(seriesB),
                fill: 'none',
                stroke: '#fc510b',
                strokeWidth: 3,
                strokeLinecap: 'round',
                strokeLinejoin: 'round',
              }),
              ...pointsA,
              ...pointsB,
              ...xLabels,
              ...hoverRegions,
            ]
          )
        ),
      ]
    );
  }

  function PerformanceApp() {
    const [range, setRange] = useState('day');
    const [visibleRange, setVisibleRange] = useState('day');
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [payload, setPayload] = useState(null);
    const [isEditingTitle, setIsEditingTitle] = useState(false);
    const [draftTitle, setDraftTitle] = useState('');
    const [openHelp, setOpenHelp] = useState(null);
    const [isActionsOpen, setIsActionsOpen] = useState(false);
    const [rowsPerPage, setRowsPerPage] = useState(10);
    const [currentPage, setCurrentPage] = useState(1);
    const [showHttpNotice, setShowHttpNotice] = useState(true);
    const [showCompatibilityHelp, setShowCompatibilityHelp] = useState(false);
    const [compatibilityContext, setCompatibilityContext] = useState('performance_page');
    const [compatibilityMessage, setCompatibilityMessage] = useState('');
    const [compatibilitySubmitting, setCompatibilitySubmitting] = useState(false);
    const [compatibilityStatus, setCompatibilityStatus] = useState(null);
    const [isHealthOpen, setIsHealthOpen] = useState(false);

    const getTestIdFromUrl = () => {
      const params = new URLSearchParams(window.location.search || '');
      const queryId = params.get('test_id') || '';
      const hashId = window.location.hash ? window.location.hash.replace(/^#/, '') : '';
      const localizedId = (abtestkitTestPerformance && abtestkitTestPerformance.testId) || '';
      const resolvedId = queryId || hashId || localizedId;

      if (!queryId && hashId && resolvedId && window.history && window.history.replaceState) {
        try {
          const url = new URL(window.location.href);
          url.searchParams.set('test_id', resolvedId);
          url.hash = '';
          window.history.replaceState(null, '', url.toString());
        } catch (_) {}
      }

      return resolvedId;
    };

    const testId = getTestIdFromUrl();

    const loadData = (selectedRange) => {
      setLoading(true);
      setError(null);

      apiFetch({
        url:
          abtestkitTestPerformance.rest.replace(/\/$/, '') +
          '/pt/performance',
        method: 'POST',
        headers: {
          'X-WP-Nonce': abtestkitTestPerformance.nonce,
          'Content-Type': 'application/json',
        },
        data: {
          test_id: testId,
          range: selectedRange,
        },
      })
        .then((data) => {
          if (!data || !data.ok || !data.test) {
            throw new Error('Unable to load performance data.');
          }
          setVisibleRange(selectedRange);
          setPayload(data.test);
          setCurrentPage(1);
          setLoading(false);
        })
        .catch((err) => {
          console.error('abtestkit performance error', err);
          setError('Failed to load performance data.');
          setLoading(false);
        });
    };

    useEffect(() => {
      if (!testId) {
        setError('Missing test ID.');
        setLoading(false);
        return;
      }

      loadData(range);
    }, [testId]);

    const changeRange = (nextRange) => {
      setRange(nextRange);
      setCurrentPage(1);
      loadData(nextRange);
    };

    useEffect(() => {
      setDraftTitle((payload && payload.title) ? payload.title : '');
    }, [payload && payload.title]);

    useEffect(() => {
      if (!payload) {
        return;
      }

      setPerformanceDocumentTitle(payload);
    }, [payload && payload.url]);

    useEffect(() => {
      if (!payload || !shouldOpenHealthFromUrl()) {
        return;
      }

      setIsHealthOpen(true);

      window.setTimeout(() => {
        const healthCard = document.getElementById('abtestkit-test-health');

        if (healthCard && typeof healthCard.scrollIntoView === 'function') {
          healthCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      }, 80);
    }, [payload && payload.id]);

    useEffect(() => {
      const handleDocumentClick = () => {
        setOpenHelp(null);
        setIsActionsOpen(false);
      };

      document.addEventListener('click', handleDocumentClick);
      return () => document.removeEventListener('click', handleDocumentClick);
    }, []);

    const helpTip = (id, content, side = 'right') =>
    h(
      'span',
      {
        style: {
          position: 'relative',
          display: 'inline-flex',
          alignItems: 'center',
          justifyContent: 'center',
          marginLeft: '6px',
          flex: '0 0 auto',
          verticalAlign: 'middle',
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
              minWidth: '16px',
              minHeight: '16px',
              borderRadius: '999px',
              border: '1px solid #c3c4c7',
              color: '#50575e',
              fontSize: '11px',
              fontWeight: '700',
              lineHeight: '1',
              cursor: 'pointer',
              userSelect: 'none',
              background: '#fff',
              boxSizing: 'border-box',
            },
            'aria-label': content,
            title: content,
          },
          '?'
        ),

        openHelp === id &&
          h(
            'div',
            {
              style: {
                position: 'absolute',
                top: '26px',
                left: side === 'left' ? 'auto' : '0',
                right: side === 'left' ? '0' : 'auto',
                transform: 'none',
                zIndex: 100000,
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
            content
          ),
      ]
    );

    const openCompatibilityHelpModal = (context = 'performance_page') => {
      setIsActionsOpen(false);
      setOpenHelp(null);
      setCompatibilityContext(context || 'performance_page');
      setCompatibilityStatus(null);
      setShowCompatibilityHelp(true);
    };

    const closeCompatibilityHelpModal = () => {
      if (compatibilitySubmitting) {
        return;
      }

      setShowCompatibilityHelp(false);
      setCompatibilityStatus(null);
    };

    const compatibilityHelpButton = (context = 'performance_page') =>
      h(
        'button',
        {
          type: 'button',
          onClick: (e) => {
            e.preventDefault();
            e.stopPropagation();
            openCompatibilityHelpModal(context);
          },
          style: {
            border: '0',
            background: 'transparent',
            padding: 0,
            margin: 0,
            color: '#2271b1',
            cursor: 'pointer',
            fontSize: '13px',
            fontWeight: '500',
            lineHeight: '1.4',
            textDecoration: 'underline',
            textUnderlineOffset: '2px',
            whiteSpace: 'nowrap',
          },
        },
        'Something not right?'
      );

    const submitCompatibilityHelp = () => {
      if (!payload || !payload.id || compatibilitySubmitting) {
        return;
      }

      setCompatibilitySubmitting(true);
      setCompatibilityStatus(null);

      apiFetch({
        url:
          abtestkitTestPerformance.rest.replace(/\/$/, '') +
          '/pt/compatibility-help',
        method: 'POST',
        headers: {
          'X-WP-Nonce': abtestkitTestPerformance.nonce,
          'Content-Type': 'application/json',
        },
        data: {
          test_id: payload.id,
          source: compatibilityContext || 'performance_page',
          message: compatibilityMessage,
        },
      })
        .then((res) => {
          if (!res || !res.ok) {
            throw new Error('Unable to send compatibility help request.');
          }

          setCompatibilityStatus('sent');
          setCompatibilityMessage('');
          setCompatibilitySubmitting(false);
        })
        .catch((err) => {
          console.error('abtestkit compatibility help error', err);
          setCompatibilityStatus('error');
          setCompatibilitySubmitting(false);
        });
    };

    const confirmBrokenResume = () => {
      if (!payload || !payload.auto_paused_broken) {
        return true;
      }

      return window.confirm(
        'This test was paused because it was broken.\n\nAre you sure you want to resume the test?\n\nBroken tests will be paused automatically.'
      );
    };

    const submitPtAction = (action, extraFields = {}) => {
      if (!payload || !payload.id) {
        return;
      }

      const form = document.createElement('form');
      form.method = 'POST';
      form.action = abtestkitTestPerformance.adminAction;

      const nonceInput = document.createElement('input');
      nonceInput.type = 'hidden';
      nonceInput.name = '_wpnonce';
      nonceInput.value = abtestkitTestPerformance.adminNonce;
      form.appendChild(nonceInput);

      const doInput = document.createElement('input');
      doInput.type = 'hidden';
      doInput.name = 'do';
      doInput.value = action;
      form.appendChild(doInput);

      const idInput = document.createElement('input');
      idInput.type = 'hidden';
      idInput.name = 'id';
      idInput.value = payload.id;
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

    const startTest = () => {
      setIsActionsOpen(false);

      if (!confirmBrokenResume()) {
        return;
      }

      submitPtAction('start', {
        return_url: window.location.href,
        ...(payload && payload.auto_paused_broken ? { confirm_broken_resume: '1' } : {}),
      });
    };

    const pauseTest = () => {
      setIsActionsOpen(false);
      submitPtAction('pause', {
        return_url: window.location.href,
      });
    };

    const deleteTest = () => {
      setIsActionsOpen(false);

      if (!payload || !payload.id) {
        return;
      }

      if (!window.confirm('Delete this test?')) {
        return;
      }

      const kind = String(payload.kind || '');

      const hasOutcome =
        !!payload.winner || payload.status === 'complete';

      const canOfferDeleteB =
        !hasOutcome &&
        (
          kind === 'page' ||
          kind === 'post' ||
          kind === 'reusable_section' ||
          kind === 'product'
        );

      if (canOfferDeleteB) {
        const deleteMessage =
          kind === 'product'
            ? 'Would you like to delete Version B shadow product?'
            : 'Would you like to delete Version B?';

        const alsoDeleteVariant = window.confirm(deleteMessage);

        if (alsoDeleteVariant) {
          submitPtAction('delete', { trash_b: '1' });
          return;
        }
      }

      submitPtAction('delete');
    };

    const resetTest = () => {
      setIsActionsOpen(false);

      if (!payload || !payload.id) {
        return;
      }

      const confirmed = window.confirm(
        'Are you sure? Resetting the test will set all metrics to zero.'
      );

      if (!confirmed) {
        return;
      }

      apiFetch({
        url:
          abtestkitTestPerformance.rest.replace(/\/$/, '') +
          '/pt/reset-test',
        method: 'POST',
        headers: {
          'X-WP-Nonce': abtestkitTestPerformance.nonce,
          'Content-Type': 'application/json',
        },
        data: {
          test_id: payload.id,
        },
      })
        .then((res) => {
          if (!res || !res.ok) {
            throw new Error('Unable to reset test.');
          }
          loadData(range);
        })
        .catch((err) => {
          console.error('abtestkit reset test error', err);
          window.alert('Failed to reset test metrics.');
        });
    };

    const applyWinner = () => {
      setIsActionsOpen(false);

      if (!payload || !payload.id) {
        return;
      }

      const statsA = (payload.stats && payload.stats.A) ? payload.stats.A : {};
      const statsB = (payload.stats && payload.stats.B) ? payload.stats.B : {};
      const isRevenueTest = payload.goal === 'purchase';

      const impressionsA = Number(statsA.impressions || 0);
      const impressionsB = Number(statsB.impressions || 0);

      const conversionsA = Number(
        isRevenueTest ? (statsA.purchases || 0) : (statsA.clicks || 0)
      );
      const conversionsB = Number(
        isRevenueTest ? (statsB.purchases || 0) : (statsB.clicks || 0)
      );

      const revenueA = Number(statsA.revenue || 0);
      const revenueB = Number(statsB.revenue || 0);

      const metricA = isRevenueTest
        ? (impressionsA > 0 ? revenueA / impressionsA : 0)
        : (impressionsA > 0 ? conversionsA / impressionsA : 0);

      const metricB = isRevenueTest
        ? (impressionsB > 0 ? revenueB / impressionsB : 0)
        : (impressionsB > 0 ? conversionsB / impressionsB : 0);

      const fallbackLeader =
        metricB > metricA ? 'B' :
        metricA > metricB ? 'A' :
        null;

      const leader =
        payload.winner === 'A' || payload.winner === 'B'
          ? payload.winner
          : fallbackLeader;

      if (leader !== 'A' && leader !== 'B') {
        window.alert('There is no clear winner to apply yet.');
        return;
      }

      if (leader === 'B') {
        const confirmed = window.confirm(
          payload.kind === 'custom_css'
            ? 'Apply Version B winner? This will mark the Custom CSS version as the winner and complete the test. It will not edit the page content.'
            : payload.kind === 'product'
            ? 'Apply Version B winner? This will apply Version B changes to the live product and complete the test.'
            : 'Apply Version B winner? This will replace Version A with Version B and complete the test.'
        );

        if (!confirmed) {
          return;
        }

        submitPtAction('apply_b_winner', {
          return_url: window.location.href,
        });
        return;
      }

      const keepAConfirmed = window.confirm(
        'Apply Version A winner? This will keep Version A as the winner and complete the test.'
      );

      if (!keepAConfirmed) {
        return;
      }

      let trashB = '0';

      if (payload.kind === 'page' || payload.kind === 'post' || payload.kind === 'reusable_section') {
        const deleteBConfirmed = window.confirm(
          'Would you like to delete Version B?'
        );
        trashB = deleteBConfirmed ? '1' : '0';
      }

      submitPtAction('keep_a_winner', {
        trash_b: trashB,
        return_url: window.location.href,
      });
    };

    const saveTitle = () => {
      const trimmed = String(draftTitle || '').trim();

      if (!payload || !payload.id) {
        setIsEditingTitle(false);
        return;
      }

      if (!trimmed) {
        setDraftTitle(payload.title || '');
        setIsEditingTitle(false);
        return;
      }

      apiFetch({
        url:
          abtestkitTestPerformance.rest.replace(/\/$/, '') +
          '/pt/update-title',
        method: 'POST',
        headers: {
          'X-WP-Nonce': abtestkitTestPerformance.nonce,
          'Content-Type': 'application/json',
        },
        data: {
          test_id: payload.id,
          title: trimmed,
        },
      })
        .then((res) => {
          if (!res || !res.ok) {
            throw new Error('Unable to update title.');
          }

          setPayload((current) => ({
            ...current,
            title: trimmed,
          }));
          setIsEditingTitle(false);
        })
        .catch((err) => {
          console.error('abtestkit title update error', err);
          window.alert('Failed to update test title.');
          setDraftTitle(payload.title || '');
          setIsEditingTitle(false);
        });
    };

if (loading && !payload) {
      return h(
        'div',
        { className: 'abtestkit-performance-loading' },
        h(Spinner, null),
        ' Loading performance…'
      );
    }

    if (error) {
      return h(
        'div',
        null,
        [
          h('div', { className: 'notice notice-error' }, h('p', null, error)),
          h(
            Button,
            {
              href: abtestkitTestPerformance.dashboardUrl,
              isSecondary: true,
            },
            'Back to Dashboard'
          ),
        ]
      );
    }

    const stats = payload.stats || { A: {}, B: {} };
    const engagement = payload.engagement || { A: {}, B: {} };
    const engCountA = Number((engagement.A && engagement.A.count) || 0);
    const engCountB = Number((engagement.B && engagement.B.count) || 0);
    const avgScrollA = Number((engagement.A && engagement.A.avg_scroll) || 0);
    const avgScrollB = Number((engagement.B && engagement.B.avg_scroll) || 0);
    const avgTimeA = Number((engagement.A && engagement.A.avg_time) || 0);
    const avgTimeB = Number((engagement.B && engagement.B.avg_time) || 0);
    const hasEngagement = engCountA > 0 || engCountB > 0;
    const formatDuration = (value) => {
      const total = Math.max(0, Math.round(Number(value || 0)));
      const mins = Math.floor(total / 60);
      const secs = total % 60;
      return mins > 0 ? `${mins}m ${secs}s` : `${secs}s`;
    };
    const timeline = payload.timeline || [];
    const isRevenueGoal = payload.goal === 'purchase';
    const previewA = payload.preview_a || '';
    const previewB = payload.preview_b || '';
    const httpExcludedCount = Number(payload.http_excluded_count || 0);
    const httpExcludedLast = payload.http_excluded_last || '';
    const isClickGoal = payload.goal === 'clicks' || payload.goal === 'click';
    const isDestinationUrlGoal = payload.goal === 'destination_url';
    const isScrollDepthGoal = payload.goal === 'scroll_depth';
    const isCustomCss = String(payload.kind || '') === 'custom_css';
    const customCss = String(payload.custom_css || '').trim();
    const customCssMarkers = Array.isArray(payload.css_markers) ? payload.css_markers : [];
    const customCssScopeLabel =
      payload.css_scope === 'product'
        ? 'WooCommerce product'
        : payload.css_scope === 'post'
        ? 'Post'
        : payload.css_scope === 'page'
        ? 'Page'
        : '—';
    const scrollDepth = Number(payload.scroll_depth || 50);
    const clickTargets = (isClickGoal || isDestinationUrlGoal) ? getClickTargetsFromPayload(payload) : [];
    const health = getDisplayHealthForVisitorGracePeriod(getHealthFromPayload(payload), payload.started_at);

    const labels = timeline.map((row) => {
      if (visibleRange === 'month') {
        const d = new Date(row.bucket + 'T00:00:00');
        return d.toLocaleDateString(undefined, { month: 'short', year: 'numeric' });
      }

      if (visibleRange === 'week') {
        const d = new Date(row.bucket + 'T00:00:00');
        return d.toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
      }

      const d = new Date(row.bucket + 'T00:00:00');
      return d.toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
    });

    const impressionSeriesA = timeline.map((row) => ((row.A || {}).impressions) || 0);
    const impressionSeriesB = timeline.map((row) => ((row.B || {}).impressions) || 0);

    const conversionKey = isRevenueGoal ? 'purchases' : 'clicks';
    const conversionLabel = isRevenueGoal
      ? 'Orders'
      : isScrollDepthGoal
      ? 'Reached scroll depth'
      : 'Conversions';
    const conversionRateLabel = isRevenueGoal ? 'Order Rate' : 'Conversion Rate';
    const conversionRateHelp = isRevenueGoal
      ? 'Order Rate is the percentage of visitors who placed an order.'
      : isScrollDepthGoal
      ? 'Conversion Rate is the percentage of visitors who reached the selected scroll depth.'
      : 'Conversion Rate is the percentage of visitors who completed the selected conversion goal.';
    const conversionSeriesA = timeline.map((row) => ((row.A || {})[conversionKey]) || 0);
    const conversionSeriesB = timeline.map((row) => ((row.B || {})[conversionKey]) || 0);
    const revenueSeriesA = timeline.map((row) => Number(((row.A || {}).revenue) || 0));
    const revenueSeriesB = timeline.map((row) => Number(((row.B || {}).revenue) || 0));

    const totalImpA = Number((stats.A && stats.A.impressions) || 0);
    const totalImpB = Number((stats.B && stats.B.impressions) || 0);
    const totalConvA = Number((stats.A && stats.A[conversionKey]) || 0);
    const totalConvB = Number((stats.B && stats.B[conversionKey]) || 0);

    const totalRevenueA = Number((stats.A && stats.A.revenue) || 0);
    const totalRevenueB = Number((stats.B && stats.B.revenue) || 0);
    const rpcA = Number((stats.A && stats.A.revenue_per_customer) || 0);
    const rpcB = Number((stats.B && stats.B.revenue_per_customer) || 0);

    const rpvA = totalImpA > 0 ? (totalRevenueA / totalImpA) : 0;
    const rpvB = totalImpB > 0 ? (totalRevenueB / totalImpB) : 0;

    const rateA = totalImpA > 0 ? ((totalConvA / totalImpA) * 100).toFixed(2) : '0.00';
    const rateB = totalImpB > 0 ? ((totalConvB / totalImpB) * 100).toFixed(2) : '0.00';

    const nowTs = Math.floor(Date.now() / 1000);
    const startedAt = Number(payload.started_at || 0);
    const elapsedDaysRaw = startedAt > 0 ? ((nowTs - startedAt) / 86400) : 0;
    const elapsedDays = elapsedDaysRaw > 0 ? Math.max(elapsedDaysRaw, 1) : 0;

    const totalTestVisitors = totalImpA + totalImpB;
    const visitorsPerDay = elapsedDays > 0 ? (totalTestVisitors / elapsedDays) : 0;
    const projectedMonthlyVisitors = visitorsPerDay * 30;

    const upliftPerVisitorA = rpvA - rpvB;
    const upliftPerVisitorB = rpvB - rpvA;

    const predictedMonthlyUpliftA = upliftPerVisitorA > 0
      ? upliftPerVisitorA * projectedMonthlyVisitors
      : 0;

    const predictedMonthlyUpliftB = upliftPerVisitorB > 0
      ? upliftPerVisitorB * projectedMonthlyVisitors
      : 0;

    const leaderMetricA = isRevenueGoal ? rpvA : (totalImpA > 0 ? (totalConvA / totalImpA) : 0);
    const leaderMetricB = isRevenueGoal ? rpvB : (totalImpB > 0 ? (totalConvB / totalImpB) : 0);

    let percentA = 50;
    let percentB = 50;

    if (leaderMetricA > 0 || leaderMetricB > 0) {
      const sum = leaderMetricA + leaderMetricB;
      percentA = Math.round((leaderMetricA / sum) * 100);
      percentB = 100 - percentA;
    }

    const currency = (value) =>
      new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'GBP',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      }).format(Number(value || 0));

    const winningVersion =
      leaderMetricB > leaderMetricA ? 'B' :
      leaderMetricA > leaderMetricB ? 'A' :
      null;

    const showUpliftA = isRevenueGoal && winningVersion === 'A';
    const showUpliftB = isRevenueGoal && winningVersion === 'B';

    const hasEnoughUpliftDataA = totalImpA >= 10;
    const hasEnoughUpliftDataB = totalImpB >= 10;

    const upliftDisplayA = !showUpliftA
      ? '-'
      : !hasEnoughUpliftDataA
      ? 'Calculating'
      : predictedMonthlyUpliftA > 0
      ? currency(predictedMonthlyUpliftA)
      : '-';

    const upliftDisplayB = !showUpliftB
      ? '-'
      : !hasEnoughUpliftDataB
      ? 'Calculating'
      : predictedMonthlyUpliftB > 0
      ? currency(predictedMonthlyUpliftB)
      : '-';

    const totalTimelineRows = timeline.length;
    const totalPages = Math.max(1, Math.ceil(totalTimelineRows / rowsPerPage));
    const safeCurrentPage = Math.min(currentPage, totalPages);
    const startRow = (safeCurrentPage - 1) * rowsPerPage;
    const endRow = startRow + rowsPerPage;
    const reversedTimeline = [...timeline].reverse();
    const paginatedTimeline = reversedTimeline.slice(startRow, endRow);

    const cardStyle = {
      background: '#fff',
      border: '1px solid #dcdcde',
      borderRadius: '8px',
      padding: '14px 16px',
    };

    const statLabelStyle = {
      fontSize: '12px',
      color: '#6b7280',
      marginBottom: '4px',
      display: 'inline-flex',
      alignItems: 'center',
      gap: '0',
      lineHeight: '1.2',
    };

    const statValueStyle = {
      fontSize: '18px',
      fontWeight: '700',
      color: '#111827',
      lineHeight: '1.2',
    };

    const performanceChildren = [
        httpExcludedCount > 0 && showHttpNotice &&
        h(
          'div',
          {
            key: 'http-warning',
            className: 'notice notice-warning',
            style: {
              marginBottom: '16px',
              padding: '12px 14px',
              display: 'flex',
              alignItems: 'flex-start',
              justifyContent: 'space-between',
              gap: '12px',
            },
          },
          [
            h(
              'p',
              {
                style: {
                  margin: 0,
                  fontSize: '13px',
                  lineHeight: '1.5',
                  flex: '1 1 auto',
                },
              },
              `${httpExcludedCount} visit(s) were not tracked because they came through an insecure (HTTP) version of your site. This can lead to inaccurate results.

                      ${httpExcludedLast ? `Last seen: ${httpExcludedLast}. ` : ''}We recommend forcing HTTPS across your site to ensure reliable test data.`
            ),
            h(
              'button',
              {
                type: 'button',
                onClick: () => setShowHttpNotice(false),
                'aria-label': 'Dismiss HTTP warning',
                title: 'Dismiss',
                style: {
                  border: '0',
                  background: 'transparent',
                  padding: 0,
                  margin: 0,
                  width: '18px',
                  height: '18px',
                  display: 'inline-flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  cursor: 'pointer',
                  color: '#50575e',
                  fontSize: '16px',
                  lineHeight: '1',
                  flex: '0 0 auto',
                },
              },
              '×'
            ),
          ]
        ),

        showCompatibilityHelp &&
          h(
            'div',
            {
              key: 'compatibility-help-modal',
              role: 'dialog',
              'aria-modal': 'true',
              'aria-labelledby': 'abtestkit-compatibility-help-title',
              onClick: closeCompatibilityHelpModal,
              style: {
                position: 'fixed',
                inset: 0,
                zIndex: 100000,
                background: 'rgba(0,0,0,0.35)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                padding: '24px',
              },
            },
            h(
              'div',
              {
                onClick: (e) => e.stopPropagation(),
                style: {
                  width: '100%',
                  maxWidth: '560px',
                  background: '#fff',
                  borderRadius: '8px',
                  boxShadow: '0 18px 60px rgba(0,0,0,0.22)',
                  border: '1px solid #dcdcde',
                  overflow: 'hidden',
                },
              },
              [
                h(
                  'div',
                  {
                    style: {
                      padding: '18px 20px',
                      borderBottom: '1px solid #e5e7eb',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'space-between',
                      gap: '12px',
                    },
                  },
                  [
                    h(
                      'h2',
                      {
                        id: 'abtestkit-compatibility-help-title',
                        style: {
                          margin: 0,
                          fontSize: '18px',
                          lineHeight: '1.3',
                        },
                      },
                      'Something not right?'
                    ),
                    h(
                      'button',
                      {
                        type: 'button',
                        onClick: closeCompatibilityHelpModal,
                        disabled: compatibilitySubmitting,
                        'aria-label': 'Close compatibility help request',
                        style: {
                          border: '0',
                          background: 'transparent',
                          padding: 0,
                          margin: 0,
                          width: '24px',
                          height: '24px',
                          display: 'inline-flex',
                          alignItems: 'center',
                          justifyContent: 'center',
                          cursor: compatibilitySubmitting ? 'default' : 'pointer',
                          color: '#50575e',
                          fontSize: '20px',
                          lineHeight: '1',
                        },
                      },
                      '×'
                    ),
                  ]
                ),
                h(
                  'div',
                  {
                    style: {
                      padding: '18px 20px 20px',
                    },
                  },
                  [
                    compatibilityStatus === 'sent'
                      ? h(
                          'div',
                          {
                            className: 'notice notice-success',
                            style: {
                              margin: '0 0 14px',
                              padding: '10px 12px',
                            },
                          },
                          h('p', { style: { margin: 0 } }, 'Thanks — your compatibility help request has been sent.')
                        )
                      : null,
                    compatibilityStatus === 'error'
                      ? h(
                          'div',
                          {
                            className: 'notice notice-error',
                            style: {
                              margin: '0 0 14px',
                              padding: '10px 12px',
                            },
                          },
                          h('p', { style: { margin: 0 } }, 'Sorry, the request could not be sent. Please try again.')
                        )
                      : null,
                      h(
                        'p',
                        {
                          style: {
                            margin: '0 0 10px',
                            fontSize: '13px',
                            lineHeight: '1.5',
                          },
                        },
                        'Noticed something odd in the preview or results? Tell us what looks wrong before you rely on this test.'
                      ),
                      h(
                        'p',
                        {
                          style: {
                            margin: '0 0 14px',
                            fontSize: '13px',
                            lineHeight: '1.5',
                          },
                        },
                        'We’ll include a small diagnostic snapshot of your setup so we can understand what might be causing it.'
                      ),
                      h(
                        'label',
                        {
                          htmlFor: 'abtestkit-compatibility-help-message',
                          style: {
                            display: 'block',
                            marginBottom: '6px',
                            fontWeight: '600',
                            fontSize: '13px',
                          },
                        },
                        'What looks wrong?'
                      ),
                      h('textarea', {
                        id: 'abtestkit-compatibility-help-message',
                        value: compatibilityMessage,
                        onChange: (e) => setCompatibilityMessage(e.target.value),
                        placeholder: 'Example: Version B looks different, clicks aren’t tracking, or the cart total doesn’t match.',
                      rows: 4,
                      disabled: compatibilitySubmitting || compatibilityStatus === 'sent',
                      style: {
                        width: '100%',
                        minHeight: '96px',
                        resize: 'vertical',
                        marginBottom: '10px',
                      },
                    }),
                    h(
                      'p',
                      {
                        style: {
                          margin: '0 0 16px',
                          fontSize: '12px',
                          lineHeight: '1.45',
                          color: '#6b7280',
                        },
                      },
                      'This sends anonymous diagnostic information about your WordPress setup and current test configuration.'
                    ),
                    h(
                      'div',
                      {
                        style: {
                          display: 'flex',
                          justifyContent: 'flex-end',
                          gap: '8px',
                          flexWrap: 'wrap',
                        },
                      },
                      [
                        h(
                          Button,
                          {
                            isSecondary: true,
                            onClick: closeCompatibilityHelpModal,
                            disabled: compatibilitySubmitting,
                          },
                          compatibilityStatus === 'sent' ? 'Close' : 'Cancel'
                        ),
                        compatibilityStatus !== 'sent'
                          ? h(
                              Button,
                              {
                                isPrimary: true,
                                onClick: submitCompatibilityHelp,
                                disabled: compatibilitySubmitting || !payload || !payload.id,
                              },
                              compatibilitySubmitting ? 'Sending…' : 'Send help request'
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
          'div',
          {
            key: 'topbar',
            style: {
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'center',
              gap: '12px',
              marginBottom: '16px',
            },
          },
          [
            h(
              'div',
              null,
              [
              h(
                'div',
                {
                  style: {
                    display: 'flex',
                    alignItems: 'center',
                    gap: '8px',
                    marginBottom: '4px',
                  },
                },
                [
                  isEditingTitle
                    ? h('input', {
                        type: 'text',
                        value: draftTitle,
                        autoFocus: true,
                        onChange: (e) => setDraftTitle(e.target.value),
                        onBlur: saveTitle,
                        onKeyDown: (e) => {
                          if (e.key === 'Enter') {
                            e.preventDefault();
                            saveTitle();
                          }
                          if (e.key === 'Escape') {
                            setDraftTitle(payload.title || '');
                            setIsEditingTitle(false);
                          }
                        },
                        style: {
                          fontSize: '22px',
                          fontWeight: '700',
                          lineHeight: '1.2',
                          padding: '2px 8px',
                          border: '1px solid #8c8f94',
                          borderRadius: '4px',
                          minWidth: '280px',
                          background: '#fff',
                        },
                      })
                    : h(
                        'div',
                        {
                          style: {
                            fontSize: '22px',
                            fontWeight: '700',
                            lineHeight: '1.2',
                          },
                        },
                        payload.title || 'Untitled test'
                      ),

                  h(
                    'button',
                    {
                      type: 'button',
                      onClick: () => {
                        setDraftTitle(payload.title || '');
                        setIsEditingTitle(true);
                      },
                      style: {
                        border: '0',
                        background: 'transparent',
                        padding: 0,
                        margin: 0,
                        width: '18px',
                        height: '18px',
                        display: 'inline-flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        cursor: 'pointer',
                        color: '#2271b1',
                      },
                      'aria-label': 'Edit test title',
                      title: 'Edit test title',
                    },
                    h('span', {
                      className: 'dashicons dashicons-edit',
                      style: {
                        fontSize: '16px',
                        width: '16px',
                        height: '16px',
                      },
                    })
                  ),

                  h(HealthStatusPill, { health }),
                ]
              ),
                h(
                  'div',
                  {
                    style: {
                      display: 'flex',
                      flexDirection: 'column',
                      gap: '4px',
                      fontSize: '13px',
                      color: '#50575e',
                    },
                  },
                  [
                    h(
                      'div',
                      null,
                      `Test ID: ${payload.id}`
                    ),
                    payload.kind === 'reusable_section' && payload.testing_title
                      ? h(
                          'div',
                          null,
                          `Testing: ${payload.testing_title}`
                        )
                      : null,
                    payload.url
                      ? h(
                          'div',
                          {
                            style: {
                              display: 'flex',
                              alignItems: 'center',
                              gap: '6px',
                              flexWrap: 'wrap',
                            },
                          },
                          [
                            h(
                              'span',
                              {
                                style: {
                                  color: '#50575e',
                                },
                              },
                              'Test URL:'
                            ),
                            h(
                              'a',
                              {
                                href: payload.url,
                                target: '_blank',
                                rel: 'noopener noreferrer',
                                style: {
                                  color: '#2271b1',
                                  textDecoration: 'none',
                                  display: 'inline-flex',
                                  alignItems: 'center',
                                  gap: '4px',
                                  minWidth: 0,
                                },
                                title: 'Open test URL',
                              },
                              [
                                h(
                                  'span',
                                  {
                                    style: {
                                      overflow: 'hidden',
                                      textOverflow: 'ellipsis',
                                      whiteSpace: 'nowrap',
                                      maxWidth: '520px',
                                      display: 'inline-block',
                                      verticalAlign: 'bottom',
                                    },
                                  },
                                  payload.url
                                ),
                                h('span', {
                                  className: 'dashicons dashicons-external',
                                  style: {
                                    fontSize: '14px',
                                    width: '14px',
                                    height: '14px',
                                  },
                                }),
                              ]
                            ),
                          ]
                        )
                      : null,
                  ]
                ),
              ]
            ),
            h(
              'div',
              {
                style: {
                  display: 'flex',
                  alignItems: 'center',
                  gap: '8px',
                  flexWrap: 'wrap',
                  justifyContent: 'flex-end',
                },
              },
              [
                h(
                  'div',
                  {
                    style: {
                      position: 'relative',
                    },
                    onClick: (e) => e.stopPropagation(),
                  },
                  [
                  h(
                    Button,
                    {
                      isSecondary: true,
                      onClick: (e) => {
                        e.stopPropagation();
                        setIsActionsOpen(!isActionsOpen);
                      },
                      style: {
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
                            width: 14,
                            height: 25,
                            viewBox: '0 0 20 16',
                            fill: 'none',
                            style: {
                              transform: isActionsOpen ? 'rotate(180deg)' : 'rotate(0deg)',
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
                    isActionsOpen &&
                      h(
                        'div',
                        {
                          style: {
                            position: 'absolute',
                            top: 'calc(100% + 8px)',
                            right: 0,
                            minWidth: '180px',
                            background: '#fff',
                            border: '1px solid #dcdcde',
                            borderRadius: '6px',
                            boxShadow: '0 8px 24px rgba(0,0,0,0.12)',
                            padding: '6px 0',
                            zIndex: 1000,
                          },
                        },
                        [
                          h(
                            'button',
                            {
                              type: 'button',
                              onClick: () => {
                                setIsActionsOpen(false);

                                const healthCard = document.getElementById('abtestkit-test-health');
                                if (healthCard && typeof healthCard.scrollIntoView === 'function') {
                                  healthCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
                                }
                              },
                              style: {
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                gap: '12px',
                                width: '100%',
                                textAlign: 'left',
                                padding: '10px 14px',
                                border: '0',
                                background: '#fff',
                                cursor: 'pointer',
                                fontSize: '13px',
                              },
                            },
                            [
                              h('span', null, 'Health check'),
                              h(HealthStatusPill, { health }),
                            ]
                          ),

                          payload && payload.status !== 'complete'
                            ? (
                                payload.status === 'running'
                                  ? h(
                                      'button',
                                      {
                                        type: 'button',
                                        onClick: pauseTest,
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
                                      'Pause'
                                    )
                                  : h(
                                      'button',
                                      {
                                        type: 'button',
                                        onClick: startTest,
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
                                      'Resume'
                                    )
                              )
                            : null,

                          payload && payload.status !== 'complete'
                            ? h(
                                'button',
                                {
                                  type: 'button',
                                  onClick: applyWinner,
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
                                'Apply winner'
                              )
                            : null,

                          payload && payload.status !== 'complete'
                            ? h(
                                'button',
                                {
                                  type: 'button',
                                  onClick: resetTest,
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
                              )
                            : null,

                          h(
                            'button',
                            {
                              type: 'button',
                              onClick: deleteTest,
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
                ),

                h(
                  Button,
                  {
                    href: abtestkitTestPerformance.dashboardUrl,
                    isSecondary: true,
                  },
                  'Back to Dashboard'
                ),
              ].filter(Boolean)
            ),
          ]
        ),

        h(
          'div',
          {
            key: 'meta-grid',
            style: {
              display: 'grid',
              gridTemplateColumns: 'repeat(3, minmax(0, 1fr))',
              gap: '12px',
              marginBottom: '16px',
            },
          },
          [
            h('div', { style: cardStyle }, [
              h('div', { style: statLabelStyle }, 'Type'),
              h('div', { style: statValueStyle }, payload.kind_label || (payload.kind === 'reusable_section' ? 'Reusable Section' : (payload.kind || '—'))),
            ]),
            h('div', { style: cardStyle }, [
              h('div', { style: statLabelStyle }, 'Status'),
              h('div', { style: statValueStyle }, payload.status || '—'),
            ]),
            h('div', { style: cardStyle }, [
              h('div', { style: statLabelStyle }, 'Goal'),
              h('div', { style: statValueStyle }, formatGoal(payload.goal)),
            ]),
            isScrollDepthGoal &&
              h('div', { style: { ...cardStyle, gridColumn: '1 / -1' } }, [
                h('div', { style: statLabelStyle }, 'Scroll depth threshold'),
                h('div', { style: statValueStyle }, `${scrollDepth}% of the page`),
                h(
                  'div',
                  {
                    style: {
                      marginTop: '6px',
                      color: '#6b7280',
                      fontSize: '12px',
                      lineHeight: '1.45',
                    },
                  },
                  'A conversion is recorded once per visitor session when they reach this depth on their assigned version.'
                ),
              ]),
            isCustomCss &&
              h('div', { style: { ...cardStyle, gridColumn: '1 / -1' } }, [
                h('div', { style: statLabelStyle }, 'Custom CSS setup'),
                h(
                  'div',
                  {
                    style: {
                      display: 'grid',
                      gridTemplateColumns: 'repeat(3, minmax(0, 1fr))',
                      gap: '12px',
                      marginTop: '8px',
                    },
                  },
                  [
                    h('div', null, [
                      h('div', { style: { fontWeight: 700, marginBottom: '2px' } }, 'Location type'),
                      h('div', null, customCssScopeLabel),
                    ]),
                    h('div', null, [
                      h('div', { style: { fontWeight: 700, marginBottom: '2px' } }, 'Version A'),
                      h('div', null, 'Original page'),
                    ]),
                    h('div', null, [
                      h('div', { style: { fontWeight: 700, marginBottom: '2px' } }, 'Version B'),
                      h('div', null, 'Custom CSS applied'),
                    ]),
                  ]
                ),
                h(
                  'details',
                  { style: { marginTop: '12px' }, open: false },
                  [
                    h(
                      'summary',
                      {
                        style: {
                          cursor: 'pointer',
                          color: '#2271b1',
                          fontSize: '13px',
                          fontWeight: '600',
                        },
                      },
                      customCss ? 'Show saved Version B CSS' : 'No Version B CSS saved'
                    ),
                    customCss
                      ? h(
                          'pre',
                          {
                            style: {
                              margin: '10px 0 0',
                              padding: '12px',
                              background: '#f6f7f7',
                              border: '1px solid #dcdcde',
                              borderRadius: '6px',
                              whiteSpace: 'pre-wrap',
                              wordBreak: 'break-word',
                              fontSize: '12px',
                              lineHeight: '1.5',
                              maxHeight: '360px',
                              overflow: 'auto',
                            },
                          },
                          customCss
                        )
                      : null,
                  ].filter(Boolean)
                ),
                h(
                  'div',
                  { style: { marginTop: '12px' } },
                  [
                    h('div', { style: { fontWeight: 700, marginBottom: '6px' } }, 'B-only markers'),
                    customCssMarkers.length
                      ? h(
                          'div',
                          {
                            style: {
                              display: 'flex',
                              flexDirection: 'column',
                              gap: '8px',
                            },
                          },
                          customCssMarkers.map((marker, index) =>
                            h(
                              'div',
                              {
                                key: `${marker.class_name || 'marker'}-${index}`,
                                style: {
                                  background: '#f6f7f7',
                                  border: '1px solid #e5e7eb',
                                  borderRadius: '6px',
                                  padding: '10px 12px',
                                },
                              },
                              [
                                h('div', { style: { fontWeight: 700 } }, marker.label || `Marker ${index + 1}`),
                                h('div', { style: { fontSize: '12px', color: '#6b7280', marginTop: '3px' } }, marker.selector || '—'),
                                h('code', { style: { display: 'inline-block', marginTop: '6px' } }, marker.class_name ? `.${marker.class_name}` : '—'),
                              ]
                            )
                          )
                        )
                      : h(
                          'div',
                          {
                            style: {
                              color: '#6b7280',
                              fontSize: '13px',
                            },
                          },
                          'No B-only markers were saved. This is fine if the CSS targets existing selectors directly.'
                        ),
                  ]
                ),
              ]),
            (isClickGoal || isDestinationUrlGoal) &&
              h('div', { style: { ...cardStyle, gridColumn: '1 / -1' } }, [
                h(
                  'div',
                  {
                    style: {
                      display: 'flex',
                      justifyContent: 'space-between',
                      alignItems: 'center',
                      gap: '12px',
                      marginBottom: '8px',
                    },
                  },
                  [
                    h(
                      'div',
                      { style: statLabelStyle },
                      isDestinationUrlGoal
                        ? (clickTargets.length === 1 ? 'Destination URL' : 'Destination URLs')
                        : (clickTargets.length === 1 ? 'Click Target' : 'Click Targets')
                    ),
                    clickTargets.length
                      ? h(
                          'span',
                          {
                            style: {
                              fontSize: '12px',
                              color: '#6b7280',
                              fontWeight: '600',
                            },
                          },
                          isDestinationUrlGoal
                            ? `${clickTargets.length} saved`
                            : `${clickTargets.length} selected`
                        )
                      : null,
                  ].filter(Boolean)
                ),
                clickTargets.length
                  ? h(
                      'div',
                      {
                        style: {
                          display: 'flex',
                          flexDirection: 'column',
                          gap: '8px',
                          minWidth: 0,
                        },
                      },
                      clickTargets.map((target, index) => {
                        const targetDisplay = isDestinationUrlGoal
                          ? {
                              title: String(target || '').replace(/\*$/, '') || `Destination URL ${index + 1}`,
                              description: String(target || '').endsWith('*')
                                ? 'Visitors who land on a URL starting with this destination count as conversions.'
                                : 'Visitors who land on this exact destination count as conversions.',
                              technicalLabel: 'Tracked URL',
                            }
                          : formatClickTargetForDisplay(target, index);

                        return h(
                          'div',
                          {
                            key: `click-target-${index}`,
                            style: {
                              background: '#f6f7f7',
                              border: '1px solid #e5e7eb',
                              borderRadius: '6px',
                              padding: '10px 12px',
                              minWidth: 0,
                            },
                          },
                          [
                            h(
                              'div',
                              {
                                style: {
                                  display: 'flex',
                                  alignItems: 'center',
                                  gap: '8px',
                                  marginBottom: '4px',
                                  minWidth: 0,
                                },
                              },
                              [
                                h(
                                  'span',
                                  {
                                    style: {
                                      display: 'inline-flex',
                                      alignItems: 'center',
                                      justifyContent: 'center',
                                      width: '22px',
                                      height: '22px',
                                      borderRadius: '999px',
                                      background: '#e5f1fa',
                                      color: '#2271b1',
                                      fontSize: '12px',
                                      fontWeight: '700',
                                      flex: '0 0 auto',
                                    },
                                  },
                                  String(index + 1)
                                ),
                                h(
                                  'strong',
                                  {
                                    style: {
                                      fontSize: '14px',
                                      color: '#111827',
                                      lineHeight: '1.3',
                                      minWidth: 0,
                                    },
                                  },
                                  targetDisplay.title
                                ),
                              ]
                            ),
                            h(
                              'div',
                              {
                                style: {
                                  fontSize: '12px',
                                  color: '#6b7280',
                                  lineHeight: '1.45',
                                  marginLeft: '30px',
                                },
                              },
                              targetDisplay.description
                            ),
                            !isDestinationUrlGoal &&
                              h(
                                'details',
                                {
                                  style: {
                                    marginTop: '8px',
                                    marginLeft: '30px',
                                  },
                                },
                                [
                                  h(
                                    'summary',
                                    {
                                      style: {
                                        cursor: 'pointer',
                                        color: '#2271b1',
                                        fontSize: '12px',
                                        fontWeight: '600',
                                      },
                                    },
                                    `Show ${targetDisplay.technicalLabel.toLowerCase()}`
                                  ),
                                  h(
                                    'code',
                                    {
                                      style: {
                                        display: 'block',
                                        marginTop: '6px',
                                        whiteSpace: 'normal',
                                        wordBreak: 'break-word',
                                        lineHeight: '1.45',
                                        fontSize: '12px',
                                      },
                                    },
                                    target
                                  ),
                                ]
                              ),
                          ]
                        );
                      })
                    )
                  : h(
                      'div',
                      {
                        style: {
                          fontSize: '13px',
                          color: '#6b7280',
                          lineHeight: '1.45',
                        },
                      },
                      isDestinationUrlGoal
                        ? 'No destination URLs found for this test.'
                        : 'No click targets found for this test.'
                    ),
              ]),
            h('div', { style: cardStyle }, [
              h('div', { style: statLabelStyle }, 'Decision Mode'),
              h('div', { style: statValueStyle }, formatDecision(payload.decision_rule, payload.decision_mode)),
            ]),
            h('div', { style: cardStyle }, [
              h('div', { style: statLabelStyle }, 'Min. Impressions'),
              h('div', { style: statValueStyle }, String(payload.min_impressions || 0)),
            ]),
            h('div', { style: cardStyle }, [
              h('div', { style: statLabelStyle }, 'Min. Conversions'),
              h('div', { style: statValueStyle }, String(payload.min_conversions || 0)),
            ]),
            h('div', { style: cardStyle }, [
              h('div', { style: statLabelStyle }, 'Started'),
              h('div', { style: { fontSize: '14px', fontWeight: '600' } }, formatDate(payload.started_at)),
            ]),
            h('div', { style: cardStyle }, [
              h('div', { style: statLabelStyle }, 'Finished'),
              h('div', { style: { fontSize: '14px', fontWeight: '600' } }, formatDate(payload.finished_at)),
            ]),
            h('div', { style: cardStyle }, [
              h('div', { style: statLabelStyle }, 'Winner'),
              h('div', { style: statValueStyle }, payload.winner ? String(payload.winner).toUpperCase() : '—'),
            ]),
          ]
        ),
        h(TestHealthCard, {
          key: 'test-health-card',
          health,
          compatibilityHelpButton,
          isOpen: isHealthOpen,
          onToggle: setIsHealthOpen,
        }),
        h(
          'div',
          {
            key: 'winner-summary',
            style: {
              background: '#fff',
              border: '1px solid #dcdcde',
              borderRadius: '8px',
              padding: '18px 20px',
              marginBottom: '16px'
            }
          },
          [
            h(
              'div',
              {
                style: {
                  maxWidth: '760px',
                  margin: '0 auto',
                }
              },
              [
                h(
                  'div',
                  {
                    style: {
                      textAlign: 'center',
                      marginBottom: '14px'
                    }
                  },
                  [
                    h(
                      'div',
                      {
                        style: {
                          fontSize: '14px',
                          color: '#6b7280',
                          marginBottom: '6px',
                          display: 'inline-flex',
                          alignItems: 'center',
                          justifyContent: 'center',
                          gap: '4px'
                        }
                      },
                      [
                        'Current test leader',
                        isRevenueGoal &&
                          helpTip(
                            'leader-rpv',
                            'For revenue tests, the current leader is based on Revenue per Visitor (RPV).',
                          )
                      ].filter(Boolean)
                    ),
                    h(
                      'div',
                      {
                        style: {
                          fontSize: '28px',
                          fontWeight: '700',
                          lineHeight: '1.15',
                          color: '#111827'
                        }
                      },
                      percentB > percentA
                        ? `Version B is winning (+${percentB - percentA}%)`
                        : percentA > percentB
                        ? `Version A is winning (+${percentA - percentB}%)`
                        : 'Versions are currently tied'
                    )
                  ]
                ),

                h(
                  'div',
                  {
                    style: {
                      display: 'grid',
                      gridTemplateColumns: '1fr 1fr',
                      columnGap: '24px',
                      alignItems: 'center',
                      marginBottom: '8px',
                      fontSize: '13px',
                      fontWeight: '600',
                      color: '#374151'
                    }
                  },
                  [
                    h(
                      'div',
                      {
                        style: {
                          textAlign: 'center',
                        }
                      },
                      `Version A — ${percentA}%`
                    ),
                    h(
                      'div',
                      {
                        style: {
                          textAlign: 'center',
                        }
                      },
                      `Version B — ${percentB}%`
                    )
                  ]
                ),

                h(
                  'div',
                  {
                    style: {
                      width: '100%',
                      height: '14px',
                      background: '#e5e7eb',
                      borderRadius: '999px',
                      overflow: 'hidden'
                    }
                  },
                  [
                    h('div', {
                      style: {
                        width: percentA + '%',
                        height: '100%',
                        background: '#79caf5',
                        float: 'left'
                      }
                    }),
                    h('div', {
                      style: {
                        width: percentB + '%',
                        height: '100%',
                        background: '#fc510b',
                        float: 'left'
                      }
                    })
                  ]
                )
              ]
            )
          ]
        ),
        h(
          'div',
          {
            key: 'stats-grid',
            style: {
              display: 'grid',
              gridTemplateColumns: 'repeat(2, minmax(0, 1fr))',
              gap: '12px',
              marginBottom: '16px',
            },
          },
          [
          h('div', { style: cardStyle }, [
            h(
              'div',
              {
                style: {
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'space-between',
                  gap: '12px',
                  marginTop: 0,
                  marginBottom: '14px',
                },
              },
              [
                h(
                  'h2',
                  {
                    style: {
                      margin: 0,
                      fontSize: '16px',
                    },
                  },
                  'Version A'
                ),
                previewA
                  ? h(
                      'a',
                      {
                        href: previewA,
                        target: '_blank',
                        rel: 'noopener noreferrer',
                        'aria-label': 'Preview Version A',
                        style: {
                          minHeight: '36px',
                          border: '1px solid #3858e9',
                          borderRadius: '4px',
                          background: '#3858e9',
                          color: '#fff',
                          textDecoration: 'none',
                          display: 'inline-flex',
                          alignItems: 'center',
                          justifyContent: 'center',
                          gap: '6px',
                          padding: '0 14px',
                          fontSize: '13px',
                          fontWeight: '500',
                          lineHeight: '1',
                          whiteSpace: 'nowrap',
                          flex: '0 0 auto',
                          boxSizing: 'border-box',
                          boxShadow: 'none',
                        },
                        title: 'Preview Version A',
                      },
                      [
                        'Preview',
                        h('span', {
                          className: 'dashicons dashicons-external',
                          style: {
                            fontSize: '16px',
                            width: '16px',
                            height: '16px',
                          },
                        }),
                      ]
                    )
                  : null,
              ]
            ),
            h(
              'div',
              {
                style: {
                  display: 'grid',
                  gridTemplateColumns: 'repeat(3, minmax(0, 1fr))',
                  gap: '16px 18px',
                },
              },
              [
                h('div', null, [
                  h(
                    'div',
                    { style: statLabelStyle },
                    [
                      'Impressions',
                      helpTip(
                        'impressions-a',
                        'Not including bots.'
                      ),
                    ]
                  ),
                  h('div', { style: statValueStyle }, String(totalImpA)),
                ]),
                h('div', null, [
                  h('div', { style: statLabelStyle }, conversionLabel),
                  h('div', { style: statValueStyle }, String(totalConvA)),
                ]),
                h('div', null, [
                  h(
                    'div',
                    { style: statLabelStyle },
                    [
                      conversionRateLabel,
                      helpTip(
                        'order-rate',
                        conversionRateHelp
                      ),
                    ]
                  ),
                  h('div', { style: statValueStyle }, `${rateA}%`),
                ]),
                isRevenueGoal &&
                  h('div', null, [
                    h(
                      'div',
                      { style: statLabelStyle },
                      [
                        'Revenue',
                        helpTip(
                          'revenue',
                          'Revenue is the total order value generated by this version.'
                        ),
                      ]
                    ),
                    h('div', { style: statValueStyle }, currency(totalRevenueA)),
                  ]),
                isRevenueGoal &&
                  h('div', null, [
                    h(
                      'div',
                      { style: statLabelStyle },
                      [
                        'RPC',
                        helpTip(
                          'rpc',
                          'Revenue per Customer is total revenue divided by the number of customers who ordered.'
                        ),
                      ]
                    ),
                    h('div', { style: statValueStyle }, currency(rpcA)),
                  ]),
                isRevenueGoal &&
                  h('div', null, [
                    h(
                      'div',
                      { style: statLabelStyle },
                      [
                        'RPV',
                        helpTip(
                          'rpv',
                          'Revenue per Visitor is total revenue divided by total visitors. This is usually the best overall e-commerce test metric.'
                        ),
                      ]
                    ),
                    h('div', { style: statValueStyle }, currency(rpvA)),
                  ]),
                isRevenueGoal &&
                  h('div', null, [
                    h(
                      'div',
                      { style: statLabelStyle },
                      [
                        'Pred. Monthly Uplift',
                        helpTip(
                          'predicted-uplift-a',
                          'Estimates extra monthly revenue based on Revenue per Visitor (RPV) and the test’s observed visitor pace.'
                        ),
                      ]
                    ),
                    h(
                      'div',
                      { style: statValueStyle },
                      upliftDisplayA
                    ),
                  ]),
                hasEngagement &&
                  h('div', null, [
                    h(
                      'div',
                      { style: statLabelStyle },
                      [
                        'Avg. Scroll Depth',
                        helpTip(
                          'avg-scroll-a',
                          'Average maximum scroll depth reached by visitors on this version.'
                        ),
                      ]
                    ),
                    h('div', { style: statValueStyle }, `${Math.round(avgScrollA)}%`),
                  ]),
                hasEngagement &&
                  h('div', null, [
                    h(
                      'div',
                      { style: statLabelStyle },
                      [
                        'Avg. Time on Page',
                        helpTip(
                          'avg-time-a',
                          'Average active time visitors spent on this version (tab visible).'
                        ),
                      ]
                    ),
                    h('div', { style: statValueStyle }, formatDuration(avgTimeA)),
                  ]),
              ].filter(Boolean)
            ),
            h(
              'div',
              {
                style: {
                  display: 'flex',
                  justifyContent: 'flex-end',
                  marginTop: '16px',
                },
              },
              compatibilityHelpButton('version_a_performance_tile')
            ),
          ]),
          h('div', { style: cardStyle }, [
            h(
              'div',
              {
                style: {
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'space-between',
                  gap: '12px',
                  marginTop: 0,
                  marginBottom: '14px',
                },
              },
              [
                h(
                  'h2',
                  {
                    style: {
                      margin: 0,
                      fontSize: '16px',
                    },
                  },
                  isCustomCss ? 'Version B' : 'Version B'
                ),
                previewB
                  ? h(
                      'a',
                      {
                        href: previewB,
                        target: '_blank',
                        rel: 'noopener noreferrer',
                        'aria-label': 'Preview Version B',
                        style: {
                          minHeight: '36px',
                          border: '1px solid #3858e9',
                          borderRadius: '4px',
                          background: '#3858e9',
                          color: '#fff',
                          textDecoration: 'none',
                          display: 'inline-flex',
                          alignItems: 'center',
                          justifyContent: 'center',
                          gap: '6px',
                          padding: '0 14px',
                          fontSize: '13px',
                          fontWeight: '500',
                          lineHeight: '1',
                          whiteSpace: 'nowrap',
                          flex: '0 0 auto',
                          boxSizing: 'border-box',
                          boxShadow: 'none',
                        },
                        title: 'Preview Version B',
                      },
                      [
                        'Preview',
                        h('span', {
                          className: 'dashicons dashicons-external',
                          style: {
                            fontSize: '16px',
                            width: '16px',
                            height: '16px',
                          },
                        }),
                      ]
                    )
                  : null,
              ]
            ),
            h(
              'div',
              {
                style: {
                  display: 'grid',
                  gridTemplateColumns: 'repeat(3, minmax(0, 1fr))',
                  gap: '16px 18px',
                },
              },
              [
                h('div', null, [
                  h('div', { style: statLabelStyle }, 'Impressions'),
                  h('div', { style: statValueStyle }, String(totalImpB)),
                ]),
                h('div', null, [
                  h('div', { style: statLabelStyle }, conversionLabel),
                  h('div', { style: statValueStyle }, String(totalConvB)),
                ]),
                h('div', null, [
                  h(
                    'div',
                    { style: statLabelStyle },
                    [
                      conversionRateLabel,
                      helpTip(
                        'order-rate-b',
                        conversionRateHelp,
                        'left'
                      )
                    ]
                  ),
                  h('div', { style: statValueStyle }, `${rateB}%`),
                ]),
                isRevenueGoal &&
                  h('div', null, [
                    h(
                      'div',
                      { style: statLabelStyle },
                      [
                        'Revenue',
                        helpTip(
                          'revenue-b',
                          'Revenue is the total order value generated by this version.',
                          'left'
                        ),
                      ]
                    ),
                    h('div', { style: statValueStyle }, currency(totalRevenueB)),
                  ]),
                isRevenueGoal &&
                  h('div', null, [
                    h(
                      'div',
                      { style: statLabelStyle },
                      [
                        'RPC',
                        helpTip(
                          'rpc-b',
                          'Revenue per Customer is total revenue divided by the number of customers who ordered.',
                          'left'
                        ),
                      ]
                    ),
                    h('div', { style: statValueStyle }, currency(rpcB)),
                  ]),
                isRevenueGoal &&
                  h('div', null, [
                    h(
                      'div',
                      { style: statLabelStyle },
                      [
                        'RPV',
                        helpTip(
                          'rpv-b',
                          'Revenue per Visitor is total revenue divided by total visitors. This is usually the best overall e-commerce test metric.',
                          'left'
                        ),
                      ]
                    ),
                    h('div', { style: statValueStyle }, currency(rpvB)),
                  ]),
                isRevenueGoal &&
                  h('div', null, [
                    h(
                      'div',
                      { style: statLabelStyle },
                      [
                        'Pred. Monthly Uplift',
                        helpTip(
                          'predicted-uplift-b',
                          'Estimates extra monthly revenue based on Revenue per Visitor (RPV) and the test’s observed visitor pace.',
                          'left'
                        ),
                      ]
                    ),
                    h(
                      'div',
                      { style: statValueStyle },
                      upliftDisplayB
                    ),
                  ]),
                hasEngagement &&
                  h('div', null, [
                    h(
                      'div',
                      { style: statLabelStyle },
                      [
                        'Avg. Scroll Depth',
                        helpTip(
                          'avg-scroll-b',
                          'Average maximum scroll depth reached by visitors on this version.',
                          'left'
                        ),
                      ]
                    ),
                    h('div', { style: statValueStyle }, `${Math.round(avgScrollB)}%`),
                  ]),
                hasEngagement &&
                  h('div', null, [
                    h(
                      'div',
                      { style: statLabelStyle },
                      [
                        'Avg. Time on Page',
                        helpTip(
                          'avg-time-b',
                          'Average active time visitors spent on this version (tab visible).',
                          'left'
                        ),
                      ]
                    ),
                    h('div', { style: statValueStyle }, formatDuration(avgTimeB)),
                  ]),
              ].filter(Boolean)
            ),
            h(
              'div',
              {
                style: {
                  display: 'flex',
                  justifyContent: 'flex-end',
                  marginTop: '16px',
                },
              },
              compatibilityHelpButton('version_b_performance_tile')
            ),
          ]),
          ]
        ),

        h(
          'div',
          {
            key: 'range-switcher',
            style: {
              display: 'flex',
              alignItems: 'center',
              gap: '8px',
              marginBottom: '12px',
            },
          },
          [
            h('strong', null, 'Timeline:'),
            h(
              Button,
              {
                isPrimary: range === 'day',
                isSecondary: range !== 'day',
                disabled: loading,
                onClick: () => changeRange('day'),
              },
              'Day'
            ),
            h(
              Button,
              {
                isPrimary: range === 'week',
                isSecondary: range !== 'week',
                disabled: loading,
                onClick: () => changeRange('week'),
              },
              'Week'
            ),
            h(
              Button,
              {
                isPrimary: range === 'month',
                isSecondary: range !== 'month',
                disabled: loading,
                onClick: () => changeRange('month'),
              },
              'Month'
            ),
            loading && payload
              ? h(
                  'span',
                  {
                    style: {
                      marginLeft: '4px',
                      fontSize: '12px',
                      color: '#6b7280',
                    },
                  },
                  'Updating timeline…'
                )
              : null,
          ]
        ),

        timeline.length === 0
          ? h(
              'div',
              {
                style: {
                  ...cardStyle,
                  textAlign: 'center',
                  color: '#6b7280',
                },
              },
              'No timeline data has been recorded for this test yet.'
            )
          : h(
              Fragment,
              null,
              [
                h(SimpleLineChart, {
                  title: 'Impressions over time',
                  labels,
                  seriesA: impressionSeriesA,
                  seriesB: impressionSeriesB,
                  yLabelA: 'A impressions',
                  yLabelB: 'B impressions',
                }),
                h(SimpleLineChart, {
                  title: isRevenueGoal
                    ? 'Orders over time'
                    : isScrollDepthGoal
                    ? 'Scroll depth conversions over time'
                    : 'Conversions over time',
                  labels,
                  seriesA: conversionSeriesA,
                  seriesB: conversionSeriesB,
                  yLabelA: isRevenueGoal
                    ? 'A orders'
                    : isScrollDepthGoal
                    ? 'A reached scroll depth'
                    : 'A conversions',
                  yLabelB: isRevenueGoal
                    ? 'B orders'
                    : isScrollDepthGoal
                    ? 'B reached scroll depth'
                    : 'B conversions',
                }),
                isRevenueGoal &&
                  h(SimpleLineChart, {
                    title: 'Revenue over time',
                    labels,
                    seriesA: revenueSeriesA,
                    seriesB: revenueSeriesB,
                    yLabelA: 'A revenue',
                    yLabelB: 'B revenue',
                  }),
              ]
            ),

        timeline.length > 0 &&
          h(
            'div',
            {
              key: 'table-wrap',
              style: {
                marginTop: '16px',
                background: '#fff',
                border: '1px solid #dcdcde',
                borderRadius: '8px',
                overflow: 'hidden',
              },
            },
            [
              h(
                'div',
                {
                  style: {
                    padding: '12px 16px',
                    borderBottom: '1px solid #e5e7eb',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    gap: '12px',
                    flexWrap: 'wrap',
                  },
                },
                [
                  h(
                    'div',
                    {
                      style: {
                        fontWeight: '700',
                      },
                    },
                    'Timeline Breakdown'
                  ),
                  h(
                    'div',
                    {
                      style: {
                        display: 'flex',
                        alignItems: 'center',
                        gap: '10px',
                        flexWrap: 'wrap',
                        fontSize: '13px',
                      },
                    },
                    [
                      h(
                        'label',
                        {
                          htmlFor: 'abtestkit-rows-per-page',
                          style: {
                            color: '#50575e',
                            fontWeight: '600',
                          },
                        },
                        'Rows per page'
                      ),
                      h(
                        'select',
                        {
                          id: 'abtestkit-rows-per-page',
                          value: String(rowsPerPage),
                          onChange: (e) => {
                            setRowsPerPage(Number(e.target.value) || 10);
                            setCurrentPage(1);
                          },
                          style: {
                            minWidth: '72px',
                          },
                        },
                        [
                          h('option', { value: '10' }, '10'),
                          h('option', { value: '25' }, '25'),
                          h('option', { value: '50' }, '50'),
                        ]
                      ),
                    ]
                  ),
                ]
              ),
              h(
                Fragment,
                null,
                [
                  h(
                    'table',
                    {
                      className: 'widefat striped',
                      style: { border: 0, margin: 0 },
                    },
                    [
                      h(
                        'thead',
                        null,
                        h(
                          'tr',
                          null,
                          [
                            h('th', null, 'Period'),
                            h('th', null, 'A Impressions'),
                            h('th', null, 'B Impressions'),
                            h('th', null, isRevenueGoal ? 'A Orders' : isScrollDepthGoal ? 'A Scroll Depth' : 'A Conversions'),
                            h('th', null, isRevenueGoal ? 'B Orders' : isScrollDepthGoal ? 'B Scroll Depth' : 'B Conversions'),
                            isRevenueGoal && h('th', null, 'A Revenue'),
                            isRevenueGoal && h('th', null, 'B Revenue'),
                          ].filter(Boolean)
                        )
                      ),
                      h(
                        'tbody',
                        null,
                        paginatedTimeline.map((row) =>
                          h(
                            'tr',
                            { key: row.bucket },
                            [
                              h('td', null, formatBucketDate(row.bucket)),
                              h('td', null, String(((row.A || {}).impressions) || 0)),
                              h('td', null, String(((row.B || {}).impressions) || 0)),
                              h('td', null, String(((row.A || {})[conversionKey]) || 0)),
                              h('td', null, String(((row.B || {})[conversionKey]) || 0)),
                              isRevenueGoal && h('td', null, currency(((row.A || {}).revenue) || 0)),
                              isRevenueGoal && h('td', null, currency(((row.B || {}).revenue) || 0)),
                            ].filter(Boolean)
                          )
                        )
                      ),
                    ]
                  ),
                  h(
                    'div',
                    {
                      style: {
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        gap: '12px',
                        padding: '12px 16px',
                        borderTop: '1px solid #e5e7eb',
                        flexWrap: 'wrap',
                      },
                    },
                    [
                      h(
                        'div',
                        {
                          style: {
                            fontSize: '13px',
                            color: '#50575e',
                          },
                        },
                        `Showing ${totalTimelineRows === 0 ? 0 : startRow + 1}–${Math.min(endRow, totalTimelineRows)} of ${totalTimelineRows}`
                      ),
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
                            Button,
                            {
                              isSecondary: true,
                              disabled: safeCurrentPage <= 1,
                              onClick: () => setCurrentPage(Math.max(1, safeCurrentPage - 1)),
                            },
                            'Previous'
                          ),
                          h(
                            'span',
                            {
                              style: {
                                fontSize: '13px',
                                color: '#50575e',
                                minWidth: '72px',
                                textAlign: 'center',
                              },
                            },
                            `Page ${safeCurrentPage} of ${totalPages}`
                          ),
                          h(
                            Button,
                            {
                              isSecondary: true,
                              disabled: safeCurrentPage >= totalPages,
                              onClick: () => setCurrentPage(Math.min(totalPages, safeCurrentPage + 1)),
                            },
                            'Next'
                          ),
                        ]
                      ),
                    ]
                  ),
                ]
              ),
            ]
          ),
      ];

    const resultCardKeys = ['winner-summary', 'stats-grid'];
    const resultCards = [];
    const remainingChildren = [];

    performanceChildren.forEach((child) => {
      if (child && resultCardKeys.indexOf(child.key) !== -1) {
        resultCards.push(child);
        return;
      }

      remainingChildren.push(child);
    });

    const topbarIndex = remainingChildren.findIndex(
      (child) => child && child.key === 'topbar'
    );
    const insertIndex = topbarIndex >= 0 ? topbarIndex + 1 : 0;

    remainingChildren.splice(insertIndex, 0, ...resultCards);

    return h(
      'div',
      { className: 'abtestkit-test-performance-app' },
      remainingChildren
    );
  }

  document.addEventListener('DOMContentLoaded', function () {
    var root = document.getElementById('abtestkit-test-performance-root');
    if (!root) return;
    wp.element.render(h(PerformanceApp), root);
  });
})();