(function () {
    'use strict';

    var scanUrl = '/self-checkin/scan';
    var CAMERA_STORAGE_KEY = 'kletterdom_self_checkin_camera';
    var stateConfig = {
        ready: {
            panel: 'bg-slate-800/50 border-slate-600 text-slate-200',
            icon: '○',
            headline: 'Bereit',
            subline: 'QR-Code vorhalten',
            showHint: true,
        },
        processing: {
            panel: 'bg-slate-700/80 border-slate-500 text-slate-100',
            icon: '…',
            headline: 'Code wird geprüft …',
            subline: 'Bitte kurz warten',
            showHint: false,
        },
        success: {
            panel: 'bg-green-950/80 border-green-500 text-green-100',
            icon: '✓',
            headline: '',
            subline: '',
            showHint: false,
        },
        already_checked_in: {
            panel: 'bg-amber-950/80 border-amber-500 text-amber-100',
            icon: '◐',
            headline: '',
            subline: '',
            showHint: false,
        },
        needs_staff: {
            panel: 'bg-amber-950/80 border-amber-500 text-amber-100',
            icon: '!',
            headline: '',
            subline: '',
            showHint: false,
        },
        denied: {
            panel: 'bg-red-950/80 border-red-500 text-red-100',
            icon: '✕',
            headline: '',
            subline: '',
            showHint: false,
        },
        invalid_qr: {
            panel: 'bg-red-950/80 border-red-500 text-red-100',
            icon: '✕',
            headline: '',
            subline: '',
            showHint: false,
        },
        error: {
            panel: 'bg-red-950/80 border-red-500 text-red-100',
            icon: '✕',
            headline: '',
            subline: '',
            showHint: false,
        },
    };

    var html5QrCode = null;
    var scannerRunning = false;
    var isProcessing = false;
    var resetTimer = null;
    var preferredCameraId = null;
    var availableCameras = [];

    var els = {
        clock: document.getElementById('self-checkin-clock'),
        panel: document.getElementById('status-panel'),
        icon: document.getElementById('status-icon'),
        headline: document.getElementById('status-headline'),
        subline: document.getElementById('status-subline'),
        hintReady: document.getElementById('status-hint-ready'),
        scannerPaused: document.getElementById('scanner-paused'),
        scanHeadline: document.getElementById('scan-headline'),
        scanSubline: document.getElementById('scan-subline'),
        cameraPicker: document.getElementById('camera-picker'),
        cameraSelect: document.getElementById('camera-select'),
    };

    try {
        preferredCameraId = localStorage.getItem(CAMERA_STORAGE_KEY);
    } catch (_) {
        preferredCameraId = null;
    }

    function updateClock() {
        var now = new Date();
        els.clock.textContent = now.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
    }

    function extractToken(decodedText) {
        var token = decodedText.trim();
        var urlMatch = token.match(/\/verify\/([^/?#]+)/);
        if (urlMatch) token = urlMatch[1];
        return token;
    }

    function setUiState(uiState, payload) {
        var cfg = stateConfig[uiState] || stateConfig.ready;
        els.panel.dataset.state = uiState;
        els.panel.className = 'flex-1 flex flex-col items-center justify-center rounded-3xl border-4 p-8 md:p-12 transition-colors duration-300 ' + cfg.panel;
        els.icon.textContent = cfg.icon;

        els.headline.textContent = (payload && payload.headline) ? payload.headline : cfg.headline;

        if (payload && payload.subline !== undefined) {
            els.subline.textContent = payload.subline || '';
            els.subline.classList.toggle('hidden', !payload.subline);
        } else {
            els.subline.textContent = cfg.subline;
            els.subline.classList.remove('hidden');
        }

        els.hintReady.classList.toggle('hidden', !cfg.showHint);

        var isScanning = uiState === 'ready';
        els.scanHeadline.textContent = isScanning ? 'QR-Code scannen' : 'Scanner pausiert';
        els.scanSubline.textContent  = isScanning ? 'QR-Code vor die Kamera halten' : 'Gleich geht es weiter …';
        els.scannerPaused.classList.toggle('hidden', isScanning);
    }

    function clearResetTimer() {
        if (resetTimer) {
            clearTimeout(resetTimer);
            resetTimer = null;
        }
    }

    async function pauseScanner() {
        if (html5QrCode && scannerRunning) {
            try { await html5QrCode.pause(true); } catch (_) {}
        }
    }

    async function resumeScanner() {
        if (html5QrCode && scannerRunning) {
            try { await html5QrCode.resume(); } catch (_) {}
        }
    }

    async function resetToReady() {
        clearResetTimer();
        isProcessing = false;
        setUiState('ready');
        await resumeScanner();
    }

    function scheduleReset(ms) {
        clearResetTimer();
        resetTimer = setTimeout(function () { resetToReady(); }, ms);
    }

    function finishScanResult(data, responseOk) {
        var resolvedStatus = data.status || (responseOk ? 'success' : 'error');
        setUiState(resolvedStatus, {
            headline: data.headline || (stateConfig[resolvedStatus] && stateConfig[resolvedStatus].headline) || 'Technischer Fehler',
            subline:  data.subline  || null,
        });
        scheduleReset(data.reset_after_ms || (resolvedStatus === 'success' ? 3000 : 5000));
    }

    async function handleScan(decodedText) {
        if (isProcessing) return;
        isProcessing = true;
        clearResetTimer();
        setUiState('processing');
        await pauseScanner();

        var token = extractToken(decodedText);
        var csrf  = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

        var response;
        try {
            response = await fetch(scanUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ token: token, _token: csrf }),
            });
        } catch (_) {
            setUiState('error', { headline: 'Technischer Fehler', subline: 'Verbindung fehlgeschlagen' });
            scheduleReset(5000);
            return;
        }

        if (response.status === 419) {
            window.location.reload();
            return;
        }

        var data = {};
        try {
            data = await response.json();
        } catch (_) {
            setUiState('error', { headline: 'Technischer Fehler', subline: null });
            scheduleReset(5000);
            return;
        }

        finishScanResult(data, response.ok);
    }

    function isBuiltinCamera(label) {
        return /front|user|facetime|integrated|built.?in|iris/i.test(label || '');
    }

    function isUsbCamera(label) {
        return /usb|uvc|external|webcam|logitech|hd pro|camera|video/i.test(label || '');
    }

    function pickCamera(cameras) {
        if (preferredCameraId) {
            var saved = cameras.find(function (c) { return c.id === preferredCameraId; });
            if (saved) return saved.id;
        }

        var usbCam = cameras.find(function (c) { return isUsbCamera(c.label); });
        if (usbCam) return usbCam.id;

        if (cameras.length > 1) {
            var external = cameras.find(function (c) { return !isBuiltinCamera(c.label); });
            if (external) return external.id;
        }

        return cameras[0] ? cameras[0].id : null;
    }

    function populateCameraSelect(cameras, selectedId) {
        if (!els.cameraSelect) return;

        els.cameraSelect.innerHTML = '';
        cameras.forEach(function (cam, index) {
            var opt = document.createElement('option');
            opt.value = cam.id;
            opt.text = cam.label || ('Kamera ' + (index + 1));
            els.cameraSelect.appendChild(opt);
        });

        if (selectedId) {
            els.cameraSelect.value = selectedId;
        }

        if (els.cameraPicker) {
            els.cameraPicker.classList.toggle('hidden', cameras.length <= 1);
        }
    }

    function rememberCamera(cameraId) {
        preferredCameraId = cameraId;
        try {
            localStorage.setItem(CAMERA_STORAGE_KEY, cameraId);
        } catch (_) {}
    }

    async function ensureCameraPermission() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            throw new Error('Kamera-API nicht verfügbar (HTTPS erforderlich?)');
        }
        var stream = await navigator.mediaDevices.getUserMedia({ video: true });
        stream.getTracks().forEach(function (track) { track.stop(); });
    }

    async function stopScanner() {
        if (html5QrCode && scannerRunning) {
            try { await html5QrCode.stop(); } catch (_) {}
            scannerRunning = false;
        }
    }

    async function startScannerWithCamera(cameraId) {
        if (!cameraId) {
            setUiState('error', { headline: 'Technischer Fehler', subline: 'Keine Kamera verfügbar' });
            return;
        }

        rememberCamera(cameraId);
        await stopScanner();

        html5QrCode = new Html5Qrcode('scanner-viewport');
        await html5QrCode.start(
            cameraId,
            {
                fps: 10,
                qrbox: function (viewfinderWidth, viewfinderHeight) {
                    var edge = Math.floor(Math.min(viewfinderWidth, viewfinderHeight) * 0.72);
                    return { width: edge, height: edge };
                },
            },
            function (decodedText) { handleScan(decodedText); },
            function () {},
        );
        scannerRunning = true;
    }

    async function startScanner() {
        if (!window.Html5Qrcode) {
            setUiState('error', { headline: 'Technischer Fehler', subline: 'Scanner nicht geladen' });
            return;
        }

        try {
            await ensureCameraPermission();
            availableCameras = await Html5Qrcode.getCameras();
            if (!availableCameras || availableCameras.length === 0) {
                setUiState('error', { headline: 'Technischer Fehler', subline: 'Keine Kamera gefunden' });
                return;
            }

            var cameraId = pickCamera(availableCameras);
            populateCameraSelect(availableCameras, cameraId);
            await startScannerWithCamera(cameraId);
        } catch (err) {
            setUiState('error', {
                headline: 'Technischer Fehler',
                subline: (err && err.message) ? err.message : 'Kamerazugriff verweigert oder nicht verfügbar',
            });
        }
    }

    if (els.cameraSelect) {
        els.cameraSelect.addEventListener('change', function () {
            if (isProcessing) return;
            startScannerWithCamera(els.cameraSelect.value).catch(function (err) {
                setUiState('error', {
                    headline: 'Technischer Fehler',
                    subline: (err && err.message) ? err.message : 'Kamera konnte nicht gestartet werden',
                });
            });
        });
    }

    updateClock();
    setInterval(updateClock, 1000);
    setUiState('ready');

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startScanner);
    } else {
        startScanner();
    }
})();
