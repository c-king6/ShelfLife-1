<?php
require_once "session_config.php";
include "db_connect.php";
require_once "categories_config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$tab    = $_GET['tab']    ?? 'official';
$search = trim($_GET['q'] ?? '');
if (!in_array($tab, ['official', 'community', 'mine', 'saved'], true)) $tab = 'official';

$ready_filter  = isset($_GET['ready']) && $_GET['ready'] === '1';
$health_filter = $_GET['health'] ?? 'all';
if (!in_array($health_filter, ['all', 'Veggie-heavy', 'Balanced', 'Meat-heavy', 'Light'], true)) $health_filter = 'all';

$search_esc = mysqli_real_escape_string($conn, $search);

// Build query based on tab
$where = "";
if ($tab === 'official') {
    $where = "r.user_id IS NULL AND r.is_approved = 1";
} elseif ($tab === 'community') {
    $where = "r.user_id IS NOT NULL AND r.user_id != '$user_id' AND r.is_public = 1 AND r.is_approved = 1 AND (r.is_hidden = 0 OR r.user_id = '$user_id')";
} elseif ($tab === 'mine') {
    $where = "r.user_id = '$user_id'";
} elseif ($tab === 'saved') {
    $where = "r.id IN (SELECT recipe_id FROM recipe_saves WHERE user_id = '$user_id')";
}

if ($search_esc !== '') {
    $where .= " AND (r.recipe_name LIKE '%$search_esc%' OR r.ingredients LIKE '%$search_esc%')";
}

$sql = "SELECT r.*, u.username AS author_name,
        (SELECT COUNT(*) FROM recipe_saves WHERE recipe_id = r.id) AS save_count,
        (SELECT COUNT(*) FROM recipe_saves WHERE recipe_id = r.id AND user_id = '$user_id') AS user_saved
        FROM recipes r
        LEFT JOIN users u ON r.user_id = u.id
        WHERE $where
        ORDER BY r.recipe_name ASC";
$recipes = mysqli_query($conn, $sql);

// Load this user's pantry ingredients once for the "you have X of Y" indicator
$pantry = [];
$pq = mysqli_query($conn, "SELECT DISTINCT LOWER(TRIM(item_name)) AS name FROM inventory WHERE user_id='$user_id' AND is_bought=1");
while ($p = mysqli_fetch_assoc($pq)) $pantry[] = $p['name'];

