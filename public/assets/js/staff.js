(function () {
    'use strict';

    var confirmForm = null;
    var orangeReasonInput = null;
    var isModalKulanzRequired = false;

    window.askConfirm = function (message, form) {
        confirmForm = form;
        orangeReasonInput = null;
        isModalKulanzRequired = false;
        document.getElementById('confirmModalText').textContent = message;
        document.getElementById('confirmOrangeHint').classList.add('hidden');
        document.getElementById('confirmOrangeKulanz').classList.add('hidden');
        var okBtn = document.getElementById('confirmOkBtn');
        okBtn.disabled = false;
        okBtn.textContent = 'Ja, auschecken';
        okBtn.classList.remove('bg-indigo-600', 'hover:bg-indigo-700');
        okBtn.classList.add('bg-red-600', 'hover:bg-red-700');
        showModal();
    };

    window.openCheckinModal = function (form, reasonInput, name, reason, accessStatus, nextTriggersRed, visits, pastCheckinDates, lastKulanz) {
        var isTrialLimit = visits >= 1 && accessStatus !== 'orange';

        confirmForm = form;
        orangeReasonInput = reasonInput;
        isModalKulanzRequired = true;

        var label = isTrialLimit
            ? name + ' war bereits Schnuppern. Trotzdem einchecken?'
            : name + ' hat Status Orange. Trotzdem einchecken?';
        document.getElementById('confirmModalText').textContent = label;

        var displayReason = isTrialLimit
            ? (pastCheckinDates
                ? 'Schnupperklettern bereits absolviert am ' + pastCheckinDates
                : 'Schnuppergast hat bereits einen Besuch absolviert.')
            : (reason || 'Kein spezifischer Grund angegeben');

        document.getElementById('confirmOrangeReason').textContent = '⚠️ ' + displayReason;
        document.getElementById('confirmOrangeHint').classList.remove('hidden');
        document.getElementById('confirmOrangeKulanz').classList.remove('hidden');

        var kulanzWrapper = document.getElementById('confirmKulanzWrapper');
        var lastKulanzEl  = document.getElementById('confirmLastKulanz');
        if (lastKulanz && lastKulanz.trim() !== '') {
            lastKulanzEl.textContent = lastKulanz;
            kulanzWrapper.classList.remove('hidden');
        } else {
            lastKulanzEl.textContent = '';
            kulanzWrapper.classList.add('hidden');
        }

        var redHint = document.getElementById('confirmRedNextHint');
        if (nextTriggersRed) redHint.classList.remove('hidden');
        else                 redHint.classList.add('hidden');

        var kulanzInput = document.getElementById('confirmKulanzInput');
        kulanzInput.value = '';
        kulanzInput.classList.remove('border-red-500');

        var okBtn = document.getElementById('confirmOkBtn');
        okBtn.disabled = false;
        okBtn.textContent = 'Trotzdem Check-in';
        okBtn.classList.remove('bg-red-600', 'hover:bg-red-700');
        okBtn.classList.add('bg-indigo-600', 'hover:bg-indigo-700');

        showModal();
        setTimeout(function () { kulanzInput.focus(); }, 50);
    };

    window.closeConfirmModal = function () {
        confirmForm = null;
        orangeReasonInput = null;
        isModalKulanzRequired = false;
        var modal = document.getElementById('confirmModal');
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
        document.getElementById('confirmOrangeHint').classList.add('hidden');
        document.getElementById('confirmOrangeKulanz').classList.add('hidden');
        document.getElementById('confirmRedNextHint').classList.add('hidden');
        document.getElementById('confirmKulanzInput').value = '';
        document.getElementById('confirmKulanzWrapper').classList.add('hidden');
        document.getElementById('confirmLastKulanz').textContent = '';
        var okBtn = document.getElementById('confirmOkBtn');
        okBtn.classList.remove('bg-indigo-600', 'hover:bg-indigo-700');
        okBtn.classList.add('bg-red-600', 'hover:bg-red-700');
        okBtn.textContent = 'Ja, auschecken';
        okBtn.disabled = false;
    };

    function showModal() {
        var modal = document.getElementById('confirmModal');
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
    }

    document.addEventListener('DOMContentLoaded', function () {
        var ok = document.getElementById('confirmOkBtn');
        if (ok) {
            ok.addEventListener('click', function () {
                if (!confirmForm) return;
                if (orangeReasonInput) {
                    var val = document.getElementById('confirmKulanzInput').value.trim();
                    if (isModalKulanzRequired && !val) {
                        var inp = document.getElementById('confirmKulanzInput');
                        inp.classList.add('border-red-500');
                        inp.focus();
                        return;
                    }
                    orangeReasonInput.value = val;
                }
                this.disabled = true;
                confirmForm.submit();
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeConfirmModal();
        });
    });

    // ── QR-Scanner (Hallendienst) ─────────────────────────────────────────
    var html5QrCode = null;
    var scannerRunning = false;
    var lastScanned = null;

    window.staffToggleScanner = function () {
        var panel = document.getElementById('qr-scanner-panel');
        if (panel.classList.contains('hidden')) {
            panel.classList.remove('hidden');
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            initCameraList();
        } else {
            staffStopScanner();
            panel.classList.add('hidden');
        }
    };

    async function initCameraList() {
        try {
            var cameras = await Html5Qrcode.getCameras();
            var select  = document.getElementById('camera-select');
            select.innerHTML = '';
            if (!cameras || cameras.length === 0) {
                select.innerHTML = '<option value="">Keine Kamera gefunden</option>';
                showStatus('Keine Kamera gefunden. Bitte Kamerazugriff erlauben.', 'error');
                return;
            }
            cameras.forEach(function (cam, i) {
                var opt = document.createElement('option');
                opt.value = cam.id;
                opt.text  = cam.label || ('Kamera ' + (i + 1));
                select.appendChild(opt);
            });
            var backCam = cameras.find(function (c) { return /back|rear|environment/i.test(c.label); });
            if (backCam) select.value = backCam.id;
            staffStartScanner();
        } catch (err) {
            showStatus('Kamerazugriff verweigert. Bitte in den Browser-Einstellungen erlauben.', 'error');
        }
    }

    window.staffStartScanner = async function () {
        var cameraId = document.getElementById('camera-select').value;
        if (!cameraId) { showStatus('Bitte zuerst eine Kamera auswählen.', 'error'); return; }
        if (scannerRunning) await staffStopScanner();
        html5QrCode = new Html5Qrcode('qr-reader');
        try {
            await html5QrCode.start(
                cameraId,
                { fps: 10, qrbox: { width: 250, height: 250 } },
                onScanSuccess,
                function () {},
            );
            scannerRunning = true;
            showStatus('Scanner aktiv – QR-Code vor die Kamera halten.', 'info');
        } catch (err) {
            showStatus('Kamera konnte nicht gestartet werden: ' + err, 'error');
        }
    };

    window.staffStopScanner = async function () {
        if (html5QrCode && scannerRunning) {
            try { await html5QrCode.stop(); } catch (_) {}
            scannerRunning = false;
        }
        clearStatus();
    };

    async function onScanSuccess(decodedText) {
        if (decodedText === lastScanned) return;
        lastScanned = decodedText;
        setTimeout(function () { lastScanned = null; }, 3000);
        if (html5QrCode && scannerRunning) { try { html5QrCode.pause(); } catch (_) {} }
        showStatus('QR-Code erkannt – wird geprüft …', 'info');

        var token = decodedText.trim();
        var urlMatch = token.match(/\/verify\/([^/?#]+)/);
        if (urlMatch) token = urlMatch[1];

        var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        var response;
        try {
            response = await fetch('/verify/' + token + '/checkin', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
            });
        } catch (networkErr) {
            showStatus('Verbindungsfehler – ist der Server erreichbar?', 'error');
            setTimeout(function () { try { html5QrCode.resume(); } catch (_) {} }, 3000);
            return;
        }

        if (response.status === 419) {
            showStatus('Sitzung abgelaufen – Seite wird neu geladen …', 'info');
            setTimeout(function () { window.location.reload(); }, 1500);
            return;
        }
        if (response.status === 404) {
            showStatus('⚠ QR-Code nicht erkannt – ungültiger oder abgelaufener Code.', 'error');
            setTimeout(function () { try { html5QrCode.resume(); } catch (_) {} }, 3000);
            return;
        }

        var data = {};
        try { data = await response.json(); } catch (_) {
            showStatus('Unerwartete Server-Antwort.', 'error');
            return;
        }

        if (response.ok && data.success) {
            showStatus('✓ ' + data.message, 'success');
            setTimeout(function () { window.location.reload(); }, 1800);
        } else {
            showStatus('⚠ ' + (data.message || 'Unbekannter Fehler'), 'error');
            setTimeout(function () { try { html5QrCode.resume(); } catch (_) {} }, 3000);
        }
    }

    function showStatus(msg, type) {
        var el = document.getElementById('qr-status');
        el.textContent = msg;
        el.className = 'mt-3 rounded-lg px-4 py-3 text-sm font-medium';
        var styles = {
            info:    'bg-blue-50 border border-blue-200 text-blue-800',
            success: 'bg-green-50 border border-green-200 text-green-800',
            error:   'bg-red-50 border border-red-200 text-red-800',
        };
        el.classList.add.apply(el.classList, (styles[type] || styles.info).split(' '));
        el.classList.remove('hidden');
    }

    function clearStatus() {
        var el = document.getElementById('qr-status');
        el.classList.add('hidden');
        el.textContent = '';
    }

    // ── F2-Shortcut ───────────────────────────────────────────────────────
    document.addEventListener('keydown', function (e) {
        if (e.repeat) return;
        if (e.key !== 'F2') return;
        if (!window.matchMedia('(min-width: 768px)').matches) return;

        var modal = document.getElementById('confirmModal');
        if (modal && !modal.classList.contains('hidden')) return;

        var active = document.activeElement;
        if (active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.tagName === 'SELECT' || active.isContentEditable)) return;

        e.preventDefault();
        var panel = document.getElementById('qr-scanner-panel');
        if (panel.classList.contains('hidden')) {
            staffToggleScanner();
        } else {
            staffStartScanner();
        }
    });

    // ── Auto-Refresh (Snapshot-Polling) ───────────────────────────────────
    (function () {
        var SNAPSHOT_URL = window.STAFF_SNAPSHOT_URL || '/hallendienst/snapshot';
        var INTERVAL_MS  = 15000;
        var inFlight     = false;

        function shouldSkipRefresh() {
            if (document.hidden) return true;
            var search = document.getElementById('q');
            if (search && search.value.trim() !== '') return true;
            var scannerPanel = document.getElementById('qr-scanner-panel');
            if (scannerPanel && !scannerPanel.classList.contains('hidden')) return true;
            var modal = document.getElementById('confirmModal');
            if (modal && !modal.classList.contains('hidden')) return true;
            return false;
        }

        async function refreshSnapshot() {
            if (inFlight || shouldSkipRefresh()) return;
            inFlight = true;
            try {
                var params = new URLSearchParams(window.location.search);
                params.delete('q');
                var url = SNAPSHOT_URL + (params.toString() ? '?' + params.toString() : '');
                var response = await fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!response.ok) return;
                var data = await response.json();
                var statsEl = document.getElementById('staff-stats');
                var listEl  = document.getElementById('staff-list');
                if (statsEl && typeof data.stats === 'string') statsEl.innerHTML = data.stats;
                if (listEl  && typeof data.list  === 'string') listEl.innerHTML  = data.list;
            } catch (_) {
                /* silent fail */
            } finally {
                inFlight = false;
            }
        }

        setInterval(refreshSnapshot, INTERVAL_MS);
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) refreshSnapshot();
        });
    })();
})();
