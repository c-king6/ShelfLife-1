<?php
require_once "session_config.php";
include "db_connect.php";
require_once "categories_config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $result = mysqli_query($conn, "SELECT * FROM inventory WHERE id = '$id' AND user_id = '$user_id'");
    $item = mysqli_fetch_assoc($result);

    if (!$item) {
        header("Location: dashboard.php");
        exit();
    }
} else {
    header("Location: dashboard.php");
    exit();
}

$categories = $SHELFLIFE_CATEGORIES;
$containers = ['pcs', 'bottle', 'can', 'pack', 'box', 'jar', 'bulk'];
$size_units = ['pcs', 'ml', 'L', 'g', 'kg'];

$cq = (float)($item['current_quantity'] ?? $item['quantity']);
$cc = (float)($item['container_count'] ?? 1);
$spu = (float)($item['size_per_unit'] ?? 1);
$su = $item['size_unit'] ?? 'pcs';
$ct = $item['unit'] ?? 'pcs';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit item · ShelfLife</title>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body { display: flex; align-items: center; justify-content: center; padding: 24px; }
        .form-card {
            width: 100%; max-width: 460px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-xl);
            padding: 36px 32px;
            box-shadow: var(--sh-lg);
        }
        .form-card h1 { text-align: center; margin-bottom: 4px; }
        .form-card .subtitle {
            text-align: center; color: var(--ink-500);
            font-size: 13px; margin-bottom: 26px;
        }
        .field { margin-bottom: 16px; }
        .field label {
            display: block; font-weight: 600; font-size: 12px;
            color: var(--ink-700); margin-bottom: 6px;
        }
        .form-footer { text-align: center; margin-top: 20px; }
        .form-footer a { font-size: 13px; color: var(--ink-500); }
        .form-footer a:hover { color: var(--green-600); }

        .qty-row { display: flex; gap: 10px; margin-bottom: 10px; }
        .qty-row > div { flex: 1; }
        .qty-row label { font-size: 11px; color: var(--ink-500); }

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
    </style>
</head>
<body class="page page--list">
<?php include "session_guard.php"; ?>
<?php include 'mobile_nav.php'; ?>
<div class="form-card">
    <h1>Edit item</h1>
    <p class="subtitle">Update details for this pantry item.</p>

    <form action="update_item.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">

        <div class="field">
            <label for="item_name">Item name</label>
            <input type="text" id="item_name" name="item_name" value="<?php echo htmlspecialchars($item['item_name']); ?>" required>
        </div>

        <!-- Container Setup -->
        <div class="field">
            <label>Original Container Details</label>
            <div class="qty-row">
                <div>
                    <label for="container_count">How many?</label>
                    <input type="number" id="container_count" name="container_count" value="<?php echo $cc; ?>" min="0.01" step="0.01" required>
                </div>
                <div>
                    <label for="unit">Container</label>
                    <select id="unit" name="unit">
                        <?php foreach ($containers as $c): ?>
                            <option value="<?php echo $c; ?>" <?php if ($ct === $c) echo 'selected'; ?>><?php echo $c; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="qty-row">
                <div>
                    <label for="size_per_unit">Each contains</label>
                    <input type="number" id="size_per_unit" name="size_per_unit" value="<?php echo $spu; ?>" min="0.01" step="0.01" required>
                </div>
                <div>
                    <label for="size_unit">Content unit</label>
                    <select id="size_unit" name="size_unit">
                        <?php foreach ($size_units as $su_opt): ?>
                            <option value="<?php echo $su_opt; ?>" <?php if ($su === $su_opt) echo 'selected'; ?>><?php echo $su_opt; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Current Amount -->
        <div class="field" style="background: var(--green-50); padding: 12px; border-radius: var(--r-sm); border: 1px solid var(--green-200);">
            <label for="current_quantity" style="color: var(--green-700);">Current Amount Left</label>
            <div style="display: flex; gap: 10px; align-items: center;">
                <input type="number" id="current_quantity" name="current_quantity" value="<?php echo $cq; ?>" min="0.01" step="0.01" required style="flex: 2;">
                <span style="flex: 1; font-size: 13px; font-weight: 600; color: var(--green-700);"><?php echo htmlspecialchars($su); ?></span>
            </div>
        </div>

        <div class="field">
            <label for="price">Price per container <span class="text-muted text-xs">(optional)</span></label>
            <input type="text" id="price" name="price" inputmode="decimal" pattern="[0-9]*\.?[0-9]*" placeholder="e.g. 85.00" value="<?php echo $item['price'] !== null ? htmlspecialchars($item['price']) : ''; ?>">
        </div>

        <div class="field">
            <label for="category">Category</label>
            <select id="category" name="category">
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php if ($item['category'] == $cat) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($cat); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="expiry_date">Expiration date</label>
            <input type="date" id="expiry_date" name="expiry_date" value="<?php echo htmlspecialchars($item['expiry_date'] ?? ''); ?>">
        </div>

        <div class="field">
            <label for="location">Storage location <span class="text-muted text-xs">(optional)</span></label>
            <select id="location" name="location">
                <option value="" <?php if (empty($item['location'])) echo 'selected'; ?>>Not specified</option>
                <option value="Fridge"  <?php if (($item['location'] ?? '') === 'Fridge')  echo 'selected'; ?>>🧊 Fridge</option>
                <option value="Freezer" <?php if (($item['location'] ?? '') === 'Freezer') echo 'selected'; ?>>❄️ Freezer</option>
                <option value="Pantry"  <?php if (($item['location'] ?? '') === 'Pantry')  echo 'selected'; ?>>📦 Pantry</option>
                <option value="Shelf"   <?php if (($item['location'] ?? '') === 'Shelf')   echo 'selected'; ?>>🗄️ Shelf</option>
            </select>
        </div>

        <div class="field">
            <label for="item_photo">Photo <span class="text-muted text-xs">(optional)</span></label>
            <div class="photo-picker" id="photoPicker">
                <div class="photo-preview <?php echo !empty($item['photo_path']) ? 'has-image' : ''; ?>"
                     id="photoPreview"
                     <?php if (!empty($item['photo_path'])): ?>style="background-image: url('<?php echo htmlspecialchars($item['photo_path']); ?>');"<?php endif; ?>>
                    <i data-lucide="camera" style="width:22px;height:22px;"></i>
                    <span><?php echo !empty($item['photo_path']) ? 'Change photo' : 'Add photo'; ?></span>
                </div>
                <input type="file" id="item_photo" name="item_photo" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">
            </div>
            <?php if (!empty($item['photo_path'])): ?>
                <label style="display:flex; align-items:center; gap:6px; margin-top:8px; font-size:12px; color:var(--ink-500); cursor:pointer;">
                    <input type="checkbox" name="remove_photo" value="1" style="accent-color:var(--red-500);">
                    Remove current photo
                </label>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 13px; margin-top: 8px;">
            <i data-lucide="check" class="lucide-sm"></i> Save changes
        </button>
    </form>

    <div class="form-footer">
        <a href="dashboard.php">← Cancel and return</a>
    </div>
</div>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
    lucide.createIcons();

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

</body>
</html>