function count_pantry_matches($ingredients, $pantry) {
    // Ignore pantry staples (salt, oil, garlic, etc.)
    $split = shelflife_split_ingredients($ingredients);
    $needed = $split['needed'];
    $have = 0;
    foreach ($needed as $p) {
        $needle = strtolower(trim($p));
        if ($needle === '') continue;
        foreach ($pantry as $item) {
            if (strpos($item, $needle) !== false || strpos($needle, $item) !== false) { $have++; break; }
        }
    }
    return [
        'have'          => $have,
        'total'         => count($needed),
        'staples_count' => count($split['staples']),
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recipes · ShelfLife</title>
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
        main { max-width: 1000px; margin: 0 auto; padding: 32px 24px 100px; }
        .page-header {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 20px; flex-wrap: wrap; gap: 16px;
        }
        .page-header p { color: var(--ink-500); margin-top: 4px; }

        .tabs {
            display: flex; gap: 4px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 20px;
            overflow-x: auto;
        }
        .tabs {
            -ms-overflow-style: none;
            scrollbar-width: none;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .tabs::-webkit-scrollbar { display: none; }

        @media (max-width: 768px) {
            .tab {
                flex: 1;
                justify-content: center;
                padding: 10px 6px;
                font-size: 11.5px;
                gap: 4px;
                white-space: nowrap;
            }
            .tab .lucide-sm { width: 13px; height: 13px; }
            .tab .badge { font-size: 9.5px; padding: 1px 5px; }
        }

        .tab {
            padding: 12px 18px;
            font-family: var(--font-display);
            font-weight: 700; font-size: 13px;
            color: var(--ink-500);
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
            transition: 0.15s;
            white-space: nowrap;
            text-decoration: none;
            display: inline-flex; align-items: center; gap: 8px;
        }
        .tab:hover { color: var(--ink-800); }
        .tab.active { color: var(--green-700); border-bottom-color: var(--green-500); }

        .search-bar {
            position: relative; max-width: 420px;
            margin-bottom: 24px;
        }
        .search-bar input { padding-left: 42px; }

        .filter-bar {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .filter-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: var(--r-full);
            background: var(--surface);
            border: 1px solid var(--border);
            color: var(--ink-600);
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: 0.15s;
        }
        .filter-chip:hover {
            border-color: var(--green-500);
            color: var(--green-700);
        }
        .filter-chip.active {
            background: var(--green-500);
            border-color: var(--green-500);
            color: white;
        }
        .filter-divider {
            width: 1px;
            height: 20px;
            background: var(--border);
            margin: 0 6px;
        }

        .recipe-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 16px;
        }
        .recipe-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            padding: 20px;
            display: flex; flex-direction: column;
            transition: all 0.15s;
        }
        .recipe-card:hover {
            border-color: var(--green-500);
            box-shadow: var(--sh-md);
        }
        .recipe-card .top-row {
            display: flex; justify-content: space-between; align-items: flex-start;
            gap: 12px; margin-bottom: 10px;
        }
        .recipe-card .title {
            font-family: var(--font-display);
            font-weight: 700; font-size: 16px;
            color: var(--ink-900);
            line-height: 1.25;
        }
        .recipe-card .author {
            font-size: 11px; color: var(--ink-500);
            margin-top: 3px;
            display: flex; align-items: center; gap: 5px;
        }
        .badge-official {
            background: var(--green-100);
            color: var(--green-700);
        }
        .recipe-card .save-btn {
            display: flex; align-items: center; gap: 4px;
            background: var(--sand-50);
            border: 1px solid var(--border);
            color: var(--ink-700);
            padding: 5px 10px;
            border-radius: var(--r-full);
            font-size: 11.5px; font-weight: 700;
            cursor: pointer;
            transition: 0.15s;
            flex-shrink: 0;
        }
        .recipe-card .save-btn:hover {
            border-color: var(--green-500);
            color: var(--green-700);
        }
        .recipe-card .save-btn.saved {
            background: var(--green-50);
            border-color: var(--green-500);
            color: var(--green-700);
        }

        .recipe-card .match-bar {
            font-size: 11.5px; font-weight: 600;
            margin-bottom: 12px;
            color: var(--ink-500);
        }
        .recipe-card .match-bar.ready { color: var(--green-700); }
        .recipe-card .match-bar.partial { color: #b45309; }

        .recipe-card .ingredients {
            font-size: 12.5px; color: var(--ink-600);
            line-height: 1.55;
            margin-bottom: 12px;
            flex-grow: 1;
        }
        .recipe-card .instructions {
            font-size: 12.5px; color: var(--ink-500);
            line-height: 1.55;
            max-height: 84px;
            overflow: hidden;
            margin-bottom: 14px;
            padding-top: 12px;
            border-top: 1px dashed var(--border);
        }
        .recipe-card .actions {
            display: flex; gap: 8px; margin-top: auto;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--surface);
            border: 1px dashed var(--border);
            border-radius: var(--r-lg);
            grid-column: 1 / -1;
        }
        .empty-state .empty-icon {
            width: 56px; height: 56px; border-radius: 50%;
            background: var(--sand-100); color: var(--ink-400);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
        }
        .empty-state h3 { margin-bottom: 6px; }
        .empty-state p { color: var(--ink-500); font-size: 13px; margin-bottom: 20px; }

        /* modal */
        .modal-overlay {
            position: fixed; inset: 0;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(4px);
            display: none;
            justify-content: center; align-items: center;
            z-index: 2000; padding: 20px;
        }
        .modal-overlay.open { display: flex; }
        .modal-card {
            background: white;
            border-radius: var(--r-xl);
            padding: 28px;
            width: 100%; max-width: 500px;
            max-height: 90vh; overflow-y: auto;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
        }
        .modal-card h3 { margin-bottom: 18px; font-size: 18px; }
        .field { margin-bottom: 14px; }
        .field label {
            display: block; font-weight: 600; font-size: 12px;
            color: var(--ink-700); margin-bottom: 6px;
        }
        .field textarea {
            width: 100%; padding: 12px 14px;
            border: 1px solid var(--border); border-radius: var(--r-sm);
            font-family: var(--font-body); font-size: 13px;
            resize: vertical; box-sizing: border-box;
        }
        .field textarea:focus {
            outline: none; border-color: var(--green-400);
            box-shadow: 0 0 0 4px var(--green-50);
        }
        .visibility-row {
            display: flex; align-items: center; gap: 10px;
            padding: 12px 14px;
            background: var(--sand-50);
            border: 1px solid var(--border);
            border-radius: var(--r-sm);
            margin-bottom: 14px;
        }
        .visibility-row input { accent-color: var(--green-500); }
        .visibility-row label { font-size: 13px; color: var(--ink-700); cursor: pointer; }
        .visibility-row .hint { font-size: 11.5px; color: var(--ink-500); }
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
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="dashboard.php" class="btn btn-secondary btn-sm">
            <i data-lucide="arrow-left" class="lucide-sm"></i> Back to pantry
        </a>
        <button class="btn btn-primary btn-sm" onclick="openRecipeModal()">
            <i data-lucide="plus" class="lucide-sm"></i> Share recipe
        </button>
    </div>
</nav>

<main>
    <header class="page-header">
        <div>
            <h1>Recipes</h1>
            <p>Discover recipes from the community or share your own.</p>
        </div>
    </header>

    <div class="tabs">
        <a href="?tab=official" class="tab <?php echo $tab==='official'?'active':''; ?>">
            <i data-lucide="badge-check" class="lucide-sm"></i> Official
        </a>
        <a href="?tab=community" class="tab <?php echo $tab==='community'?'active':''; ?>">
            <i data-lucide="users" class="lucide-sm"></i> Community
        </a>
        <a href="?tab=mine" class="tab <?php echo $tab==='mine'?'active':''; ?>">
            <i data-lucide="chef-hat" class="lucide-sm"></i> My recipes
        </a>
        <a href="?tab=saved" class="tab <?php echo $tab==='saved'?'active':''; ?>">
            <i data-lucide="bookmark" class="lucide-sm"></i> Saved
        </a>
    </div>

    <form method="GET" class="search-bar">
        <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
        <i data-lucide="search" class="lucide-sm search-icon" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--ink-400); pointer-events: none;"></i>
        <input type="text" name="q" placeholder="Search by recipe name or ingredient..." value="<?php echo htmlspecialchars($search); ?>">
    </form>

    <div class="filter-bar">
        <?php
            $base_params = "recipes.php?tab=" . urlencode($tab);
            if ($search !== '')    $base_params .= "&q=" . urlencode($search);
            if ($health_filter !== 'all') $base_params .= "&health=" . urlencode($health_filter);
        ?>
        <a href="<?php echo $base_params . ($ready_filter ? '' : '&ready=1'); ?>" class="filter-chip <?php echo $ready_filter ? 'active' : ''; ?>">
            <i data-lucide="check-circle" style="width:12px;height:12px;"></i>
            Ready to cook
        </a>

        <span class="filter-divider"></span>

        <?php
            $health_opts = ['all' => 'All', 'Veggie-heavy' => 'Veggie', 'Balanced' => 'Balanced', 'Meat-heavy' => 'Meat', 'Light' => 'Light'];
            foreach ($health_opts as $key => $label):
                $p = "recipes.php?tab=" . urlencode($tab);
                if ($search !== '')      $p .= "&q=" . urlencode($search);
                if ($ready_filter)       $p .= "&ready=1";
                if ($key !== 'all')      $p .= "&health=" . urlencode($key);
        ?>
            <a href="<?php echo $p; ?>" class="filter-chip <?php echo $health_filter === $key ? 'active' : ''; ?>">
                <?php echo htmlspecialchars($label); ?>
            </a>
        <?php endforeach; ?>
    </div>
    
    <div class="recipe-grid">
        <?php if (mysqli_num_rows($recipes) === 0): ?>
            <div class="empty-state">
                <div class="empty-icon"><i data-lucide="chef-hat" class="lucide-lg"></i></div>
                <h3>
                    <?php
                        if ($tab === 'mine')      echo "You haven't shared any recipes yet";
                        elseif ($tab === 'saved') echo "No saved recipes yet";
                        elseif ($search !== '')   echo "No recipes match your search";
                        else                      echo "No recipes here yet";
                    ?>
                </h3>
                <p>
                    <?php if ($tab === 'mine'): ?>
                        Share your favorite recipe with the community.
                    <?php elseif ($tab === 'saved'): ?>
                        Save recipes you like to see them here.
                    <?php else: ?>
                        Try a different search or tab.
                    <?php endif; ?>
                </p>
                <?php if ($tab === 'mine'): ?>
                    <button class="btn btn-primary" onclick="openRecipeModal()">
                        <i data-lucide="plus" class="lucide-sm"></i> Share your first recipe
                    </button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php while ($r = mysqli_fetch_assoc($recipes)):
                $m = count_pantry_matches($r['ingredients'], $pantry);
                $is_ready = ($m['total'] > 0 && $m['have'] === $m['total']);
                $is_mine  = ((int)$r['user_id'] === (int)$user_id);
                $is_saved = ((int)$r['user_saved'] > 0);

                $skip_card = false;
                if ($ready_filter && !$is_ready) $skip_card = true;
                if ($health_filter !== 'all' && health_tag($r['ingredients']) !== $health_filter) $skip_card = true;

                if (!$skip_card):
            ?>
                <div class="recipe-card" data-id="<?php echo (int)$r['id']; ?>">
                    <div class="top-row">
                        <div style="flex-grow:1; min-width:0;">
                            <div class="title"><?php echo htmlspecialchars($r['recipe_name']); ?></div>
                            <div class="author">
                                <?php if ($r['user_id'] === null): ?>
                                    <span class="badge badge-green badge-official" style="font-size:10px; padding:2px 6px;">Official</span>
                                <?php else: ?>
                                    <i data-lucide="user" style="width:11px;height:11px;"></i>
                                    <?php echo htmlspecialchars($r['author_name'] ?? 'Unknown'); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <button class="save-btn <?php echo $is_saved ? 'saved' : ''; ?>"
                                onclick="toggleSave(<?php echo (int)$r['id']; ?>, this)"
                                title="<?php echo $is_saved ? 'Unsave' : 'Save'; ?>">
                            <i data-lucide="<?php echo $is_saved ? 'bookmark-check' : 'bookmark'; ?>" style="width:12px;height:12px;"></i>
                            <span class="save-count"><?php echo (int)$r['save_count']; ?></span>
                        </button>
                    </div>

                    <?php if ($m['total'] > 0): ?>
                        <div class="match-bar <?php echo $is_ready ? 'ready' : 'partial'; ?>">
                            <?php if ($is_ready): ?>
                                ✓ Ready to cook — you have all <?php echo $m['total']; ?> needed ingredients<?php if ($m['staples_count'] > 0): ?> (<?php echo $m['staples_count']; ?> staple<?php echo $m['staples_count'] === 1 ? '' : 's'; ?> assumed on-hand)<?php endif; ?>
                            <?php else: ?>
                                You have <?php echo $m['have']; ?> of <?php echo $m['total']; ?> needed ingredients
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="ingredients">
                        <strong style="color: var(--ink-700); font-size:11.5px; text-transform:uppercase; letter-spacing:0.04em;">Ingredients</strong><br>
                        <?php echo htmlspecialchars($r['ingredients']); ?>
                    </div>

                    <?php if (!empty($r['instructions'])): ?>
                        <div class="instructions">
                            <strong style="color: var(--ink-700); font-size:11.5px; text-transform:uppercase; letter-spacing:0.04em;">Method</strong><br>
                            <?php echo nl2br(htmlspecialchars($r['instructions'])); ?>
                        </div>
                    <?php endif; ?>

                    <div class="actions">
                        <?php if ($is_mine): ?>
                            <button class="btn btn-secondary btn-sm" style="flex:1;"
                                onclick='openRecipeModal(<?php echo (int)$r["id"]; ?>, <?php echo json_encode($r["recipe_name"]); ?>, <?php echo json_encode($r["ingredients"]); ?>, <?php echo json_encode($r["instructions"]); ?>, <?php echo (int)$r["is_public"]; ?>)'>
                                <i data-lucide="pencil" class="lucide-sm"></i> Edit
                            </button>
                            <a href="delete_recipe.php?id=<?php echo (int)$r['id']; ?>"
                               class="btn btn-sm"
                               style="background: var(--red-50); color: var(--red-700);"
                               onclick="return confirm('Delete this recipe?')">
                                <i data-lucide="trash-2" class="lucide-sm"></i>
                            </a>
                            <?php else: ?>
                                <button class="btn btn-primary btn-sm" style="flex:1;"
                                    onclick='copyRecipe(<?php echo (int)$r["id"]; ?>, <?php echo json_encode($r["recipe_name"]); ?>)'>
                                    <i data-lucide="copy" class="lucide-sm"></i> Add to my recipes
                                </button>
                                <button class="btn btn-secondary btn-sm"
                                    onclick='openReportModal(<?php echo (int)$r["id"]; ?>, <?php echo json_encode($r["recipe_name"]); ?>)'
                                    title="Report this recipe">
                                    <i data-lucide="flag" class="lucide-sm"></i>
                                </button>
                            <?php endif; ?>
                    </div>
                </div>
            <?php endif; endwhile; ?>
        <?php endif; ?>
    </div>
            <!-- Report modal -->
        <div id="reportModal" class="modal-overlay">
            <div class="modal-card" style="max-width: 420px;">
                <h3>Report recipe</h3>
                <p class="text-muted text-sm" style="margin-bottom: 18px;" id="reportRecipeName"></p>

                <form id="reportForm" onsubmit="submitReport(event)">
                    <input type="hidden" name="recipe_id" id="report_recipe_id">

                    <div class="field">
                        <label>Reason</label>
                        <select name="reason" id="report_reason" required style="width:100%; padding:11px 14px; border:1px solid var(--border); border-radius:var(--r-sm); font-family:var(--font-body); font-size:13px;">
                            <option value="">Select a reason...</option>
                            <option value="not_food">Not actually a food recipe</option>
                            <option value="inappropriate">Inappropriate content</option>
                            <option value="spam">Spam or misleading</option>
                            <option value="copyright">Copied without credit</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="field">
                        <label>Details <span class="text-muted text-xs">(optional)</span></label>
                        <textarea name="details" rows="3" maxlength="500" placeholder="Tell the admins what's wrong..."></textarea>
                    </div>

                    <div id="report_msg" style="font-size:13px; margin-bottom:12px; display:none;"></div>

                    <div style="display: flex; gap: 8px;">
                        <button type="button" class="btn btn-secondary" style="flex:1;" onclick="closeReportModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="flex:1;">Submit report</button>
                    </div>
                </form>
            </div>
        </div>
