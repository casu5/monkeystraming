(function () {
    if (window.__keyboardScrollFixReady) return;
    window.__keyboardScrollFixReady = true;

    function canScroll(el) {
        if (!el) return false;
        return el.scrollHeight > el.clientHeight + 4;
    }

    function nearestScrollable(start) {
        var el = start && start.nodeType === 1 ? start : null;

        while (el && el !== document.body && el !== document.documentElement) {
            var style = window.getComputedStyle(el);
            var overflowY = style.overflowY;

            if ((overflowY === 'auto' || overflowY === 'scroll') && canScroll(el)) {
                return el;
            }

            el = el.parentElement;
        }

        return document.scrollingElement || document.documentElement || document.body;
    }

    function forceUnlockScroll() {
        document.documentElement.style.overflowY = 'auto';
        document.body.style.overflowY = 'auto';
        document.body.style.position = '';
        document.body.style.height = '';
        document.body.style.width = '';
    }

    function scrollTarget(target, amount) {
        forceUnlockScroll();

        var scroller = nearestScrollable(target);
        var before = scroller.scrollTop;
        scroller.scrollTop = before + amount;

        if (scroller.scrollTop === before) {
            window.scrollBy(0, amount);
            document.documentElement.scrollTop += amount;
            document.body.scrollTop += amount;
        }
    }

    function scrollToTarget(target, top) {
        forceUnlockScroll();

        var scroller = nearestScrollable(target);
        scroller.scrollTop = top;
        window.scrollTo(0, top);
        document.documentElement.scrollTop = top;
        document.body.scrollTop = top;
    }

    function shouldIgnore(target, key, code) {
        if (!target || !target.tagName) return false;
        if (target.isContentEditable) return true;

        var tag = target.tagName.toLowerCase();
        if (tag === 'textarea' || tag === 'select') return true;

        if (tag === 'input') {
            var type = String(target.getAttribute('type') || 'text').toLowerCase();
            var textLike = ['text', 'search', 'email', 'password', 'tel', 'url', 'number'].indexOf(type) !== -1;

            if (!textLike) return false;

            /* The user explicitly needs keyboard scrolling back. Keep arrows for page scroll. */
            return key !== 'ArrowDown' && key !== 'ArrowUp' && code !== 40 && code !== 38 &&
                key !== 'PageDown' && key !== 'PageUp' && code !== 34 && code !== 33 &&
                key !== 'Home' && key !== 'End' && code !== 36 && code !== 35;
        }

        return false;
    }

    function handleKeyboardScroll(event) {
        if (event.altKey || event.ctrlKey || event.metaKey) return;

        var key = event.key || '';
        var code = event.keyCode || event.which || 0;
        var target = event.target || document.activeElement || document.body;

        if (shouldIgnore(target, key, code)) return;

        var pageAmount = Math.max(220, Math.floor(window.innerHeight * 0.86));
        var handled = true;

        if (key === 'ArrowDown' || key === 'Down' || code === 40) {
            scrollTarget(target, 88);
        } else if (key === 'ArrowUp' || key === 'Up' || code === 38) {
            scrollTarget(target, -88);
        } else if (key === 'PageDown' || code === 34) {
            scrollTarget(target, pageAmount);
        } else if (key === 'PageUp' || code === 33) {
            scrollTarget(target, -pageAmount);
        } else if (key === 'Home' || code === 36) {
            scrollToTarget(target, 0);
        } else if (key === 'End' || code === 35) {
            scrollToTarget(target, Math.max(document.body.scrollHeight, document.documentElement.scrollHeight));
        } else if ((key === ' ' || code === 32) && !shouldIgnore(target, key, code)) {
            scrollTarget(target, event.shiftKey ? -pageAmount : pageAmount);
        } else {
            handled = false;
        }

        if (handled) {
            event.preventDefault();
            event.stopPropagation();
            if (typeof event.stopImmediatePropagation === 'function') event.stopImmediatePropagation();
        }
    }

    window.addEventListener('keydown', handleKeyboardScroll, true);
    document.addEventListener('keydown', handleKeyboardScroll, true);
})();
