<?php
require_once "session_config.php";
include "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if (isset($_POST['set_expiry'])) {
    $id = (int)$_POST['item_id'];

    if (isset($_POST['no_expiry'])) {
        $expiry = '2099-12-31';
    } else {
        $expiry = mysqli_real_escape_string($conn, $_POST['expiry_date'] ?? '');
        if ($expiry === '') {
            $expiry = '2099-12-31';
        }
    }

    $allowed_locations = ['Fridge', 'Freezer', 'Pantry', 'Shelf'];
    $location = in_array($_POST['location'] ?? '', $allowed_locations, true) ? $_POST['location'] : null;
    $location_sql = ($location !== null) ? "'" . mysqli_real_escape_string($conn, $location) . "'" : "NULL";

    $cur_q = mysqli_query($conn, "SELECT item_name, quantity, container_count, unit, size_per_unit, size_unit, price, category FROM inventory
                                  WHERE id = $id AND user_id = '$user_id' LIMIT 1");
    $cur = mysqli_fetch_assoc($cur_q);

    if ($cur) {
        $name_esc  = mysqli_real_escape_string($conn, $cur['item_name']);
        $cat_esc   = mysqli_real_escape_string($conn, $cur['category']);
        $ct_esc    = mysqli_real_escape_string($conn, $cur['unit'] ?? 'pcs');
        $su_esc    = mysqli_real_escape_string($conn, $cur['size_unit'] ?? 'pcs');
        
        $cc        = (float)$cur['container_count'];
        $spu       = (float)$cur['size_per_unit'];
        $total_content = $cc * $spu;
        
        $price_sql = ($cur['price'] !== null) ? "'" . (float)$cur['price'] . "'" : "NULL";

        // Look for a match in the pantry
        $match_q = mysqli_query($conn, "SELECT id, quantity, container_count, initial_quantity, current_quantity FROM inventory
                                        WHERE user_id = '$user_id'
                                        AND id != $id
                                        AND item_name = '$name_esc'
                                        AND category = '$cat_esc'
                                        AND expiry_date = '$expiry'
                                        AND price <=> $price_sql
                                        AND unit = '$ct_esc'
                                        AND size_unit = '$su_esc'
                                        AND is_bought = 1
                                        LIMIT 1");
        $match = mysqli_fetch_assoc($match_q);

        if ($match) {
            // Combine: bump existing row's quantities
            $new_cc = (float)$match['container_count'] + $cc;
            $new_initial = (float)$match['initial_quantity'] + $total_content;
            $new_current = (float)$match['current_quantity'] + $total_content;
            $new_qty_int = (int)ceil($new_current);
            
            mysqli_query($conn, "UPDATE inventory SET 
                                    quantity = $new_qty_int, 
                                    container_count = $new_cc, 
                                    initial_quantity = $new_initial, 
                                    current_quantity = $new_current, 
                                    location = COALESCE(location, $location_sql) 
                                 WHERE id = " . (int)$match['id'] . " AND user_id = '$user_id'");
            mysqli_query($conn, "DELETE FROM inventory WHERE id = $id AND user_id = '$user_id'");
        } else {
            // No match — set expiry and location, move to pantry
            mysqli_query($conn, "UPDATE inventory SET 
                                    expiry_date = '$expiry', 
                                    location = $location_sql, 
                                    initial_quantity = $total_content, 
                                    current_quantity = $total_content 
                                 WHERE id = $id AND user_id = '$user_id'");
        }
    }

    header("Location: incoming_groceries.php");
    exit();
}

$incoming = mysqli_query($conn, "SELECT * FROM inventory WHERE is_bought = 1 AND expiry_date IS NULL AND user_id = '$user_id' ORDER BY id DESC");
$count = mysqli_num_rows($incoming);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incoming groceries · ShelfLife</title>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .topnav { position: sticky; top: 0; z-index: 100; background: var(--surface); border-bottom: 1px solid var(--border); padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
        .brand { display: flex; align-items: center; gap: 10px; font-family: var(--font-display); font-weight: 700; font-size: 17px; letter-spacing: -0.02em; color: var(--ink-900); }
        .brand .logo-icon { width: 32px; height: 32px; border-radius: var(--r-sm); background: var(--green-500); display: flex; align-items: center; justify-content: center; color: white; }
        main { max-width: 640px; margin: 0 auto; padding: 32px 24px 80px; }
        .page-header { margin-bottom: 24px; }
        .page-header p { color: var(--ink-500); margin-top: 4px; }
        .queue-panel { background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden; }
        .queue-header { padding: 14px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .queue-header h2 { font-size: 14px; }
        .queue-header .count { background: var(--amber-50); color: var(--amber-700); font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: var(--r-full); }
        .item-card { padding: 18px 20px; border-bottom: 1px solid var(--sand-100); }
        .item-card:last-child { border-bottom: none; }
        .item-row-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
        .item-name { font-weight: 600; font-size: 14px; color: var(--ink-900); }
        .item-qty { font-size: 11.5px; color: var(--ink-500); background: var(--sand-100); padding: 3px 9px; border-radius: var(--r-full); font-weight: 600; }
        .item-form-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .item-form-row label { font-size: 12px; color: var(--ink-600); font-weight: 600; }
        .item-form-row input[type="date"] { flex: 1; min-width: 160px; }
        .never-check { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; color: var(--ink-600); font-weight: 500; cursor: pointer; user-select: none; }
        .never-check input { accent-color: var(--green-500); }
        .empty-queue { padding: 60px 20px; text-align: center; }
        .empty-queue .empty-icon { width: 56px; height: 56px; border-radius: 50%; background: var(--green-100); color: var(--green-700); display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; }
        .empty-queue h3 { margin-bottom: 6px; }
        .empty-queue p { color: var(--ink-500); font-size: 13px; margin-bottom: 20px; }
        
        /* Quick expiry buttons */
        .quick-expiry { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 10px; }
        .quick-expiry .btn { padding: 4px 10px; font-size: 11.5px; border-radius: var(--r-full); }
    </style>
</head>
<body class="page page--list">
<?php include "session_guard.php"; ?>
<?php include 'mobile_nav.php'; ?>
<nav class="topnav">
    <a href="dashboard.php" class="brand">
        <div class="logo-icon"><i data-lucide="leaf"></i></div>
        ShelfLife
    </a>
    <a href="dashboard.php" class="btn btn-secondary btn-sm">
        <i data-lucide="arrow-left" class="lucide-sm"></i> Back to pantry
    </a>
</nav>

<main>
    <header class="page-header">
        <h1>Incoming groceries</h1>
        <p>Set expiry dates to move items into your pantry.</p>
    </header>

    <div class="queue-panel">
        <div class="queue-header">
            <h2>Waiting to be stocked</h2>
            <span class="count"><?php echo (int)$count; ?> item<?php echo $count === 1 ? '' : 's'; ?></span>
        </div>

        <?php if ($count > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($incoming)): 
                $cc = (float)($row['container_count'] ?? 1);
                $ct = $row['unit'] ?? 'pcs';
                $spu = (float)($row['size_per_unit'] ?? 1);
                $su = $row['size_unit'] ?? 'pcs';
            ?>
                <div class="item-card">
                    <div class="item-row-top">
                        <span class="item-name"><?php echo htmlspecialchars($row['item_name']); ?></span>
                        <span class="item-qty"><?php echo $cc; ?> <?php echo htmlspecialchars($ct); ?><?php echo $cc > 1 ? 's' : ''; ?> (<?php echo $spu * $cc; ?> <?php echo htmlspecialchars($su); ?>)</span>
                    </div>

                    <div class="quick-expiry">
                        <button type="button" class="btn btn-secondary quick-expiry-btn" data-days="3">+3 days</button>
                        <button type="button" class="btn btn-secondary quick-expiry-btn" data-days="7">+1 week</button>
                        <button type="button" class="btn btn-secondary quick-expiry-btn" data-days="14">+2 weeks</button>
                        <button type="button" class="btn btn-secondary quick-expiry-btn" data-days="30">+1 month</button>
                        <button type="button" class="btn btn-secondary quick-expiry-btn" data-days="90">+3 months</button>
                    </div>

                    <form method="POST" class="item-form-row">
                        <input type="hidden" name="item_id" value="<?php echo (int)$row['id']; ?>">
                        <label>Expires on</label>
                        <input type="date" name="expiry_date">
                        <label>Location</label>
                        <select name="location" style="min-width: 130px;">
                            <option value="">—</option>
                            <option value="Fridge">🧊 Fridge</option>
                            <option value="Freezer">❄️ Freezer</option>
                            <option value="Pantry">📦 Pantry</option>
                            <option value="Shelf">🗄️ Shelf</option>
                        </select>
                        <label class="never-check">
                            <input type="checkbox" name="no_expiry">
                            Non-perishable
                        </label>
                        <button type="submit" name="set_expiry" class="btn btn-primary btn-sm">
                            <i data-lucide="check" class="lucide-sm"></i> Move to pantry
                        </button>
                    </form>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-queue">
                <div class="empty-icon"><i data-lucide="sparkles" class="lucide-lg"></i></div>
                <h3>All caught up</h3>
                <p>No incoming items are waiting to be stocked.</p>
                <a href="dashboard.php" class="btn btn-primary">
                    <i data-lucide="home" class="lucide-sm"></i> Back to pantry
                </a>
            </div>
        <?php endif; ?>
    </div>
</main>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
    lucide.createIcons();
    
    // Quick Expiry Logic
    document.querySelectorAll('.quick-expiry-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const days = parseInt(this.dataset.days);
            const form = this.closest('.item-card').querySelector('form');
            const dateInput = form.querySelector('input[name="expiry_date"]');
            
            const d = new Date();
            d.setDate(d.getDate() + days);
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            dateInput.value = d.getFullYear() + '-' + mm + '-' + dd;
            
            // Highlight selected
            this.closest('.quick-expiry').querySelectorAll('.btn').forEach(b => {
                b.classList.remove('btn-primary');
                b.classList.add('btn-secondary');
            });
            this.classList.remove('btn-secondary');
            this.classList.add('btn-primary');
        });
    });
</script>
</body>
</html>