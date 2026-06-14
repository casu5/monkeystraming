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
            document.documentElement.classList.toggle('mobile-menu-open', open);
            document.body.style.overflow = '';
            document.body.style.position = '';
            document.body.style.width = '';
            toggle.classList.toggle('active', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Cerrar menu' : 'Abrir menu');
            toggle.innerHTML = open
                ? '<i class="fas fa-times" aria-hidden="true"></i><span>Cerrar</span>'
                : '<i class="fas fa-bars" aria-hidden="true"></i><span>Menu</span>';
        }

        function repairStaleMenuState() {
            if (header.classList.contains('mobile-menu-open')) return;
            document.body.classList.remove('mobile-menu-open');
            document.documentElement.classList.remove('mobile-menu-open');
            toggle.classList.remove('active');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'Abrir menu');
            document.body.style.overflow = '';
            document.body.style.position = '';
            document.body.style.width = '';
        }

        function isMobileHomeScrollNav() {
            return document.body.classList.contains('home-scroll-nav') && window.innerWidth <= 760;
        }

        function setCompact(compact) {
            if (!document.body.classList.contains('home-scroll-nav')) return;

            document.body.classList.toggle('mobile-nav-compact', compact);
            header.classList.toggle('mobile-nav-compact', compact);

            if (!compact) {
                setOpen(false);
            }
        }

        toggle.addEventListener('click', function () {
            if (isMobileHomeScrollNav() && !document.body.classList.contains('mobile-nav-compact')) {
                setCompact(true);
            }
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

        window.addEventListener('pageshow', repairStaleMenuState);
        window.addEventListener('focus', repairStaleMenuState);
        repairStaleMenuState();

        window.addEventListener('resize', function () {
            if (window.innerWidth > 760) {
                setOpen(false);
                setCompact(false);
                return;
            }

            if (document.body.classList.contains('home-scroll-nav')) {
                setCompact(window.scrollY > 24);
            }
        });

        if (document.body.classList.contains('home-scroll-nav')) {
            var lastScrollY = window.scrollY || 0;
            var ticking = false;

            setCompact(lastScrollY > 24);

            function updateCompactFromScroll() {
                var currentY = window.scrollY || 0;
                var delta = currentY - lastScrollY;

                if (window.innerWidth <= 760) {
                    if (currentY <= 24 || delta < -10) {
                        setCompact(false);
                    } else if (currentY > 24) {
                        setCompact(true);
                    }
                }

                lastScrollY = currentY;
            }

            window.addEventListener('scroll', function () {
                if (ticking) return;
                ticking = true;

                window.requestAnimationFrame(function () {
                    updateCompactFromScroll();
                    ticking = false;
                });
            }, { passive: true });

            window.addEventListener('touchmove', function () {
                updateCompactFromScroll();
            }, { passive: true });

            window.setInterval(function () {
                if (!document.body.classList.contains('home-scroll-nav')) return;
                updateCompactFromScroll();
            }, 160);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupMobileMenu);
    } else {
        setupMobileMenu();
    }
})();
