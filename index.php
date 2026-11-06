<?php
require_once "session_config.php";
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add item · ShelfLife</title>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body { display: flex; align-items: center; justify-content: center; padding: 24px; }
        .form-wrap { width: 100%; max-width: 460px; }
        .form-card {
            background: var(--surface);
            border-radius: var(--r-xl);
            padding: 40px 32px;
            border: 1px solid var(--border);
            box-shadow: var(--sh-lg);
        }
        .form-header { margin-bottom: 28px; text-align: center; }
        .form-header .greeting {
            font-size: 12px; color: var(--green-700);
            font-weight: 600; letter-spacing: 0.05em;
            text-transform: uppercase; margin-bottom: 6px;
        }
        .form-header h1 { margin-bottom: 4px; }
        .form-header p { color: var(--ink-500); font-size: 13px; }
        .field { margin-bottom: 18px; }
        .field label {
            display: block; font-family: var(--font-body);
            font-weight: 600; font-size: 12px;
            color: var(--ink-700); margin-bottom: 6px;
        }
        .form-footer { text-align: center; margin-top: 24px; }
        .form-footer a { font-size: 13px; color: var(--ink-500); font-weight: 500; }
        .form-footer a:hover { color: var(--green-600); }

        .qty-row { display: flex; gap: 10px; margin-bottom: 10px; }
        .qty-row > div { flex: 1; }
        .qty-row label { font-size: 11px; color: var(--ink-500); }
        .qty-row .amp { align-self: center; font-size: 15px; color: var(--ink-400); padding-top: 18px; }

        .total-hint {
            font-size: 12px; color: var(--green-700);
            font-weight: 600; margin-top: 6px;
            padding: 8px 12px; background: var(--green-50);
            border-radius: var(--r-sm);
            display: flex; align-items: center; gap: 6px;
        }

        .field-group { margin-bottom: 18px; }
        .field-group > label {
            display: block; font-family: var(--font-body);
            font-weight: 600; font-size: 12px;
            color: var(--ink-700); margin-bottom: 6px;
        }

        .expiry-chips { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px; }
        .expiry-chip {
            padding: 6px 12px; border-radius: var(--r-full);
            background: var(--surface); border: 1px solid var(--border);
            color: var(--ink-600); font-size: 12px; font-weight: 600;
            cursor: pointer; transition: 0.15s;
            font-family: var(--font-body);
        }
        .expiry-chip:hover { border-color: var(--green-500); color: var(--green-700); }
        .expiry-chip.suggested { background: var(--green-500); border-color: var(--green-500); color: white; }
        .expiry-hint {
            font-size: 11.5px; color: var(--ink-500); margin-top: 6px;
            display: flex; align-items: center; gap: 5px;
        }

        .toggle-row {
            display: flex; align-items: center; gap: 10px;
            padding: 12px 14px; background: var(--sand-50);
            border: 1px solid var(--border); border-radius: var(--r-sm);
            margin-bottom: 10px; cursor: pointer; user-select: none;
        }
        .toggle-row input { accent-color: var(--green-500); width: 16px; height: 16px; cursor: pointer; }
        .toggle-row .label-wrap { flex-grow: 1; }
        .toggle-row .label-main { font-size: 13px; font-weight: 600; color: var(--ink-800); }
        .toggle-row .label-hint { font-size: 11.5px; color: var(--ink-500); margin-top: 2px; }

        .photo-picker { cursor: pointer; position: relative; }
        .photo-preview {
            width: 100%; min-height: 120px;
            border: 2px dashed var(--border-strong);
            border-radius: var(--r-md);
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 8px; color: var(--ink-500); font-size: 12.5px; font-weight: 600;
            background: var(--sand-50); transition: 0.15s; overflow: hidden;
            background-size: cover; background-position: center;
        }
        .photo-picker:hover .photo-preview {
            border-color: var(--green-500); color: var(--green-700); background-color: var(--green-50);
        }
        .photo-preview.has-image { border-style: solid; border-color: var(--border); color: transparent; }
        .photo-preview.has-image span,
        .photo-preview.has-image i { display: none; }

        #scannerModal {
            position: fixed; inset: 0;
            background: rgba(15,23,42,0.75);
            backdrop-filter: blur(4px);
            z-index: 3000; display: none;
            justify-content: center; align-items: center; padding: 20px;
        }
        #scannerModal .scanner-card {
            background: var(--surface);
            border-radius: var(--r-xl);
            padding: 24px; width: 100%; max-width: 500px;
            box-shadow: var(--sh-lg);
        }
        #scannerModal .scanner-header {
            display: flex; align-items: center;
            justify-content: space-between; margin-bottom: 16px;
        }
        #scannerModal h3 { font-size: 16px; }
        #reader {
            width: 100%; border-radius: var(--r-md);
            overflow: hidden; background: var(--ink-900); min-height: 240px;
        }
        #reader video { display: block; }
        .scanner-hint { font-size: 12px; color: var(--ink-500); margin-top: 14px; text-align: center; }
        #scan_status { font-size: 13px; margin-top: 12px; text-align: center; min-height: 20px; color: var(--ink-500); }
    </style>
