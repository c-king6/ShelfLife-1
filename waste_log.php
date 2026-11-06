<?php
require_once "session_config.php";
include "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

require_once "household_helper.php";

// ---------- Household scope ----------
$household    = get_household_info($conn, $user_id);
$in_household = (bool)$household;
$scope        = $_GET['scope'] ?? 'own';
if (!in_array($scope, ['own', 'household'], true)) $scope = 'own';
if (!$in_household) $scope = 'own';

$scope_ids  = ($scope === 'household') ? get_household_user_ids($conn, $user_id) : [$user_id];
$scope_list = implode(',', array_map('intval', $scope_ids));

// ---------- Filters ----------
$selected_month  = $_GET['month']  ?? 'all';
$selected_status = $_GET['status'] ?? 'all';

if (!in_array($selected_status, ['all', 'consumed', 'wasted'], true)) {
    $selected_status = 'all';
}
if ($selected_month !== 'all' && !preg_match('/^\d{4}-\d{2}$/', $selected_month)) {
    $selected_month = 'all';
}

// ---------- Build WHERE clause ----------
$where = "h.user_id IN ($scope_list)";

if ($selected_month !== 'all') {
    $month_esc = mysqli_real_escape_string($conn, $selected_month);
    $where .= " AND DATE_FORMAT(h.logged_at, '%Y-%m') = '$month_esc'";
}
if ($selected_status !== 'all') {
    $where .= " AND h.status = '$selected_status'";
}