</main>

<!-- Submit / Edit modal -->
<div id="recipeModal" class="modal-overlay">
    <div class="modal-card">
        <h3 id="modalTitle">Share a recipe</h3>
        <form method="POST" action="save_recipe.php" id="recipeForm">
            <input type="hidden" name="recipe_id" id="form_recipe_id" value="">

            <div class="field">
                <label for="recipe_name">Recipe name</label>
                <input type="text" id="recipe_name" name="recipe_name" placeholder="e.g. Lola's Chicken Adobo" required maxlength="255">
            </div>

            <div class="field">
                <label for="ingredients">Ingredients <span class="text-muted text-xs">(comma-separated)</span></label>
                <textarea id="ingredients" name="ingredients" rows="3" placeholder="chicken, soy sauce, vinegar, garlic, bay leaf" required></textarea>
            </div>

            <div class="field">
                <label for="instructions">Instructions</label>
                <textarea id="instructions" name="instructions" rows="5" placeholder="1. Marinate the chicken..."></textarea>
            </div>

            <div class="visibility-row">
                <input type="checkbox" id="is_public" name="is_public" value="1" checked>
                <label for="is_public" style="flex-grow:1; margin:0;">
                    Make it public
                    <div class="hint">Other users can see and save this recipe</div>
                </label>
            </div>

            <div style="display: flex; gap: 8px; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeRecipeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex: 1;">Publish recipe</button>
            </div>
        </form>
    </div>
