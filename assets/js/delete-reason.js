(function () {
  if (!window.ABTESTKIT_DELETE_REASON) return;

  var cfg = window.ABTESTKIT_DELETE_REASON;
  if (!cfg.pluginBase || !cfg.rest || !cfg.nonce) return;

  var state = {
    step: 1,
    reason: '',
    detailTag: '',
    area: '',
    detail: ''
  };

  function escapeHtml(str) {
    return String(str || '').replace(/[&<>"']/g, function (m) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[m];
    });
  }

  function getQueryParam(href, key) {
    if (!href) return '';
    var re = new RegExp('[?&]' + key + '=([^&#]*)');
    var m = re.exec(href);
    return m ? m[1] : '';
  }

  function decodeParam(v) {
    v = String(v || '').replace(/\+/g, ' ');
    try { return decodeURIComponent(v); } catch (e) { return v; }
  }

  function isOurDeactivateLink(link) {
    if (!link) return false;
    var href = link.getAttribute('href') || '';
    var pluginParam = decodeParam(getQueryParam(href, 'plugin'));
    if (!pluginParam) return false;
    return pluginParam === cfg.pluginBase;
  }

  function findDeactivateLinkFromEventTarget(target) {
    if (!target) return null;

    if (target.nodeType === 3 && target.parentElement) target = target.parentElement;
    if (!target.closest) return null;

    var link = target.closest('a');
    if (!link) {
      var wrap = target.closest('span.deactivate');
      if (wrap) link = wrap.querySelector('a');
    }
    if (!link) return null;

    var href = link.getAttribute('href') || '';
    if (href.indexOf('action=deactivate') === -1) return null;

    return isOurDeactivateLink(link) ? link : null;
  }

  function reasons() {
    return Array.isArray(cfg.reasons) ? cfg.reasons : [];
  }

  function areas() {
    return Array.isArray(cfg.areas) ? cfg.areas : [];
  }

  function findReason(value) {
    var list = reasons();
    for (var i = 0; i < list.length; i++) {
      if (list[i] && list[i].value === value) return list[i];
    }
    return null;
  }

  function selectedReason() {
    return findReason(state.reason) || {};
  }

  function injectStyles() {
    if (document.getElementById('abtestkit-delete-reason-styles')) return;

    var css = document.createElement('style');
    css.id = 'abtestkit-delete-reason-styles';
    css.textContent =
      '#abtestkit-delete-reason-modal{position:fixed;inset:0;z-index:100000;display:flex;align-items:center;justify-content:center;padding:16px;box-sizing:border-box;}' +
      '#abtestkit-delete-reason-modal .abtestkit-delete-reason-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.45);}' +
      '#abtestkit-delete-reason-modal .abtestkit-delete-reason-box{position:relative;background:#fff;width:100%;max-width:720px;max-height:calc(100vh - 32px);overflow:auto;border-radius:12px;box-shadow:0 18px 55px rgba(0,0,0,.26);box-sizing:border-box;}' +
      '#abtestkit-delete-reason-modal .abtk-head{padding:20px 22px 14px;border-bottom:1px solid #f0f0f1;}' +
      '#abtestkit-delete-reason-modal h2{margin:0 0 7px;font-size:21px;line-height:1.25;}' +
      '#abtestkit-delete-reason-modal .abtk-intro{margin:0;color:#646970;font-size:13px;line-height:1.45;}' +
      '#abtestkit-delete-reason-modal .abtk-body{padding:18px 22px 6px;}' +
      '#abtestkit-delete-reason-modal .abtk-stepbar{display:flex;align-items:center;gap:8px;margin:0 0 14px;}' +
      '#abtestkit-delete-reason-modal .abtk-step{display:inline-flex;align-items:center;gap:6px;font-size:12px;color:#646970;}' +
      '#abtestkit-delete-reason-modal .abtk-dot{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:999px;background:#f0f0f1;color:#50575e;font-size:12px;font-weight:700;}' +
      '#abtestkit-delete-reason-modal .abtk-step.is-active{color:#1d2327;font-weight:600;}' +
      '#abtestkit-delete-reason-modal .abtk-step.is-active .abtk-dot{background:#2271b1;color:#fff;}' +
      '#abtestkit-delete-reason-modal .abtk-section-title{margin:0 0 10px;font-size:15px;font-weight:700;color:#1d2327;}' +
      '#abtestkit-delete-reason-modal .abtk-reason-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;}' +
      '#abtestkit-delete-reason-modal .abtk-reason-card{appearance:none;background:#fff;text-align:left;border:1px solid #dcdcde;border-radius:10px;padding:12px;cursor:pointer;box-shadow:none;}' +
      '#abtestkit-delete-reason-modal .abtk-reason-card:hover{border-color:#2271b1;background:#f6f7f7;}' +
      '#abtestkit-delete-reason-modal .abtk-reason-card.is-selected{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1;background:#f0f6fc;}' +
      '#abtestkit-delete-reason-modal .abtk-reason-title{display:block;margin:0 0 4px;font-weight:700;font-size:13px;color:#1d2327;}' +
      '#abtestkit-delete-reason-modal .abtk-reason-desc{display:block;color:#646970;font-size:12px;line-height:1.35;}' +
      '#abtestkit-delete-reason-modal .abtk-followup-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:0 0 16px;}' +
      '#abtestkit-delete-reason-modal .abtk-followup-card{appearance:none;background:#fff;text-align:left;border:1px solid #dcdcde;border-radius:10px;padding:12px;cursor:pointer;box-shadow:none;}' +
      '#abtestkit-delete-reason-modal .abtk-followup-card:hover{border-color:#2271b1;background:#f6f7f7;}' +
      '#abtestkit-delete-reason-modal .abtk-followup-card.is-selected{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1;background:#f0f6fc;}' +
      '#abtestkit-delete-reason-modal .abtk-followup-title{display:block;margin:0;font-weight:700;font-size:13px;color:#1d2327;}' +
      '#abtestkit-delete-reason-modal .abtk-chips{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 14px;}' +
      '#abtestkit-delete-reason-modal .abtk-chip{appearance:none;background:#fff;border:1px solid #dcdcde;border-radius:999px;padding:7px 10px;font-size:12px;line-height:1.2;cursor:pointer;}' +
      '#abtestkit-delete-reason-modal .abtk-chip:hover{border-color:#2271b1;}' +
      '#abtestkit-delete-reason-modal .abtk-chip.is-selected{background:#2271b1;border-color:#2271b1;color:#fff;}' +
      '#abtestkit-delete-reason-modal .abtk-field{margin-top:2px;}' +
      '#abtestkit-delete-reason-modal .abtk-help{margin:0 0 7px;color:#646970;font-size:12px;line-height:1.4;}' +
      '#abtestkit-delete-reason-modal textarea.abtk-input{width:100%;max-width:100%;min-height:118px;padding:10px 12px;border:1px solid #8c8f94;border-radius:8px;box-sizing:border-box;resize:vertical;font-size:13px;line-height:1.45;}' +
      '#abtestkit-delete-reason-modal .abtk-snapshot{margin:10px 0 0;color:#646970;font-size:11px;line-height:1.4;}' +
      '#abtestkit-delete-reason-modal .abtk-error{color:#b32d2e;display:none;margin:10px 0 0;font-size:13px;}' +
      '#abtestkit-delete-reason-modal .abtk-footer{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:14px 22px 18px;border-top:1px solid #f0f0f1;}' +
      '#abtestkit-delete-reason-modal .abtk-footer-left,.abtestkit-delete-reason-modal .abtk-footer-right{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}' +
      '#abtestkit-delete-reason-modal .abtk-skip{font-size:12px;text-decoration:underline;color:#646970;}' +
      '#abtestkit-delete-reason-modal .abtk-skip:hover{color:#135e96;}' +
      '#abtestkit-delete-reason-modal #abtestkit-delete-reason-confirm[disabled]{opacity:.55;cursor:not-allowed;}' +
      '@media (max-width:720px){' +
        '#abtestkit-delete-reason-modal{align-items:flex-start;padding:10px;}' +
        '#abtestkit-delete-reason-modal .abtk-head{padding:16px 16px 12px;}' +
        '#abtestkit-delete-reason-modal .abtk-body{padding:14px 16px 4px;}' +
        '#abtestkit-delete-reason-modal .abtk-reason-grid{grid-template-columns:1fr;}' +
        '#abtestkit-delete-reason-modal .abtk-followup-grid{grid-template-columns:1fr;}' +
        '#abtestkit-delete-reason-modal .abtk-footer{padding:12px 16px 16px;align-items:stretch;flex-direction:column;}' +
        '#abtestkit-delete-reason-modal .abtk-footer-left,.abtestkit-delete-reason-modal .abtk-footer-right{width:100%;justify-content:space-between;}' +
        '#abtestkit-delete-reason-modal .abtk-footer-right .button{flex:1;}' +
      '}';
    document.head.appendChild(css);
  }

  function stepbarHtml() {
    return '';
  }

  function reasonCardsHtml() {
    var list = reasons();
    var html = '';

    for (var i = 0; i < list.length; i++) {
      var item = list[i] || {};
      var selected = item.value === state.reason ? ' is-selected' : '';
      html += '' +
        '<button type="button" class="abtk-reason-card' + selected + '" data-abtk-reason="' + escapeHtml(item.value) + '">' +
          '<span class="abtk-reason-title">' + escapeHtml(item.title) + '</span>' +
          '<span class="abtk-reason-desc">' + escapeHtml(item.desc) + '</span>' +
        '</button>';
    }

    return html;
  }

  function chipsHtml(items, selectedValue, attr) {
    var html = '';
    items = Array.isArray(items) ? items : [];

    for (var i = 0; i < items.length; i++) {
      var item = items[i] || {};
      var selected = item.value === selectedValue ? ' is-selected' : '';
      html += '<button type="button" class="abtk-chip' + selected + '" ' + attr + '="' + escapeHtml(item.value) + '">' + escapeHtml(item.label) + '</button>';
    }

    return html;
  }

  function tagCardsHtml(items, selectedValue, attr) {
    var html = '';
    items = Array.isArray(items) ? items : [];

    for (var i = 0; i < items.length; i++) {
      var item = items[i] || {};
      var selected = item.value === selectedValue ? ' is-selected' : '';
      html += '' +
        '<button type="button" class="abtk-followup-card' + selected + '" ' + attr + '="' + escapeHtml(item.value) + '">' +
          '<span class="abtk-followup-title">' + escapeHtml(item.label) + '</span>' +
        '</button>';
    }

    return html;
  }

  function syncDetailFromModal(modal) {
    var textarea = modal ? modal.querySelector('#abtestkit-delete-reason-detail') : null;
    if (textarea) state.detail = textarea.value || '';
  }

  function getWizardSnapshot() {
    try {
      var raw = window.localStorage.getItem('abtk_pt_wizard_session');
      var saved = raw ? JSON.parse(raw) : null;

      if (!saved || typeof saved !== 'object' || Array.isArray(saved)) {
        return {};
      }

      var lastSeen = Number(saved.last_seen || 0);
      var ageSeconds = lastSeen > 0
        ? Math.max(0, Math.round((Date.now() - lastSeen) / 1000))
        : 0;

      // Keep this list aligned with the server allow-list. Never include
      // titles, IDs, URLs, selectors, custom code/content or error messages.
      return {
        ui: saved.ui,
        step: saved.step,
        step_index: saved.step_index,
        furthest_step: saved.furthest_step,
        furthest_step_index: saved.furthest_step_index,
        ms: saved.ms,
        age_seconds: ageSeconds,
        completed: saved.completed,
        result: saved.result,
        kind: saved.kind,
        custom_code_type: saved.custom_code_type,
        scope: saved.scope,
        b_mode: saved.b_mode,
        has_control: saved.has_control,
        has_variant: saved.has_variant,
        has_temp_variant: saved.has_temp_variant,
        edited_variant: saved.edited_variant,
        seo_safe_existing_b: saved.seo_safe_existing_b,
        goal: saved.goal,
        conversion_chosen: saved.conversion_chosen,
        click_scope: saved.click_scope,
        links_count: saved.links_count,
        scroll_depth: saved.scroll_depth,
        decision_mode: saved.decision_mode,
        decision_rule: saved.decision_rule,
        custom_css_length: saved.custom_css_length,
        css_marker_count: saved.css_marker_count,
        html_change_count: saved.html_change_count,
        has_error: saved.has_error,
        product_title_changed: saved.product_title_changed,
        product_price_changed: saved.product_price_changed,
        product_sale_price_changed: saved.product_sale_price_changed,
        product_short_description_changed: saved.product_short_description_changed,
        product_long_description_changed: saved.product_long_description_changed,
        product_image_changed: saved.product_image_changed,
        product_gallery_changed: saved.product_gallery_changed
      };
    } catch (e) {
      return {};
    }
  }

  function buildModal() {
    var overlay = document.createElement('div');
    overlay.id = 'abtestkit-delete-reason-modal';
    overlay.innerHTML =
      '<div class="abtestkit-delete-reason-backdrop"></div>' +
      '<div class="abtestkit-delete-reason-box" role="dialog" aria-modal="true" aria-labelledby="abtestkit-delete-reason-title">' +
        '<div class="abtk-head">' +
          '<h2 id="abtestkit-delete-reason-title">' + escapeHtml(cfg.title) + '</h2>' +
          '<p class="abtk-intro">' + escapeHtml(cfg.intro) + '</p>' +
        '</div>' +
        '<div class="abtk-body" id="abtestkit-delete-reason-body"></div>' +
        '<p id="abtestkit-delete-reason-error" class="abtk-error"></p>' +
        '<div class="abtk-footer">' +
          '<div class="abtk-footer-left">' +
            '<button type="button" class="button" id="abtestkit-delete-reason-back" style="display:none;">' + escapeHtml(cfg.backText) + '</button>' +
            '<a href="#" id="abtestkit-delete-reason-skip" class="abtk-skip">' + escapeHtml(cfg.skipText) + '</a>' +
          '</div>' +
          '<div class="abtk-footer-right">' +
            '<button type="button" class="button" id="abtestkit-delete-reason-cancel">' + escapeHtml(cfg.cancelText) + '</button>' +
            '<button type="button" class="button button-primary" id="abtestkit-delete-reason-confirm" disabled>' + escapeHtml(cfg.continueText) + '</button>' +
          '</div>' +
        '</div>' +
      '</div>';

    document.body.appendChild(overlay);
    return overlay;
  }

  function closeModal(modal) {
    if (modal && modal.parentNode) modal.parentNode.removeChild(modal);
  }

  function hideError(modal) {
    var error = modal.querySelector('#abtestkit-delete-reason-error');
    if (!error) return;
    error.style.display = 'none';
    error.textContent = '';
  }

  function showError(modal, msg) {
    var error = modal.querySelector('#abtestkit-delete-reason-error');
    if (!error) return;
    error.style.display = 'block';
    error.textContent = msg || cfg.requiredText;
  }

  function render(modal) {
    var body = modal.querySelector('#abtestkit-delete-reason-body');
    var back = modal.querySelector('#abtestkit-delete-reason-back');
    var cancel = modal.querySelector('#abtestkit-delete-reason-cancel');
    var confirm = modal.querySelector('#abtestkit-delete-reason-confirm');

    hideError(modal);

    if (state.step === 1) {
      body.innerHTML =
        stepbarHtml() +
        '<p class="abtk-section-title">' + escapeHtml(cfg.stepOneLabel) + '</p>' +
        '<div class="abtk-reason-grid">' + reasonCardsHtml() + '</div>';

      back.style.display = 'none';
      cancel.style.display = '';
      confirm.textContent = cfg.continueText || 'Continue';
      confirm.disabled = !state.reason;
      return;
    }

    var reason = selectedReason();
    var tags = Array.isArray(reason.tags) ? reason.tags : [];
    var followupLabel = reason.followupLabel || cfg.stepTwoLabel || '';
    var detailLabel = cfg.detailLabel || cfg.stepTwoLabel || '';
    var followupHtml = '';

    if (tags.length) {
      followupHtml =
        '<p class="abtk-section-title">' + escapeHtml(followupLabel) + '</p>' +
        '<div class="abtk-followup-grid">' + tagCardsHtml(tags, state.detailTag, 'data-abtk-detail-tag') + '</div>';
    } else {
      followupHtml =
        '<p class="abtk-section-title">' + escapeHtml(followupLabel || detailLabel) + '</p>';
    }

    body.innerHTML =
      stepbarHtml() +
      followupHtml +
      '<div class="abtk-field">' +
        (tags.length ? '<p class="abtk-section-title">' + escapeHtml(detailLabel) + '</p>' : '') +
        '<p class="abtk-help">' + escapeHtml(cfg.detailHelp) + '</p>' +
        '<textarea id="abtestkit-delete-reason-detail" class="abtk-input" maxlength="1000" placeholder="' + escapeHtml(cfg.placeholder) + '">' + escapeHtml(state.detail) + '</textarea>' +
      '</div>' +
      '<div class="abtk-snapshot">' + escapeHtml(cfg.snapshotText) + '</div>';

    back.style.display = '';
    cancel.style.display = 'none';
    confirm.textContent = cfg.confirmText || 'Send feedback and deactivate';
    confirm.disabled = false;

    var textarea = body.querySelector('#abtestkit-delete-reason-detail');
    if (textarea) textarea.focus();
  }

  // SAFE: posts to same-origin WP REST route with nonce.
  function postReason() {
    return fetch(cfg.rest, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': cfg.nonce
      },
      body: JSON.stringify({
        reason: state.reason,
        detail_tag: state.detailTag,
        area: state.area,
        detail: state.detail,
        wizard: getWizardSnapshot()
      })
    }).catch(function () {});
  }

  function openModalForLink(link) {
    if (document.getElementById('abtestkit-delete-reason-modal')) return;

    state = { step: 1, reason: '', detailTag: '', area: '', detail: '' };

    var modal = buildModal();
    var cancel = modal.querySelector('#abtestkit-delete-reason-cancel');
    var skip = modal.querySelector('#abtestkit-delete-reason-skip');
    var confirm = modal.querySelector('#abtestkit-delete-reason-confirm');
    var back = modal.querySelector('#abtestkit-delete-reason-back');

    modal.addEventListener('click', function (e) {
      var reasonButton = e.target.closest ? e.target.closest('[data-abtk-reason]') : null;
      if (reasonButton && modal.contains(reasonButton)) {
        state.reason = reasonButton.getAttribute('data-abtk-reason') || '';
        state.detailTag = '';
        state.step = 2;
        render(modal);
        return;
      }

      var tagButton = e.target.closest ? e.target.closest('[data-abtk-detail-tag]') : null;
      if (tagButton && modal.contains(tagButton)) {
        syncDetailFromModal(modal);
        state.detailTag = tagButton.getAttribute('data-abtk-detail-tag') || '';
        render(modal);
        return;
      }

      var areaButton = e.target.closest ? e.target.closest('[data-abtk-area]') : null;
      if (areaButton && modal.contains(areaButton)) {
        syncDetailFromModal(modal);
        state.area = areaButton.getAttribute('data-abtk-area') || '';
        render(modal);
      }
    });

    cancel.addEventListener('click', function () {
      closeModal(modal);
    });

    back.addEventListener('click', function () {
      syncDetailFromModal(modal);
      state.step = 1;
      render(modal);
    });

    skip.addEventListener('click', function (e) {
      e.preventDefault();
      window.location.href = link.href;
    });

    confirm.addEventListener('click', function () {
      if (state.step === 1) {
        if (!state.reason) {
          showError(modal, cfg.requiredText);
          return;
        }
        state.step = 2;
        render(modal);
        return;
      }

      var textarea = modal.querySelector('#abtestkit-delete-reason-detail');
      state.detail = textarea ? (textarea.value || '').trim() : '';

      if (!state.reason) {
        state.step = 1;
        render(modal);
        showError(modal, cfg.requiredText);
        return;
      }

      confirm.disabled = true;
      back.disabled = true;
      cancel.disabled = true;
      skip.style.pointerEvents = 'none';
      skip.style.opacity = '0.6';

      postReason().then(function () {
        window.location.href = link.href;
      });
    });

    document.addEventListener('keydown', function escHandler(e) {
      if (e.key === 'Escape') {
        document.removeEventListener('keydown', escHandler);
        closeModal(modal);
      }
    });

    render(modal);
  }

  function init() {
    injectStyles();

    document.addEventListener('click', function (e) {
      var link = findDeactivateLinkFromEventTarget(e.target);
      if (!link) return;

      e.preventDefault();
      e.stopPropagation();
      if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();

      openModalForLink(link);
    }, true);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