// ---------- Summary ----------
// FIX: item_history.price now stores the TOTAL value of the used portion.
// We SUM(price) directly — do not multiply by quantity.
$sum_q = mysqli_query($conn, "SELECT
    COALESCE(SUM(CASE WHEN h.status='consumed' AND h.price IS NOT NULL THEN h.price ELSE 0 END), 0) as saved,
    COALESCE(SUM(CASE WHEN h.status='wasted'   AND h.price IS NOT NULL THEN h.price ELSE 0 END), 0) as wasted,
    COUNT(CASE WHEN h.status='consumed' THEN 1 END) as consumed_count,
    COUNT(CASE WHEN h.status='wasted'   THEN 1 END) as wasted_count,
    COUNT(CASE WHEN h.price IS NULL THEN 1 END) as no_price_count
    FROM item_history h WHERE $where");
$sum = mysqli_fetch_assoc($sum_q);

$saved_amount   = (float)$sum['saved'];
$wasted_amount  = (float)$sum['wasted'];
$consumed_count = (int)$sum['consumed_count'];
$wasted_count   = (int)$sum['wasted_count'];
$no_price_count = (int)$sum['no_price_count'];
$total_value    = $saved_amount + $wasted_amount;
$efficiency     = $total_value > 0 ? ($saved_amount / $total_value) * 100 : 0;

// ---------- Available months ----------
$months_q = mysqli_query($conn, "SELECT DISTINCT DATE_FORMAT(h.logged_at, '%Y-%m') as month
                                 FROM item_history h WHERE h.user_id IN ($scope_list)
                                 ORDER BY month DESC");

// ---------- Fetch history rows ----------
$rows_q = mysqli_query($conn, "
    SELECT h.*, u.username AS owner_name
    FROM item_history h
    LEFT JOIN users u ON u.id = h.user_id
    WHERE $where
    ORDER BY h.logged_at DESC
    LIMIT 200
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food history · ShelfLife</title>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .topnav {
            position: sticky; top: 0; z-index: 100;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 14px 24px;
            display: flex; align-items: center; justify-content: space-between;
            gap: 16px; flex-wrap: wrap;
        }
        .brand {
            display: flex; align-items: center; gap: 10px;
            font-family: var(--font-display); font-weight: 700;
            font-size: 17px; letter-spacing: -0.02em; color: var(--ink-900);
        }
        .brand .logo-icon {
            width: 32px; height: 32px; border-radius: var(--r-sm);
            background: var(--green-500);
            display: flex; align-items: center; justify-content: center; color: white;
        }
        main { max-width: 900px; margin: 0 auto; padding: 32px 24px 80px; }
        .page-header { margin-bottom: 24px; }
        .page-header p { color: var(--ink-500); margin-top: 4px; }

        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            padding: 20px 24px;
            margin-bottom: 24px;
        }
        .summary .label {
            font-size: 11px; text-transform: uppercase;
            letter-spacing: 0.04em; font-weight: 600;
            color: var(--ink-500); margin-bottom: 4px;
        }
        .summary .value {
            font-family: var(--font-display);
            font-size: 24px; font-weight: 700;
            line-height: 1;
        }
        .summary .sub {
            font-size: 11.5px; color: var(--ink-500); margin-top: 4px;
        }

        .filters {
            display: flex; gap: 12px; flex-wrap: wrap;
            margin-bottom: 20px;
            align-items: center;
        }
        .filters select {
            width: auto; min-width: 180px;
            padding: 9px 12px; font-size: 13px;
        }
        .status-tabs {
            display: flex; gap: 4px;
            background: var(--sand-100);
            padding: 4px; border-radius: var(--r-full);
        }
        .status-tabs a {
            padding: 6px 14px;
            font-size: 12px; font-weight: 600;
            color: var(--ink-500);
            border-radius: var(--r-full);
            transition: 0.15s;
        }
        .status-tabs a.active {
            background: var(--surface);
            color: var(--ink-900);
            box-shadow: var(--sh-xs);
        }
        .status-tabs a.active.consumed { color: var(--green-700); }
        .status-tabs a.active.wasted   { color: var(--red-700); }

        .history-panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            overflow: hidden;
        }
        .history-header {
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
        }
        .history-header h2 { font-size: 14px; }
        .history-header .count {
            background: var(--sand-100); color: var(--ink-500);
            font-size: 11px; font-weight: 700;
            padding: 2px 8px; border-radius: var(--r-full);
        }
        .history-row {
            display: grid;
            grid-template-columns: 100px 1fr 100px 100px 100px;
            gap: 16px;
            padding: 14px 20px;
            border-bottom: 1px solid var(--sand-100);
            align-items: center;
            font-size: 13px;
        }
        .history-row:last-child { border-bottom: none; }
        .history-row .date {
            color: var(--ink-500); font-size: 12px;
        }
        .history-row .name {
            font-weight: 600; color: var(--ink-800);
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .history-row .cat {
            color: var(--ink-500); font-size: 11.5px;
            margin-top: 2px;
        }
        .history-row .qty { color: var(--ink-500); font-size: 12px; }
        .history-row .value { font-weight: 700; text-align: right; }
        .history-row .status {
            font-size: 11px; font-weight: 700;
            padding: 3px 8px; border-radius: var(--r-full);
            text-align: center;
            text-transform: uppercase; letter-spacing: 0.03em;
            display: inline-block;
        }
        .history-row .status.consumed { background: var(--green-50); color: var(--green-700); }
        .history-row .status.wasted   { background: var(--red-50);   color: var(--red-700); }
        .history-row.consumed-row .value { color: var(--green-700); }
        .history-row.wasted-row   .value { color: var(--red-700); }

        .empty-state { padding: 60px 20px; text-align: center; }
        .empty-state .empty-icon {
            width: 56px; height: 56px; border-radius: 50%;
            background: var(--sand-100); color: var(--ink-400);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
        }
        .empty-state h3 { margin-bottom: 6px; }
        .empty-state p { color: var(--ink-500); font-size: 13px; margin-bottom: 20px; }

        @media (max-width: 640px) {
            .history-row {
                grid-template-columns: 1fr auto;
                grid-template-areas:
                    "name status"
                    "date value";
                row-gap: 6px;
            }
            .history-row .date   { grid-area: date; }
            .history-row .name   { grid-area: name; }
            .history-row .qty,
            .history-row .cat    { display: none; }
            .history-row .value  { grid-area: value; }
            .history-row .status { grid-area: status; }
        }
        .waste-chart-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            padding: 20px 22px;
            margin-bottom: 24px;
        }
        .waste-chart-empty {
            padding: 40px 20px;
            text-align: center;
            color: var(--ink-500);
            font-size: 13px;
        }
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
        <h1>Food history<?php echo $scope === 'household' ? ' · Household' : ''; ?></h1>
        <p>
            <?php if ($scope === 'household'): ?>
                Every item eaten or thrown away across your household.
            <?php else: ?>
                Every item you ate or threw away, and what it cost you.
            <?php endif; ?>
        </p>
    </header>

    <div class="summary">
        <div>
            <div class="label">Saved</div>
            <div class="value" style="color: var(--green-700);">₱<?php echo number_format($saved_amount, 2); ?></div>
            <div class="sub"><?php echo $consumed_count; ?> item<?php echo $consumed_count === 1 ? '' : 's'; ?> eaten in time</div>
        </div>
        <div>
            <div class="label">Wasted</div>
            <div class="value" style="color: var(--red-700);">₱<?php echo number_format($wasted_amount, 2); ?></div>
            <div class="sub"><?php echo $wasted_count; ?> item<?php echo $wasted_count === 1 ? '' : 's'; ?> expired</div>
        </div>
        <div>
            <div class="label">Efficiency</div>
            <div class="value"><?php echo round($efficiency); ?>%</div>
            <div class="sub">of grocery value used</div>
        </div>
    </div>

    <?php if ($no_price_count > 0): ?>
        <p class="text-xs text-muted" style="margin-bottom: 20px; display: flex; align-items: center; gap: 6px;">
            <i data-lucide="info" style="width: 13px; height: 13px;"></i>
            <?php echo $no_price_count; ?> item<?php echo $no_price_count === 1 ? '' : 's'; ?> in this view had no price — their value is not counted.
        </p>
    <?php endif; ?>

    <div class="waste-chart-card">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; gap:12px; flex-wrap:wrap;">
            <div style="display:flex; align-items:center; gap:8px;">
                <i data-lucide="bar-chart-3" style="color: var(--red-500);"></i>
                <h2 style="font-size: 15px;">Waste by category</h2>
            </div>
            <span class="text-xs text-muted">All-time, all filters ignored</span>
        </div>
        <div style="position: relative; height: 240px;">
            <canvas id="wasteChart"></canvas>
        </div>
        <div class="waste-chart-empty" id="wasteEmpty" style="display:none;">
            <i data-lucide="bar-chart-3" style="width:28px;height:28px;opacity:0.4;margin-bottom:8px;"></i>
            <div>No wasted items yet — good job keeping the pantry fresh.</div>
        </div>
    </div>

    <div class="filters">
        <form method="GET" style="margin: 0;">
            <input type="hidden" name="scope" value="<?php echo htmlspecialchars($scope); ?>">
            <?php if ($selected_status !== 'all'): ?>
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($selected_status); ?>">
            <?php endif; ?>
            <select name="month" onchange="this.form.submit()">
                <option value="all" <?php echo $selected_month === 'all' ? 'selected' : ''; ?>>All time</option>
                <?php while ($m = mysqli_fetch_assoc($months_q)): ?>
                    <option value="<?php echo htmlspecialchars($m['month']); ?>" <?php echo $selected_month === $m['month'] ? 'selected' : ''; ?>>
                        <?php echo date('F Y', strtotime($m['month'] . '-01')); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </form>

        <div class="status-tabs">
            <?php
                $base = "waste_log.php?month=" . urlencode($selected_month) . "&scope=" . urlencode($scope);
            ?>
            <a href="<?php echo $base; ?>&status=all"      class="<?php echo $selected_status === 'all'      ? 'active' : ''; ?>">All</a>
            <a href="<?php echo $base; ?>&status=consumed" class="consumed <?php echo $selected_status === 'consumed' ? 'active' : ''; ?>">Consumed</a>
            <a href="<?php echo $base; ?>&status=wasted"   class="wasted <?php echo $selected_status === 'wasted'   ? 'active' : ''; ?>">Wasted</a>
        </div>
    </div>

    <div class="history-panel">
        <div class="history-header">
            <h2>History</h2>
            <span class="count"><?php echo mysqli_num_rows($rows_q); ?> entr<?php echo mysqli_num_rows($rows_q) === 1 ? 'y' : 'ies'; ?></span>
        </div>

        <?php if (mysqli_num_rows($rows_q) > 0): ?>
            <?php while ($r = mysqli_fetch_assoc($rows_q)):
                $qty_fmt = rtrim(rtrim(number_format((float)$r['quantity'], 2, '.', ''), '0'), '.');
                if ($qty_fmt === '') $qty_fmt = '0';
            ?>
                <div class="history-row <?php echo $r['status']; ?>-row">
                    <div class="date"><?php echo date('M d, Y', strtotime($r['logged_at'])); ?></div>
                    <div>
                        <div class="name">
                            <?php echo htmlspecialchars($r['item_name']); ?>
                            <?php if ($scope === 'household' && !empty($r['owner_name']) && (int)$r['user_id'] !== $user_id): ?>
                                <span style="display:inline-flex; align-items:center; font-size:10px; font-weight:700; color:var(--ink-500); background:var(--sand-100); padding:1px 6px; border-radius:var(--r-full); margin-left:6px; text-transform:none; vertical-align:middle;">
                                    <?php echo htmlspecialchars($r['owner_name']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="cat"><?php echo htmlspecialchars($r['category']); ?></div>
                    </div>
                    <div class="qty"><?php echo $qty_fmt; ?> used</div>
                    <div class="value">
                        <?php echo ($r['price'] !== null) ? '₱' . number_format((float)$r['price'], 2) : '<span class="text-muted">—</span>'; ?>
                    </div>
                    <div>
                        <div class="status <?php echo $r['status']; ?>">
                            <?php echo $r['status']; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon"><i data-lucide="book-open" class="lucide-lg"></i></div>
                <h3>No history yet</h3>
                <p>When you delete or cook pantry items, they'll show up here.</p>
                <a href="dashboard.php" class="btn btn-primary">
                    <i data-lucide="arrow-left" class="lucide-sm"></i> Back to pantry
                </a>
            </div>
        <?php endif; ?>
    </div>
</main>

<script src="https://unpkg.com/lucide@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    lucide.createIcons();

    (function () {
        const canvas = document.getElementById('wasteChart');
        if (!canvas || typeof Chart === 'undefined') return;

        const scope = '<?php echo $scope; ?>';

        fetch('chart_data.php?type=waste_categories&scope=' + encodeURIComponent(scope))
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.labels.length) {
                    canvas.style.display = 'none';
                    document.getElementById('wasteEmpty').style.display = 'block';
                    return;
                }

                new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Items wasted',
                            data: data.counts,
                            backgroundColor: 'rgba(181, 74, 63, 0.75)',
                            borderColor: '#b54a3f',
                            borderWidth: 1.5,
                            borderRadius: 6,
                            maxBarThickness: 44
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: '#1a1f1c',
                                padding: 10,
                                cornerRadius: 8,
                                titleFont: { family: "'Inter', sans-serif", size: 12, weight: '700' },
                                bodyFont: { family: "'Inter', sans-serif", size: 12 },
                                callbacks: {
                                    label: function (ctx) {
                                        const i = ctx.dataIndex;
                                        const val = data.values[i] || 0;
                                        return ' ' + ctx.parsed.y + ' item' + (ctx.parsed.y === 1 ? '' : 's') +
                                               ' · ₱' + val.toFixed(2);
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: {
                                    color: '#6b7268',
                                    font: { family: "'Inter', sans-serif", size: 11, weight: '600' }
                                }
                            },
                            y: {
                                beginAtZero: true,
                                grid: { color: 'rgba(212, 207, 197, 0.35)', drawBorder: false },
                                ticks: {
                                    color: '#8a9088',
                                    font: { family: "'Inter', sans-serif", size: 11 },
                                    precision: 0
                                }
                            }
                        }
                    }
                });
            })
            .catch(() => {
                canvas.style.display = 'none';
                document.getElementById('wasteEmpty').style.display = 'block';
            });
    })();
</script>

</body>
</html>