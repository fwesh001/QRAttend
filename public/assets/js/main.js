// QRAttend - global client script.

(function () {
    const body = document.body;
    const loader = document.querySelector('.qra-loader-overlay');

    // ---- Toast overlay (bottom-right lg / bottom-center sm) --------------
    const ensureToastRegion = () => {
        let region = document.querySelector('.qra-toast-region');
        if (!region) {
            region = document.createElement('div');
            region.className = 'qra-toast-region';
            document.body.appendChild(region);
        }
        return region;
    };

    // Move any server-rendered flash toasts into the corner region.
    const relocateFlashToasts = () => {
        const region = ensureToastRegion();
        document.querySelectorAll('.qra-toast-region > .alert').forEach((el) => {
            region.appendChild(el);
        });
    };

    // Global helper: window.qraToast(type, text, ms)
    // type: success | danger | warning | info
    window.qraToast = (type, text, ms) => {
        const region = ensureToastRegion();
        const alert = document.createElement('div');
        alert.className = 'alert alert-' + type + ' alert-dismissible fade show mb-0 shadow';
        alert.setAttribute('role', 'alert');
        alert.textContent = text;
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'btn-close';
        close.setAttribute('data-bs-dismiss', 'alert');
        close.setAttribute('aria-label', 'Close');
        alert.appendChild(close);
        region.appendChild(alert);
        if (window.bootstrap && window.bootstrap.Alert) {
            new window.bootstrap.Alert(alert);
        }
        const ttl = ms || 5000;
        setTimeout(() => {
            if (window.bootstrap && window.bootstrap.Alert) {
                const inst = window.bootstrap.Alert.getOrCreateInstance(alert);
                inst.close();
            } else {
                alert.remove();
            }
        }, ttl);
    };

    const hideInitialLoader = () => {
        body.classList.remove('page-loading');
        body.classList.remove('page-loading-active');
        if (loader) {
            loader.classList.remove('active');
        }
    };

    const showLoader = () => {
        body.classList.add('page-loading-active');
        if (loader) {
            loader.classList.add('active');
        }
    };

    const isInternalLink = (link) => {
        try {
            const url = new URL(link.href, window.location.href);
            return url.origin === window.location.origin && !url.hash && !link.hasAttribute('download') && link.target !== '_blank';
        } catch (e) {
            return false;
        }
    };

    const bindNavigationHooks = () => {
        document.querySelectorAll('form').forEach((form) => {
            form.addEventListener('submit', () => showLoader());
        });

        document.querySelectorAll('a').forEach((anchor) => {
            if (anchor.href && isInternalLink(anchor)) {
                anchor.addEventListener('click', () => showLoader());
            }
        });
    };

    document.addEventListener('DOMContentLoaded', () => {
        hideInitialLoader();
        relocateFlashToasts();
        bindNavigationHooks();
    });

    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            hideInitialLoader();
        }
    });

    document.addEventListener('readystatechange', () => {
        if (document.readyState === 'complete') {
            hideInitialLoader();
        }
    });
})();

