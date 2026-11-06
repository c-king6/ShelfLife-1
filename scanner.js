// ShelfLife barcode + expiry scanner
// OCR tries to read the expiry date. If it fails, quick-picks appear.

(function () {
    'use strict';

    var scanBtn      = document.getElementById('scanBtn');
    var scannerModal = document.getElementById('scannerModal');
    var closeScanner = document.getElementById('closeScanner');
    var scanStatus   = document.getElementById('scan_status');
    var scanHint     = document.getElementById('scan_hint');
    var captureBtn   = document.getElementById('captureBtn');
    var skipBtn      = document.getElementById('skipBtn');
    var quickPicks   = document.getElementById('quickPicks');

    if (!scanBtn || !scannerModal) return;

    var html5QrCode  = null;
    var currentPhase = 'idle';
    var currentItemName = '';

    function setStatus(text, color) {
        if (!scanStatus) return;
        scanStatus.textContent = text;
        scanStatus.style.color = color || 'var(--ink-500)';
    }
    function showCapture(show) {
        if (captureBtn) captureBtn.style.display = show ? 'inline-flex' : 'none';
    }
    function showSkip(show) {
        if (skipBtn) skipBtn.style.display = show ? 'inline-flex' : 'none';
    }
    function showQuickPicks(show) {
        if (quickPicks) quickPicks.style.display = show ? 'block' : 'none';
    }

    // Suggest a date based on the item's name.
    function smartDateFor(name) {
        var n = (name || '').toLowerCase();

        // Fresh / perishable — suggest 1 week
        if (/milk|yogurt|yoghurt|cream|cheese|bread|loaf|egg|lettuce|spinach|kale|kangkong|pechay|malunggay|fish|meat|chicken|pork|beef|shrimp|seafood|tofu/.test(n)) {
            return addDays(7);
        }
        // Medium-life — suggest 1 month
        if (/juice|sauce|ketchup|mayo|mustard|jam|honey|butter|margarine|snack|chips|cookies|cereal|oats/.test(n)) {
            return addDays(30);
        }
        // Long-life / dry / canned — suggest 1 year
        if (/rice|pasta|noodle|spaghetti|flour|sugar|salt|oil|vinegar|soy|canned|can |sardine|tuna|spam|beans|lentils|coffee|tea|powder/.test(n)) {
            return addDays(365);
        }
        // Default fallback — 2 weeks
        return addDays(14);
    }

    function addDays(n) {
        var d = new Date();
        d.setDate(d.getDate() + n);
        var mm = String(d.getMonth() + 1).padStart(2, '0');
        var dd = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + mm + '-' + dd;
    }

    async function startScanner() {
        scannerModal.style.display = 'flex';
        currentPhase = 'barcode';
        showCapture(false);
        showSkip(false);
        showQuickPicks(false);
        setStatus('Starting camera...');

        try {
            html5QrCode = new Html5Qrcode('reader');
            await html5QrCode.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: { width: 280, height: 160 } },
                onBarcodeDetected,
                function () {}
            );
            setStatus('Point at the product barcode...');
        } catch (err) {
            console.error(err);
            setStatus('Camera unavailable. Use localhost or HTTPS.', 'var(--red-600)');
        }
    }

    async function stopScanner() {
        if (html5QrCode) {
            try { await html5QrCode.stop(); } catch (e) {}
            try { await html5QrCode.clear(); } catch (e) {}
            html5QrCode = null;
        }
        scannerModal.style.display = 'none';
        currentPhase = 'idle';
        showCapture(false);
        showSkip(false);
        showQuickPicks(false);
    }

    async function onBarcodeDetected(decodedText) {
        if (currentPhase !== 'barcode') return;
        currentPhase = 'looking_up';
        setStatus('Barcode detected: ' + decodedText + ' - looking up product...');

        try {
            var res  = await fetch(
                'https://world.openfoodfacts.org/api/v2/product/' +
                encodeURIComponent(decodedText) + '.json'
            );
            var data = await res.json();

            if (data.status === 1 && data.product) {
                var p = data.product;
                var name  = (p.product_name || p.generic_name || '').trim();
                var brand = (p.brands || '').trim();
                var full  = brand ? (brand + ' ' + name).trim() : name;

                if (full) {
                    currentItemName = full;
                    document.getElementById('item_name').value = full;
                    scanHint.textContent = 'Found: ' + full;
                    scanHint.style.display = 'block';
                    scanHint.style.color = 'var(--green-700)';
                }
            } else {
                setStatus('Product not in catalog. You can still set expiry below.', 'var(--amber-700)');
            }
        } catch (err) {
            console.error(err);
            setStatus('Lookup failed. You can still set expiry below.', 'var(--amber-700)');
        }

        currentPhase = 'expiry';
        showCapture(true);
        showSkip(true);
        setStatus('Tap Capture to scan the date, or Skip and pick one.', 'var(--ink-500)');

        // Build the smart quick-picks (used if OCR fails or user skips)
        buildQuickPicks();
    }

    function buildQuickPicks() {
        if (!quickPicks) return;
        var suggested = smartDateFor(currentItemName);

        quickPicks.innerHTML = '';

        var label = document.createElement('div');
        label.textContent = 'Quick pick expiry:';
        label.style.fontSize = '11.5px';
        label.style.color = 'var(--ink-500)';
        label.style.marginTop = '14px';
        label.style.marginBottom = '8px';
        label.style.textAlign = 'center';
        quickPicks.appendChild(label);

        var wrap = document.createElement('div');
        wrap.style.display = 'flex';
        wrap.style.flexWrap = 'wrap';
        wrap.style.gap = '8px';
        wrap.style.justifyContent = 'center';

        var presets = [
            { label: '+3 days', days: 3 },
            { label: '+1 week', days: 7, suggested: true },
            { label: '+2 weeks', days: 14 },
            { label: '+1 month', days: 30 },
            { label: '+3 months', days: 90 },
            { label: '+6 months', days: 180 },
            { label: '+1 year', days: 365 },
            { label: '+2 years', days: 730 }
        ];

        // Figure out which preset matches the smart suggestion
        var suggestedDays = null;
        if (suggested) {
            var target = new Date(suggested);
            var today = new Date();
            suggestedDays = Math.round((target - today) / 86400000);
        }

        presets.forEach(function (p) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = p.label;
            btn.className = 'btn btn-secondary btn-sm';
            btn.style.fontSize = '12px';
            if (suggestedDays !== null && Math.abs(p.days - suggestedDays) < 5) {
                btn.className = 'btn btn-primary btn-sm';
                btn.style.fontSize = '12px';
                btn.textContent = p.label + ' (suggested)';
            }
            btn.addEventListener('click', function () {
                var d = addDays(p.days);
                document.getElementById('expiry_date').value = d;
                scanHint.textContent = 'Expiry set to ' + d;
                scanHint.style.display = 'block';
                scanHint.style.color = 'var(--green-700)';
                stopScanner();
            });
            wrap.appendChild(btn);
        });

        quickPicks.appendChild(wrap);
    }

    async function captureExpiry() {
        if (currentPhase !== 'expiry') return;
        currentPhase = 'reading';
        showCapture(false);
        showSkip(false);
        showQuickPicks(false);
        setStatus('Reading date...');

        var video = document.querySelector('#reader video');
        if (!video || video.readyState < 2) {
            setStatus('Camera not ready. Try again.', 'var(--red-600)');
            currentPhase = 'expiry';
            showCapture(true);
            showSkip(true);
            showQuickPicks(true);
            return;
        }

        var canvas = document.createElement('canvas');
        canvas.width  = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);

        var cropCanvas = cropCenter(canvas);
        var dataUrl    = cropCanvas.toDataURL('image/png');

        var ocrText = '';

        try {
            var result = await Tesseract.recognize(dataUrl, 'eng', {
                logger: function (m) {
                    if (m.status === 'recognizing text') {
                        setStatus('Reading date... ' + Math.round(m.progress * 100) + '%');
                    }
                }
            });
            ocrText = (result && result.data && result.data.text) || '';
        } catch (err) {
            console.error(err);
            setStatus('OCR failed. Pick a date below.', 'var(--amber-700)');
            currentPhase = 'expiry';
            showCapture(true);
            showSkip(true);
            showQuickPicks(true);
            return;
        }

        console.log('[ShelfLife] OCR raw:\n' + ocrText);

        var parsed = parseExpiryDate(ocrText);
        if (parsed) {
            document.getElementById('expiry_date').value = parsed;
            setStatus('Expiry set to ' + parsed + ' - edit if wrong.', 'var(--green-700)');
            setTimeout(stopScanner, 1200);
        } else {
            setStatus('Could not read the date. Try again or pick below.', 'var(--amber-700)');
            currentPhase = 'expiry';
            showCapture(true);
            showSkip(true);
            showQuickPicks(true);
        }
    }

    function cropCenter(source) {
        var w = source.width, h = source.height;
        var cw = Math.round(w * 0.7);
        var ch = Math.round(h * 0.4);
        var cx = Math.round((w - cw) / 2);
        var cy = Math.round((h - ch) / 2);

        var out = document.createElement('canvas');
        out.width  = cw;
        out.height = ch;
        out.getContext('2d').drawImage(source, cx, cy, cw, ch, 0, 0, cw, ch);
        return out;
    }

    // =========================================================
    //  DATE PARSING (kept from before)
    // =========================================================

    var MONTH_NAMES = {
        JAN:1, IAN:1, JAM:1, FEB:2, FEE:2, MAR:3, MAH:3,
        APR:4, APH:4, MAY:5, MAV:5, JUN:6, IUN:6, JUH:6,
        JUL:7, IUL:7, AUG:8, AUC:8, SEP:9, SEPT:9, SET:9,
        OCT:10, DCT:10, NOV:11, HOV:11, DEC:12, DFC:12
    };

    function fixDigits(str) {
        return str
            .replace(/O/g, '0').replace(/[Il|!]/g, '1')
            .replace(/S/g, '5').replace(/B/g, '8')
            .replace(/Z/g, '2').replace(/G/g, '6')
            .replace(/[qQ]/g, '9');
    }

    function matchMonth(word) {
        if (!word) return null;
        var w = word.toUpperCase().replace(/[^A-Z0-9]/g, '');
        if (!w) return null;
        if (MONTH_NAMES[w]) return MONTH_NAMES[w];
        var first3 = w.slice(0, 3);
        if (MONTH_NAMES[first3]) return MONTH_NAMES[first3];
        return null;
    }

    function buildDate(year, month, day) {
        if (!year || !month || month < 1 || month > 12) return null;
        if (year < 2000 || year > 2099) return null;
        if (day === null || day === undefined) {
            day = new Date(year, month, 0).getDate();
        }
        if (day < 1 || day > 31) return null;
        var test = new Date(year, month - 1, day);
        if (test.getFullYear() !== year || test.getMonth() !== month - 1 || test.getDate() !== day) return null;
        var mm = String(month).padStart(2, '0');
        var dd = String(day).padStart(2, '0');
        return year + '-' + mm + '-' + dd;
    }

    function twoToFourYear(y) { return (y < 100) ? (y + 2000) : y; }

    function extractDates(rawLine) {
        var results = [];
        var variants = [rawLine];
        var cleaned  = fixDigits(rawLine);
        if (cleaned !== rawLine) variants.push(cleaned);

        for (var v = 0; v < variants.length; v++) {
            var t = variants[v].toUpperCase();
            var m;

            var re1 = /\b(\d{1,2})\s*[-\/\.\s]?\s*([A-Z]{3,4})\s*[-\/\.\s]?\s*(\d{2,4})\b/g;
            while ((m = re1.exec(t)) !== null) {
                var mo1 = matchMonth(m[2]);
                if (mo1) {
                    var d1 = buildDate(twoToFourYear(parseInt(m[3], 10)), mo1, parseInt(m[1], 10));
                    if (d1) results.push(d1);
                }
            }

            var re2 = /\b([A-Z]{3,4})\s*[-\/\.\s]?\s*(\d{1,2})\s*[-\/\.\s]?\s*(\d{2,4})\b/g;
            while ((m = re2.exec(t)) !== null) {
                var mo2 = matchMonth(m[1]);
                if (mo2) {
                    var d2 = buildDate(twoToFourYear(parseInt(m[3], 10)), mo2, parseInt(m[2], 10));
                    if (d2) results.push(d2);
                }
            }

            var re3 = /\b(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})\b/g;
            while ((m = re3.exec(t)) !== null) {
                var d3 = buildDate(parseInt(m[1], 10), parseInt(m[2], 10), parseInt(m[3], 10));
                if (d3) results.push(d3);
            }

            var re4 = /\b(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2,4})\b/g;
            while ((m = re4.exec(t)) !== null) {
                var a4 = parseInt(m[1], 10), b4 = parseInt(m[2], 10);
                var y4 = twoToFourYear(parseInt(m[3], 10));
                if (a4 > 12) { var d4a = buildDate(y4, b4, a4); if (d4a) results.push(d4a); }
                else if (b4 > 12) { var d4b = buildDate(y4, a4, b4); if (d4b) results.push(d4b); }
                else {
                    var d4c = buildDate(y4, b4, a4), d4d = buildDate(y4, a4, b4);
                    if (d4c) results.push(d4c);
                    if (d4d && d4d !== d4c) results.push(d4d);
                }
            }

            var re5 = /\b([A-Z]{3,4})\s+(\d{4})\b/g;
            while ((m = re5.exec(t)) !== null) {
                var mo5 = matchMonth(m[1]);
                if (mo5) { var d5 = buildDate(parseInt(m[2], 10), mo5, null); if (d5) results.push(d5); }
            }

            var re6 = /\b(\d{1,2})[\/\-](\d{4})\b/g;
            while ((m = re6.exec(t)) !== null) {
                var mo6 = parseInt(m[1], 10), y6 = parseInt(m[2], 10);
                if (mo6 >= 1 && mo6 <= 12) { var d6 = buildDate(y6, mo6, null); if (d6) results.push(d6); }
            }

            var re7 = /\b(\d{1,2})[\/\-](\d{2})\b/g;
            while ((m = re7.exec(t)) !== null) {
                var mo7 = parseInt(m[1], 10), y7 = twoToFourYear(parseInt(m[2], 10));
                if (mo7 >= 1 && mo7 <= 12) { var d7 = buildDate(y7, mo7, null); if (d7) results.push(d7); }
            }

            var re8 = /\b(\d{8})\b/g;
            while ((m = re8.exec(t)) !== null) {
                var s8 = m[1];
                var d8 = buildDate(parseInt(s8.slice(0, 4), 10), parseInt(s8.slice(4, 6), 10), parseInt(s8.slice(6, 8), 10));
                if (d8) results.push(d8);
            }

            var re9 = /\b(\d{6})\b/g;
            while ((m = re9.exec(t)) !== null) {
                var s9 = m[1];
                var a9 = parseInt(s9.slice(0, 2), 10), b9 = parseInt(s9.slice(2, 4), 10);
                var y9 = twoToFourYear(parseInt(s9.slice(4, 6), 10));
                if (a9 > 12 && b9 <= 12) { var d9a = buildDate(y9, b9, a9); if (d9a) results.push(d9a); }
                else if (b9 > 12 && a9 <= 12) { var d9b = buildDate(y9, a9, b9); if (d9b) results.push(d9b); }
                else if (a9 <= 12 && b9 <= 12) {
                    var d9c = buildDate(y9, b9, a9), d9d = buildDate(y9, a9, b9);
                    if (d9c) results.push(d9c);
                    if (d9d && d9d !== d9c) results.push(d9d);
                }
            }
        }
        return results;
    }

    function parseExpiryDate(rawText) {
        if (!rawText) return null;
        var text = rawText.toUpperCase();
        var lines = text.split(/\n+/).map(function (l) { return l.trim(); }).filter(Boolean);

        var expiryMarkers = /\b(EXP|EXPIRY|EXPIRES|EXPIRATION|BBE?|BEST\s*BEFORE|USE\s*BY|USE\s*BEFORE|UB|CBD|CONSUME\s*BY|CONSUME\s*BEFORE|ED|E\.D\.)\b/;
        var mfgMarkers    = /\b(MFG|MANUFACTURED|MHD|PROD|PRODUCTION|PACKED|MFD)\b/;

        for (var i = 0; i < lines.length; i++) {
            if (expiryMarkers.test(lines[i]) && !mfgMarkers.test(lines[i])) {
                var dates = extractDates(lines[i]);
                if (dates.length) { dates.sort(); return dates[dates.length - 1]; }
            }
        }

        var candidates = [];
        for (var j = 0; j < lines.length; j++) {
            if (mfgMarkers.test(lines[j])) continue;
            candidates = candidates.concat(extractDates(lines[j]));
        }
        if (candidates.length) { candidates.sort(); return candidates[candidates.length - 1]; }

        var all = [];
        for (var k = 0; k < lines.length; k++) all = all.concat(extractDates(lines[k]));
        if (all.length) { all.sort(); return all[all.length - 1]; }

        return null;
    }

    scanBtn.addEventListener('click', startScanner);
    if (closeScanner) closeScanner.addEventListener('click', stopScanner);
    if (captureBtn)   captureBtn.addEventListener('click', captureExpiry);
    if (skipBtn)      skipBtn.addEventListener('click', stopScanner);
})();