</head>
<body class="page page--auth">
<?php include "session_guard.php"; ?>
    <div class="form-wrap">
        <div class="form-card">
            <div class="form-header">
                <div class="greeting">Hi, <?php echo htmlspecialchars(strtoupper($_SESSION['username'])); ?></div>
                <h1>Add to pantry</h1>
                <p>Track what you bought so it stays fresh and visible.</p>
            </div>

            <form action="save_food.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="is_bought" value="1">

                <div class="field">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                        <label for="item_name" style="margin-bottom: 0;">Item name</label>
                        <button type="button" id="scanBtn" class="btn btn-secondary btn-sm" style="padding: 4px 10px; font-size: 11.5px;">
                            <i data-lucide="camera" class="lucide-sm"></i> Scan barcode
                        </button>
                    </div>
                    <input type="text" id="item_name" name="item_name" placeholder="e.g. Soy Sauce" required>
                    <div id="scan_hint" class="text-xs text-muted" style="margin-top: 6px; display: none;"></div>
                </div>

                <!-- How many × size -->
                <div class="field-group">
                    <label>Quantity &amp; size</label>
                    <div class="qty-row">
                        <div>
                            <label for="container_count">How many?</label>
                            <input type="number" id="container_count" name="container_count" value="1" min="0.01" step="0.01" required>
                        </div>
                        <div>
                            <label for="unit">Container</label>
                            <select id="unit" name="unit">
                                <option value="pcs">pcs</option>
                                <option value="bottle">bottle</option>
                                <option value="can">can</option>
                                <option value="pack">pack</option>
                                <option value="box">box</option>
                                <option value="jar">jar</option>
                                <option value="bulk">bulk</option>
                            </select>
                        </div>
                    </div>
                    <div class="qty-row">
                        <div>
                            <label for="size_per_unit">Each contains</label>
                            <input type="number" id="size_per_unit" name="size_per_unit" value="1" min="0.01" step="0.01" required>
                        </div>
                        <div>
                            <label for="size_unit">Content unit</label>
                            <select id="size_unit" name="size_unit">
                                <option value="pcs">pcs</option>
                                <option value="ml">ml</option>
                                <option value="L">L</option>
                                <option value="g">g</option>
                                <option value="kg">kg</option>
                            </select>
                        </div>
                    </div>
                    <div class="total-hint" id="totalHint">
                        <i data-lucide="package" style="width:14px;height:14px;"></i>
                        <span id="totalHintText">Total: 1 pcs</span>
                    </div>
                </div>

                <div class="field">
                    <label for="price">Price per container <span class="text-muted text-xs">(optional — helps track savings)</span></label>
                    <input type="text" id="price" name="price" inputmode="decimal" pattern="[0-9]*\.?[0-9]*" placeholder="e.g. 85.00" list="price_suggestions" autocomplete="off">
                    <datalist id="price_suggestions"></datalist>
                    <div id="price_history_hint" class="text-xs text-muted" style="margin-top: 6px; display: none;"></div>
                </div>

                <div class="field">
                    <label class="toggle-row" for="no_expiry">
                        <input type="checkbox" id="no_expiry" name="no_expiry" value="1">
                        <div class="label-wrap">
                            <div class="label-main">Non-perishable</div>
                            <div class="label-hint">Canned goods, dry pasta, spices, etc. — no expiry date needed</div>
                        </div>
                    </label>
                </div>

                <div class="field" id="expiryField">
                    <label for="expiry_date">Expiration date <span class="text-muted text-xs">(optional)</span></label>
                    <input type="date" id="expiry_date" name="expiry_date">
                    <div class="expiry-chips" id="expiryChips" style="display: none;"></div>
                    <div class="expiry-hint" id="expiryHint" style="display: none;">
                        <i data-lucide="sparkles" style="width: 12px; height: 12px; color: var(--green-500);"></i>
                        <span id="expiryHintText">Suggested based on the item type</span>
                    </div>
                </div>

                <div class="field">
                    <label for="location">Storage location <span class="text-muted text-xs">(optional)</span></label>
                    <select id="location" name="location">
                        <option value="">Not specified</option>
                        <option value="Fridge">🧊 Fridge</option>
                        <option value="Freezer">❄️ Freezer</option>
                        <option value="Pantry">📦 Pantry</option>
                        <option value="Shelf">🗄️ Shelf</option>
                    </select>
                </div>

                <div class="field">
                    <label for="item_photo">Photo <span class="text-muted text-xs">(optional — helps you recognize it)</span></label>
                    <div class="photo-picker" id="photoPicker">
                        <div class="photo-preview" id="photoPreview">
                            <i data-lucide="camera" style="width:22px;height:22px;"></i>
                            <span>Add photo</span>
                        </div>
                        <input type="file" id="item_photo" name="item_photo" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 13px; font-size: 14px; margin-top: 8px;">
                    <i data-lucide="plus" class="lucide-sm"></i> Stock in pantry
                </button>
            </form>

            <div class="form-footer">
                <a href="dashboard.php">← Back to pantry</a>
            </div>
        </div>
    </div>

    <div id="scannerModal">
        <div class="scanner-card">
            <div class="scanner-header">
                <h3>Scan product</h3>
                <button type="button" id="closeScanner" class="btn btn-ghost btn-sm" style="padding: 6px;">
                    <i data-lucide="x" class="lucide-sm"></i>
                </button>
            </div>
            <div id="reader"></div>
            <p class="scanner-hint">
                Step 1: point at the barcode. Step 2: point at the expiry date and tap Capture, or Skip to pick a date.
            </p>
            <div id="scan_status"></div>
            <div style="display: flex; gap: 8px; margin-top: 12px;">
                <button type="button" id="captureBtn" class="btn btn-primary" style="display: none; flex: 1;">
                    <i data-lucide="camera" class="lucide-sm"></i> Capture expiry
                </button>
                <button type="button" id="skipBtn" class="btn btn-secondary" style="display: none; flex: 1;">
                    Skip
                </button>
            </div>
            <div id="quickPicks" style="display: none;"></div>
        </div>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
    <script>
        lucide.createIcons();

        const nameField  = document.getElementById('item_name');
        const priceField = document.getElementById('price');
        const priceList  = document.getElementById('price_suggestions');

        // ---------- Quantity + size logic ----------
        const containerCountInput = document.getElementById('container_count');
        const containerSel        = document.getElementById('unit');
        const sizePerUnitInput    = document.getElementById('size_per_unit');
        const sizeUnitSel         = document.getElementById('size_unit');
        const totalHintText       = document.getElementById('totalHintText');

        function formatNum(n) {
            if (!isFinite(n)) return '0';
            return String(Math.round(n * 100) / 100);
        }

        function updateTotalHint() {
            const c = parseFloat(containerCountInput.value) || 0;
            const s = parseFloat(sizePerUnitInput.value) || 0;
            const su = sizeUnitSel.value;
            const ct = containerSel.value;
            const total = c * s;

            if (ct === 'pcs') {
                totalHintText.textContent = 'Total: ' + formatNum(total) + ' pcs';
            } else if (ct === 'bulk') {
                totalHintText.textContent = 'Total: ' + formatNum(total) + ' ' + su;
            } else {
                totalHintText.textContent = 'Total: ' + formatNum(total) + ' ' + su + ' · ' + formatNum(c) + ' ' + ct + (c === 1 ? '' : 's');
            }
        }

        containerSel.addEventListener('change', function() {
            const c = this.value;

            if (c === 'pcs') {
                sizePerUnitInput.value = 1;
                sizeUnitSel.value = 'pcs';
                sizePerUnitInput.setAttribute('readonly', 'readonly');
                sizeUnitSel.setAttribute('disabled', 'disabled');
                sizePerUnitInput.style.opacity = '0.5';
                sizeUnitSel.style.opacity = '0.5';
            } else if (c === 'bulk') {
                containerCountInput.value = 1;
                containerCountInput.setAttribute('readonly', 'readonly');
                containerCountInput.style.opacity = '0.5';
                sizePerUnitInput.removeAttribute('readonly');
                sizeUnitSel.removeAttribute('disabled');
                sizePerUnitInput.style.opacity = '1';
                sizeUnitSel.style.opacity = '1';
                if (sizeUnitSel.value === 'pcs') sizeUnitSel.value = 'g';
            } else {
                containerCountInput.removeAttribute('readonly');
                sizePerUnitInput.removeAttribute('readonly');
                sizeUnitSel.removeAttribute('disabled');
                containerCountInput.style.opacity = '1';
                sizePerUnitInput.style.opacity = '1';
                sizeUnitSel.style.opacity = '1';

                if (c === 'bottle' || c === 'jar') sizeUnitSel.value = 'ml';
                else if (c === 'can' || c === 'pack') sizeUnitSel.value = 'g';
                else if (c === 'box') sizeUnitSel.value = 'pcs';
            }
            updateTotalHint();
        });

        containerCountInput.addEventListener('input', updateTotalHint);
        sizePerUnitInput.addEventListener('input', updateTotalHint);
        sizeUnitSel.addEventListener('change', updateTotalHint);

        // Init
        containerSel.dispatchEvent(new Event('change'));

        // ---------- Price history ----------
        nameField.addEventListener('blur', function () {
            const name = nameField.value.trim();
            if (name === '') return;

            const form = new FormData();
            form.append('item_name', name);

            fetch('get_item_price.php', { method: 'POST', body: form })
                .then(r => r.json())
                .then(data => {
                    priceList.innerHTML = '';
                    (data.all_prices || []).forEach(p => {
                        const opt = document.createElement('option');
                        opt.value = p;
                        priceList.appendChild(opt);
                    });

                    if (priceField.value === '' && data.last_price !== null) {
                        priceField.value = data.last_price;
                    }

                    const hint = document.getElementById('price_history_hint');
                    if (data.all_prices && data.all_prices.length > 0) {
                        const formatted = data.all_prices.map(p => '₱' + p.toFixed(2)).join(', ');
                        hint.textContent = 'Previous prices: ' + formatted + ' — click the field to pick one';
                        hint.style.display = 'block';
                    } else {
                        hint.style.display = 'none';
                    }
                })
                .catch(() => {});
        });

        // ---------- Non-perishable toggle ----------
        const noExpiryBox = document.getElementById('no_expiry');
        const expiryField = document.getElementById('expiryField');
        const expiryInput = document.getElementById('expiry_date');

        noExpiryBox.addEventListener('change', function () {
            if (this.checked) {
                expiryField.style.opacity = '0.5';
                expiryField.style.pointerEvents = 'none';
                expiryInput.value = '';
            } else {
                expiryField.style.opacity = '1';
                expiryField.style.pointerEvents = '';
                updateExpiryChips();
            }
        });

        // ---------- Category-aware expiry chips ----------
        const chipsWrap = document.getElementById('expiryChips');
        const hintWrap  = document.getElementById('expiryHint');
        const hintText  = document.getElementById('expiryHintText');

        function suggestedDaysFor(name) {
            const n = (name || '').toLowerCase();
            if (/milk|yogurt|yoghurt|cream|cheese|bread|loaf|egg|lettuce|spinach|kale|kangkong|pechay|malunggay|fish|meat|chicken|pork|beef|shrimp|seafood|tofu/.test(n)) return 7;
            if (/juice|sauce|ketchup|mayo|mustard|jam|honey|butter|margarine|snack|chips|cookies|cereal|oats/.test(n)) return 30;
            if (/rice|pasta|noodle|spaghetti|flour|sugar|salt|oil|vinegar|soy|canned|can |sardine|tuna|spam|beans|lentils|coffee|tea|powder/.test(n)) return 365;
            return 14;
        }

        function addDays(n) {
            const d = new Date();
            d.setDate(d.getDate() + n);
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            return d.getFullYear() + '-' + mm + '-' + dd;
        }

        const EXPIRY_PRESETS = [
            { label: '+3 days',   days: 3 },
            { label: '+1 week',   days: 7 },
            { label: '+2 weeks',  days: 14 },
            { label: '+1 month',  days: 30 },
            { label: '+3 months', days: 90 },
            { label: '+6 months', days: 180 },
            { label: '+1 year',   days: 365 },
            { label: '+2 years',  days: 730 }
        ];

        function updateExpiryChips() {
            if (noExpiryBox.checked) {
                chipsWrap.style.display = 'none';
                hintWrap.style.display = 'none';
                return;
            }
            const name = nameField.value.trim();
            if (name === '') {
                chipsWrap.style.display = 'none';
                hintWrap.style.display = 'none';
                return;
            }
            const suggestedDays = suggestedDaysFor(name);
            chipsWrap.innerHTML = '';
            EXPIRY_PRESETS.forEach(p => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'expiry-chip' + (p.days === suggestedDays ? ' suggested' : '');
                btn.textContent = p.label + (p.days === suggestedDays ? ' ★' : '');
                btn.addEventListener('click', function () {
                    expiryInput.value = addDays(p.days);
                    chipsWrap.querySelectorAll('.expiry-chip').forEach(c => c.classList.remove('suggested'));
                    btn.classList.add('suggested');
                });
                chipsWrap.appendChild(btn);
            });
            chipsWrap.style.display = 'flex';
            hintWrap.style.display = 'flex';

            let hintLabel = 'Suggested based on the item type';
            if (suggestedDays === 7)        hintLabel = 'Fresh perishable — usually good for about 1 week';
            else if (suggestedDays === 30)  hintLabel = 'Medium shelf life — usually good for about 1 month';
            else if (suggestedDays === 365) hintLabel = 'Long shelf life — usually good for about 1 year';
            else                            hintLabel = 'Typical shelf life — usually good for about 2 weeks';
            hintText.textContent = hintLabel;
        }

        nameField.addEventListener('input', updateExpiryChips);

        // ---------- Photo picker ----------
        const photoPicker  = document.getElementById('photoPicker');
        const photoInput   = document.getElementById('item_photo');
        const photoPreview = document.getElementById('photoPreview');

        if (photoPicker && photoInput && photoPreview) {
            photoPicker.addEventListener('click', function () { photoInput.click(); });

            photoInput.addEventListener('change', function () {
                const file = this.files[0];
                if (!file) return;
                if (file.size > 5 * 1024 * 1024) {
                    if (window.showToast) window.showToast('Photo must be under 5MB.', 'warning');
                    else alert('Photo must be under 5MB.');
                    this.value = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = function (e) {
                    photoPreview.style.backgroundImage = 'url(' + e.target.result + ')';
                    photoPreview.classList.add('has-image');
                };
                reader.readAsDataURL(file);
            });
        }
    </script>
    <script src="scanner.js"></script>
</body>
</html>