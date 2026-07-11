// QRAttend - global client script.

(function () {
    const body = document.body;
    const loader = document.querySelector('.qra-loader-overlay');

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

