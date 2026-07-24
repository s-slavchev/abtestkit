jQuery(function ($) {
    if (typeof ABTESTKIT_RUNNING_MAP !== 'object') return;

    function isRunningTest(postId) {
        return !!ABTESTKIT_RUNNING_MAP[postId];
    }

    function getPostIdFromHref(href) {
        if (!href) return 0;
        const m = href.match(/post=([0-9]+)/);
        return m ? parseInt(m[1], 10) : 0;
    }

    function confirmGuard(type) {
        let msg = '';

        if (type === 'trash') {
            msg =
                'This is part of a running A/B Test.\n\n' +
                'Trashing it may break the test and invalidate your results.\n\n' +
                'Are you sure you want to move it to Trash?';
        } else if (type === 'quickedit') {
            msg =
                'This is part of a running A/B Test.\n\n' +
                'Quick Edit changes can invalidate your results.\n\n' +
                'Are you sure you want to open Quick Edit?';
        } else {
            // edit
            msg =
                'This is part of a running A/B Test.\n\n' +
                'Editing it may invalidate your results.\n\n' +
                'Are you sure you want to edit it?';
        }

        return window.confirm(msg);
    }

    /**
     * 1) Guard Edit / Trash clicks.
     *    (Covers row actions + title click if it includes action=edit)
     */
    $('#the-list').on('click', 'a', function (e) {
        const $a = $(this);
        const href = $a.attr('href') || '';

        // Quick edit handled separately below.
        if ($a.hasClass('editinline') || href.indexOf('action=inline') !== -1) {
            return;
        }

        const postId = getPostIdFromHref(href);
        if (!postId || !isRunningTest(postId)) return;

        const isTrash = href.indexOf('action=trash') !== -1;
        const isEdit =
            href.indexOf('action=edit') !== -1 ||
            // Some themes/plugins use post.php?post=ID without action=edit in admin lists (rare)
            href.indexOf('post.php') !== -1;

        if (!isTrash && !isEdit) return;

        const ok = confirmGuard(isTrash ? 'trash' : 'edit');
        if (!ok) {
            e.preventDefault();
            e.stopImmediatePropagation();
            return false;
        }
    });

    /**
     * HARD GUARANTEE: intercept WordPress quick edit opener.
     * This catches every Quick Edit open, regardless of what was clicked.
     */
    if (window.inlineEditPost && typeof window.inlineEditPost.edit === 'function') {
        const _abt_original_inline_edit = window.inlineEditPost.edit;

        window.inlineEditPost.edit = function (id) {
            // WP sometimes passes "123" or "post-123"
            let postId = 0;

            if (typeof id === 'number') {
                postId = id;
            } else if (typeof id === 'string') {
                const m = id.match(/([0-9]+)/);
                postId = m ? parseInt(m[1], 10) : 0;
            } else if (id && typeof id === 'object') {
                // Sometimes it passes the clicked element
                const $tr = jQuery(id).closest('tr');
                const trId = ($tr.attr('id') || '');
                const m = trId.match(/^post-([0-9]+)$/);
                postId = m ? parseInt(m[1], 10) : 0;
            }

            if (postId && isRunningTest(postId)) {
                const ok = confirmGuard('quickedit');
                if (!ok) {
                    // Ensure editor does not open
                    if (typeof window.inlineEditPost.revert === 'function') {
                        window.inlineEditPost.revert();
                    }
                    return;
                }
            }

            return _abt_original_inline_edit.apply(this, arguments);
        };
    }
});