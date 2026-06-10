(function () {
    function setupMobileMenu() {
        var header = document.querySelector('body > .header');
        if (!header || header.dataset.mobileMenuReady === '1') return;

        var navContainer = header.querySelector('.nav-container');
        var nav = header.querySelector('.nav');
        if (!navContainer || !nav) return;

        header.dataset.mobileMenuReady = '1';
        header.classList.add('mobile-nav-enabled');

        var toggle = header.querySelector('.mobile-nav-toggle') || document.querySelector('.mobile-nav-toggle');
        if (!toggle) {
            toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'mobile-nav-toggle';
            header.appendChild(toggle);
        }

        toggle.removeAttribute('onclick');
        toggle.type = 'button';
        toggle.setAttribute('aria-controls', 'mobileNavPanel');
        toggle.setAttribute('aria-label', 'Abrir menu');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.innerHTML = '<i class="fas fa-bars" aria-hidden="true"></i><span>Menu</span>';
        navContainer.id = navContainer.id || 'mobileNavPanel';

        var backdrop = document.querySelector('.mobile-nav-backdrop');
        if (!backdrop) {
            backdrop = document.createElement('button');
            backdrop.type = 'button';
            backdrop.className = 'mobile-nav-backdrop';
            backdrop.setAttribute('aria-label', 'Cerrar menu');
            document.body.appendChild(backdrop);
        }

        function setOpen(open) {
            header.classList.toggle('mobile-menu-open', open);
            document.body.classList.toggle('mobile-menu-open', open);
            toggle.classList.toggle('active', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Cerrar menu' : 'Abrir menu');
            toggle.innerHTML = open
                ? '<i class="fas fa-times" aria-hidden="true"></i><span>Cerrar</span>'
                : '<i class="fas fa-bars" aria-hidden="true"></i><span>Menu</span>';
        }

        toggle.addEventListener('click', function () {
            setOpen(!document.body.classList.contains('mobile-menu-open'));
        });

        backdrop.addEventListener('click', function () {
            setOpen(false);
        });

        nav.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                setOpen(false);
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') setOpen(false);
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 760) setOpen(false);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupMobileMenu);
    } else {
        setupMobileMenu();
    }
})();