</div>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
    lucide.createIcons();

    function openReportModal(recipeId, recipeName) {
    document.getElementById('report_recipe_id').value = recipeId;
    document.getElementById('reportRecipeName').textContent = '"' + recipeName + '"';
    document.getElementById('report_reason').value = '';
    document.getElementById('reportForm').reset();
    document.getElementById('report_msg').style.display = 'none';
    document.getElementById('reportModal').classList.add('open');
    }
    function closeReportModal() {
        document.getElementById('reportModal').classList.remove('open');
    }
    document.getElementById('reportModal').addEventListener('click', function(e) {
        if (e.target === this) closeReportModal();
    });

    function submitReport(e) {
        e.preventDefault();
        const msg = document.getElementById('report_msg');
        const formData = new FormData(document.getElementById('reportForm'));
        formData.append('csrf_token', window.SHELFLIFE_CSRF);
        fetch('report_recipe.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                msg.style.display = 'block';
                if (data.success) {
                    msg.style.color = 'var(--green-700)';
                    msg.textContent = data.auto_hidden
                        ? '✓ Report submitted. Recipe has been hidden pending review.'
                        : '✓ Report submitted. Thank you.';
                    setTimeout(() => { closeReportModal(); location.reload(); }, 1600);
                } else {
                    msg.style.color = 'var(--red-600)';
                    msg.textContent = data.error || 'Could not submit report.';
                }
            })
            .catch(() => {
                msg.style.display = 'block';
                msg.style.color = 'var(--red-600)';
                msg.textContent = 'Could not submit report.';
            });
    }

    function openRecipeModal(id, name, ingredients, instructions, isPublic) {
        const modal = document.getElementById('recipeModal');
        if (id) {
            document.getElementById('form_recipe_id').value = id;
            document.getElementById('recipe_name').value = name || '';
            document.getElementById('ingredients').value = ingredients || '';
            document.getElementById('instructions').value = instructions || '';
            document.getElementById('is_public').checked = isPublic === 1;
            document.getElementById('modalTitle').textContent = 'Edit recipe';
        } else {
            document.getElementById('form_recipe_id').value = '';
            document.getElementById('recipeForm').reset();
            document.getElementById('is_public').checked = true;
            document.getElementById('modalTitle').textContent = 'Share a recipe';
        }
        modal.classList.add('open');
    }
    function closeRecipeModal() {
        document.getElementById('recipeModal').classList.remove('open');
    }
    document.getElementById('recipeModal').addEventListener('click', function(e) {
        if (e.target === this) closeRecipeModal();
    });

    function toggleSave(recipeId, btn) {
        const form = new FormData();
        form.append('recipe_id', recipeId);
        form.append('csrf_token', window.SHELFLIFE_CSRF);
        fetch('toggle_save_recipe.php', { method: 'POST', body: form })
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                const countSpan = btn.querySelector('.save-count');
                countSpan.textContent = data.count;
                btn.classList.toggle('saved', data.saved);
                btn.innerHTML = '<i data-lucide="' + (data.saved ? 'bookmark-check' : 'bookmark') + '" style="width:12px;height:12px;"></i><span class="save-count">' + data.count + '</span>';
                lucide.createIcons();
            });
    }

    function copyRecipe(recipeId, recipeName) {
        if (!confirm('Add "' + recipeName + '" to your recipes?')) return;

        const form = new FormData();
        form.append('recipe_id', recipeId);
        form.append('csrf_token', window.SHELFLIFE_CSRF);
        fetch('copy_recipe.php', { method: 'POST', body: form })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Recipe added to your collection.');
                    window.location.href = 'recipes.php?tab=mine';
                } else {
                    alert('Could not copy recipe.');
                }
            });
    }
</script>
</body>
</html>