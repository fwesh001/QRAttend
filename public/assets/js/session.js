/* QRAttend :: Real-Time Session Polling Engine
 * ---------------------------------------------------------------------------
 * - Reads session parameters from data-attributes on #session-root
 * - Lazily creates the session via POST to the engine
 * - Counts down remaining time every second
 * - Polls the engine every 4s for live check-ins + token (auto-rotation)
 * - Redraws the QR code seamlessly when the backend rotates the token
 */
(function () {
    'use strict';

    const root = document.getElementById('session-root');
    if (!root) return; // Not on the session page.

    const allocationId = root.dataset.allocationId;
    const pollUrl = root.dataset.pollUrl; // absolute path to app/handlers/session.php
    const apiBase = root.dataset.apiBase || '';

    const elCountdown  = document.getElementById('countdown');
    const elCheckedIn  = document.getElementById('checked-in');
    const elTotal      = document.getElementById('total-enrolled');
    const elPin        = document.getElementById('session-pin');
    const elQr         = document.getElementById('qrcode');
    const elExpired    = document.getElementById('expired-overlay');

    let sessionId   = null;
    let qrToken     = null;
    let expiresAt   = 0;       // unix seconds
    let totalEnrolled = null;
    let qrInstance  = null;
    let pollTimer   = null;
    let countdownTimer = null;
    let sessionClosed = false;

    // ---- Helpers -----------------------------------------------------------
    function pad(n) { return String(n).padStart(2, '0'); }

    function fmtCountdown(secs) {
        if (secs <= 0) return '00:00';
        const m = Math.floor(secs / 60);
        const s = secs % 60;
        return pad(m) + ':' + pad(s);
    }

    function renderQr(token) {
        if (!window.QRCode || !elQr) return;
        elQr.innerHTML = ''; // clear old canvas
        qrInstance = new window.QRCode(elQr, {
            text: String(token),
            width: Math.min(elQr.clientWidth || 400, 420),
            height: Math.min(elQr.clientWidth || 400, 420),
            colorDark: '#1A1A1A',
            colorLight: '#FFFFFF',
            correctLevel: window.QRCode.CorrectLevel.H
        });
    }

    function showExpired() {
        sessionClosed = true;
        if (elExpired) elExpired.classList.remove('d-none');
        if (elCountdown) {
            elCountdown.textContent = '00:00';
            elCountdown.style.color = 'var(--brand-danger)';
        }
        if (pollTimer) clearInterval(pollTimer);
        if (countdownTimer) clearInterval(countdownTimer);
    }

    // ---- 1. Create the session (lazy init) --------------------------------
    function initSession() {
        const fd = new FormData();
        fd.append('allocation_id', allocationId);

        fetch(pollUrl, { method: 'POST', body: fd, cache: 'no-store' })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    alert('Could not start session: ' + (data.message || 'unknown error'));
                    return;
                }
                sessionId  = data.session_id;
                qrToken    = data.qr_token;
                expiresAt  = Math.floor(new Date(data.expires_at.replace(' ', 'T')).getTime() / 1000);
                if (elPin) elPin.textContent = data.session_pin || '------';

                renderQr(qrToken);
                startCountdown();
                startPolling();
            })
            .catch(err => {
                console.error('Session init failed', err);
                alert('Network error while starting session.');
            });
    }

    // ---- 2. Countdown loop (every second) ---------------------------------
    function startCountdown() {
        tickCountdown();
        countdownTimer = setInterval(tickCountdown, 1000);
    }

    function tickCountdown() {
        const remaining = expiresAt - Math.floor(Date.now() / 1000);
        if (elCountdown) elCountdown.textContent = fmtCountdown(remaining);
        if (remaining <= 0 && !sessionClosed) {
            showExpired();
        }
    }

    // ---- 3. Polling loop (every 4s) ---------------------------------------
    function startPolling() {
        poll();
        pollTimer = setInterval(poll, 4000);
    }

    function poll() {
        if (sessionClosed || !sessionId) return;

        const url = pollUrl + '?session_id=' + encodeURIComponent(sessionId);
        fetch(url, { method: 'GET', cache: 'no-store' })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    console.warn('Poll error:', data.message);
                    return;
                }

                // Update live counter
                if (elCheckedIn) elCheckedIn.textContent = data.checked_in;

                // Sync expiry if backend reports it
                if (data.status === 'Expired' || data.status === 'Closed') {
                    showExpired();
                    return;
                }

                // Auto-rotation: token changed -> redraw QR seamlessly
                if (data.qr_token && data.qr_token !== qrToken) {
                    qrToken = data.qr_token;
                    renderQr(qrToken);
                }
            })
            .catch(err => console.error('Poll failed', err));
    }

    // ---- Boot -------------------------------------------------------------
    document.addEventListener('DOMContentLoaded', initSession);
})();

