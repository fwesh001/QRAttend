/* QRAttend :: html5-qrcode Camera Wrapper Engine
 * ---------------------------------------------------------------------------
 * - Initializes the rear camera on mobile via Html5Qrcode
 * - On a successful scan: stops the camera, POSTs the token to the backend
 * - On camera failure: silently hides the camera and flips to the PIN fallback
 * - PIN form also POSTs to the same backend endpoint
 */
(function () {
    'use strict';

    const root = document.getElementById('scanner-root');
    if (!root) return;

    const endpoint = root.dataset.endpoint || '/app/handlers/attendance.php';
    const readerEl = document.getElementById('reader');
    const statusEl = document.getElementById('scan-status');
    const pinForm  = document.getElementById('pin-form');
    const pinInput = document.getElementById('session_pin');

    let html5Qr = null;
    let processing = false;   // guard against double-submits
    let cameraStarted = false;

    // ---- UI helpers --------------------------------------------------------
    function setStatus(html, type) {
        if (!statusEl) return;
        const cls = type ? 'text-' + type : '';
        statusEl.innerHTML = '<div class="' + cls + ' small fw-semibold">' + html + '</div>';
    }

    function showPinFallback() {
        // Switch the active tab to PIN and hide the camera container.
        const pinTab = document.getElementById('tab-pin');
        const camTab = document.getElementById('tab-camera');
        if (pinTab) pinTab.click();
        if (readerEl) readerEl.style.display = 'none';
        if (camTab) camTab.classList.remove('active');
        if (pinTab) pinTab.classList.add('active');
    }

    function stopCamera() {
        if (html5Qr && cameraStarted) {
            try { html5Qr.stop().catch(function () {}); } catch (e) { /* noop */ }
            try { html5Qr.clear(); } catch (e) { /* noop */ }
            cameraStarted = false;
        }
    }

    // ---- Backend submission ------------------------------------------------
    function submitAttendance(payload) {
        if (processing) return;
        processing = true;
        setStatus('Verifying your attendance…', 'primary');

        fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(payload).toString(),
            cache: 'no-store'
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                setStatus(
                    '<i class="bi bi-check-circle-fill text-success me-1"></i>' +
                    'Success! Redirecting…', 'success'
                );
                stopCamera();
                if (data.redirect) {
                    setTimeout(function () {
                        window.location.href = data.redirect;
                    }, 1200);
                }
            } else {
                processing = false;
                setStatus(
                    '<i class="bi bi-exclamation-triangle-fill text-danger me-1"></i>' +
                    (data.message || 'Attendance failed.'), 'danger'
                );
            }
        })
        .catch(function (err) {
            processing = false;
            console.error('Attendance POST failed', err);
            setStatus('Network error. Please try again.', 'danger');
        });
    }

    // ---- Camera initialization --------------------------------------------
    function startCamera() {
        if (!window.Html5Qrcode || !readerEl) {
            showPinFallback();
            return;
        }
        html5Qr = new window.Html5Qrcode('reader', /* verbose */ false);

        const config = {
            fps: 10,
            qrbox: function (viewfinderWidth, viewfinderHeight) {
                // Optimize the scan box for compact screens.
                const min = Math.min(viewfinderWidth, viewfinderHeight);
                const size = Math.floor(min * 0.75);
                return { width: size, height: size };
            },
            aspectRatio: 1.0
        };

        html5Qr.start(
            { facingMode: 'environment' },  // rear camera on phones
            config,
            onScanSuccess,
            onScanError
        ).then(function () {
            cameraStarted = true;
        }).catch(function (err) {
            // Permission denied / no camera / OS exception -> graceful fallback.
            console.warn('Camera unavailable:', err);
            stopCamera();
            showPinFallback();
            setStatus('Camera unavailable. Use the backup PIN below.', 'warning');
        });
    }

    function onScanSuccess(decodedText) {
        if (processing) return;
        // Stop the feed immediately to prevent duplicate processing.
        stopCamera();
        submitAttendance({ qr_token: decodedText });
    }

    function onScanError(/* err */) {
        // Scanning errors are frequent and expected; ignore silently.
    }

    // ---- PIN form submission ----------------------------------------------
    if (pinForm) {
        pinForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const pin = (pinInput && pinInput.value || '').trim();
            if (!/^\d{6}$/.test(pin)) {
                setStatus('Please enter the 6-digit PIN.', 'warning');
                return;
            }
            submitAttendance({ session_pin: pin });
        });
    }

    // ---- Boot -------------------------------------------------------------
    document.addEventListener('DOMContentLoaded', startCamera);
})();

