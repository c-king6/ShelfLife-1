<?php
require_once "session_config.php";
include "db_connect.php";
include "log_helper.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$msg = "";

// ----- ADD / EDIT official recipe -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['add', 'edit'], true)) {
    $rid   = (int)($_POST['recipe_id'] ?? 0);
    $name  = mysqli_real_escape_string($conn, trim($_POST['recipe_name'] ?? ''));
    $ing   = mysqli_real_escape_string($conn, trim($_POST['ingredients'] ?? ''));
    $instr = mysqli_real_escape_string($conn, trim($_POST['instructions'] ?? ''));

    if ($name !== '' && $ing !== '') {
        if ($_POST['action'] === 'edit' && $rid > 0) {
            mysqli_query($conn, "UPDATE recipes SET recipe_name='$name', ingredients='$ing', instructions='$instr' WHERE id=$rid");
            log_admin_action($conn, $_SESSION['user_id'], 'recipe.update', 'recipe', $rid, "Updated official recipe");
            header("Location: admin_recipes.php?msg=updated"); exit();
        } else {
            mysqli_query($conn, "INSERT INTO recipes (recipe_name, ingredients, instructions, user_id, is_public, is_approved)
                                 VALUES ('$name', '$ing', '$instr', NULL, 1, 1)");
            $new_id = mysqli_insert_id($conn);
            log_admin_action($conn, $_SESSION['user_id'], 'recipe.add', 'recipe', $new_id, "Added official recipe");
            header("Location: admin_recipes.php?msg=added"); exit();
        }
    }
}

// ----- DELETE official recipe -----
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];

    // Fetch name before deleting so we can log it
    $rname_q = mysqli_query($conn, "SELECT recipe_name FROM recipes WHERE id=$id");
    $rname = mysqli_fetch_assoc($rname_q);
    $rname_str = $rname ? $rname['recipe_name'] : ('#' . $id);

    mysqli_query($conn, "DELETE FROM recipes WHERE id=$id");
    mysqli_query($conn, "DELETE FROM recipe_saves WHERE recipe_id=$id");
    mysqli_query($conn, "DELETE FROM recipe_reports WHERE recipe_id=$id");
    mysqli_query($conn, "DELETE FROM meal_plan WHERE recipe_id=$id");

    log_admin_action($conn, $_SESSION['user_id'], 'recipe.delete', 'recipe', $id, "Deleted: $rname_str");
    header("Location: admin_recipes.php?msg=deleted"); exit();
}

// ----- HIDE user recipe -----
if (isset($_GET['hide_id'])) {
    $id = (int)$_GET['hide_id'];

    // Get current state so we know whether we're hiding or unhiding
    $state_q = mysqli_query($conn, "SELECT is_hidden, recipe_name FROM recipes WHERE id=$id");
    $state = mysqli_fetch_assoc($state_q);

    mysqli_query($conn, "UPDATE recipes SET is_hidden = 1 - is_hidden WHERE id=$id");

    $was_hidden = $state ? (int)$state['is_hidden'] : 0;
    $rname_str  = $state ? $state['recipe_name'] : ('#' . $id);
    $action     = $was_hidden ? 'Unhid' : 'Hid';
    log_admin_action($conn, $_SESSION['user_id'], 'recipe.moderate', 'recipe', $id, "$action: $rname_str");

    header("Location: admin_recipes.php?tab=submissions&msg=hidden"); exit();
}

// ----- DELETE user recipe -----
if (isset($_GET['delete_user_recipe'])) {
    $id = (int)$_GET['delete_user_recipe'];

    $rname_q = mysqli_query($conn, "SELECT recipe_name FROM recipes WHERE id=$id AND user_id IS NOT NULL");
    $rname = mysqli_fetch_assoc($rname_q);
    $rname_str = $rname ? $rname['recipe_name'] : ('#' . $id);

    mysqli_query($conn, "DELETE FROM recipes WHERE id=$id AND user_id IS NOT NULL");
    mysqli_query($conn, "DELETE FROM recipe_saves WHERE recipe_id=$id");
    mysqli_query($conn, "DELETE FROM recipe_reports WHERE recipe_id=$id");
    mysqli_query($conn, "DELETE FROM meal_plan WHERE recipe_id=$id");

    log_admin_action($conn, $_SESSION['user_id'], 'recipe.delete', 'recipe', $id, "Deleted user recipe: $rname_str");
    header("Location: admin_recipes.php?tab=submissions&msg=deleted"); exit();
}

// ----- DISMISS a report -----
if (isset($_GET['dismiss_report'])) {
    $rid = (int)$_GET['dismiss_report'];
    mysqli_query($conn, "UPDATE recipe_reports SET status='dismissed' WHERE id=$rid");
    log_admin_action($conn, $_SESSION['user_id'], 'report.dismiss', 'report', $rid, "Dismissed report #$rid");
    header("Location: admin_recipes.php?tab=reports&msg=dismissed"); exit();
}

// ----- ACTION a report (delete recipe + mark report actioned) -----
if (isset($_GET['action_report'])) {
    $rid = (int)$_GET['action_report'];
    $rr = mysqli_query($conn, "SELECT recipe_id FROM recipe_reports WHERE id=$rid");
    $row = mysqli_fetch_assoc($rr);
    if ($row) {
        $recipe_id = (int)$row['recipe_id'];

        // Fetch name before deleting
        $rname_q = mysqli_query($conn, "SELECT recipe_name FROM recipes WHERE id=$recipe_id");
        $rname = mysqli_fetch_assoc($rname_q);
        $rname_str = $rname ? $rname['recipe_name'] : ('#' . $recipe_id);

        mysqli_query($conn, "DELETE FROM recipes WHERE id=$recipe_id AND user_id IS NOT NULL");
        mysqli_query($conn, "DELETE FROM recipe_saves WHERE recipe_id=$recipe_id");
        mysqli_query($conn, "DELETE FROM meal_plan WHERE recipe_id=$recipe_id");
        mysqli_query($conn, "UPDATE recipe_reports SET status='actioned' WHERE recipe_id=$recipe_id");

        log_admin_action($conn, $_SESSION['user_id'], 'report.action', 'recipe', $recipe_id, "Removed reported recipe: $rname_str");
    }
    header("Location: admin_recipes.php?tab=reports&msg=actioned"); exit();
}

// ----- Fetch current tab -----
$tab = $_GET['tab'] ?? 'official';
if (!in_array($tab, ['official', 'submissions', 'reports'], true)) $tab = 'official';

// Counts for tab badges
$count_official    = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM recipes WHERE user_id IS NULL"))['c'];
$count_submissions = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM recipes WHERE user_id IS NOT NULL"))['c'];
$count_reports     = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM recipe_reports WHERE status='pending'"))['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recipe moderation · Admin · ShelfLife</title>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .topnav { position: sticky; top: 0; z-index: 100; background: var(--surface); border-bottom: 1px solid var(--border); padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
        .brand { display: flex; align-items: center; gap: 10px; font-family: var(--font-display); font-weight: 700; font-size: 17px; letter-spacing: -0.02em; color: var(--ink-900); }
        .brand .logo-icon { width: 32px; height: 32px; border-radius: var(--r-sm); background: var(--ink-900); display: flex; align-items: center; justify-content: center; color: white; }
        main { max-width: 1100px; margin: 0 auto; padding: 32px 24px 80px; }
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 20px; flex-wrap: wrap; gap: 16px; }
        .page-header p { color: var(--ink-500); margin-top: 4px; }
        .flash { background: var(--green-50); border: 1px solid var(--green-200); color: var(--green-700); padding: 12px 16px; border-radius: var(--r-md); font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 8px; margin-bottom: 24px; }
        .tabs { display: flex; gap: 4px; border-bottom: 1px solid var(--border); margin-bottom: 24px; overflow-x: auto; }
        .tab { padding: 12px 18px; font-family: var(--font-display); font-weight: 700; font-size: 13px; color: var(--ink-500); border-bottom: 2px solid transparent; margin-bottom: -1px; white-space: nowrap; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .tab:hover { color: var(--ink-800); }
        .tab.active { color: var(--green-700); border-bottom-color: var(--green-500); }
        .tab .badge { background: var(--sand-100); color: var(--ink-600); font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: var(--r-full); }
        .tab.active .badge { background: var(--green-100); color: var(--green-700); }
        .tab .badge.danger { background: var(--red-50); color: var(--red-700); }

        .panel { background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden; }
        .recipe-row { padding: 18px 22px; border-bottom: 1px solid var(--sand-100); display: flex; align-items: flex-start; gap: 16px; }
        .recipe-row:last-child { border-bottom: none; }
        .recipe-row .info { flex-grow: 1; min-width: 0; }
        .recipe-row .title { font-weight: 700; font-size: 14px; color: var(--ink-900); margin-bottom: 4px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .recipe-row .ingredients { font-size: 12px; color: var(--ink-500); margin-bottom: 4px; line-height: 1.5; }
        .recipe-row .instructions { font-size: 12px; color: var(--ink-500); line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .recipe-row .actions { display: flex; gap: 6px; flex-shrink: 0; flex-wrap: wrap; }

        .badge-tag { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: var(--r-full); font-size: 10.5px; font-weight: 700; }
        .badge-tag.hidden { background: var(--red-50); color: var(--red-700); }
        .badge-tag.author { background: var(--sand-100); color: var(--ink-500); }
        .badge-tag.reported { background: #fdf3e8; color: #b45309; }

        .empty-state { padding: 60px 22px; text-align: center; color: var(--ink-500); }

        .modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.5); backdrop-filter: blur(4px); display: none; justify-content: center; align-items: center; z-index: 2000; padding: 20px; }
        .modal-overlay.open { display: flex; }
        .modal-card { background: white; border-radius: var(--r-xl); padding: 28px; width: 100%; max-width: 500px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 50px rgba(0,0,0,0.2); }
        .modal-card h3 { margin-bottom: 18px; font-size: 18px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-weight: 600; font-size: 12px; color: var(--ink-700); margin-bottom: 6px; }
        .field textarea { width: 100%; padding: 12px 14px; border: 1px solid var(--border); border-radius: var(--r-sm); font-family: var(--font-body); font-size: 13px; resize: vertical; box-sizing: border-box; }
    </style>
</head>
<body class="page page--admin">
<?php include "session_guard.php"; ?>
<nav class="topnav">
    <a href="admin.php" class="brand">
        <div class="logo-icon"><i data-lucide="shield"></i></div>
        ShelfLife Admin
    </a>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="admin.php" class="btn btn-secondary btn-sm">
            <i data-lucide="arrow-left" class="lucide-sm"></i> Back to admin
        </a>
    </div>
</nav>

<main>
    <header class="page-header">
        <div>
            <h1>Recipe moderation</h1>
            <p>Manage official recipes, review user submissions, and handle reports.</p>
        </div>
        <?php if ($tab === 'official'): ?>
            <button class="btn btn-primary" onclick="openRecipeModal()">
                <i data-lucide="plus" class="lucide-sm"></i> New official recipe
            </button>
        <?php endif; ?>
    </header>

    <?php if (isset($_GET['msg'])): ?>
        <div class="flash">
            <i data-lucide="check-circle" class="lucide-sm"></i>
            <?php
                $m = $_GET['msg'];
                if ($m === 'added')      echo "Recipe added.";
                elseif ($m === 'updated') echo "Recipe updated.";
                elseif ($m === 'deleted') echo "Recipe deleted.";
                elseif ($m === 'hidden')  echo "Recipe visibility toggled.";
                elseif ($m === 'dismissed') echo "Report dismissed.";
                elseif ($m === 'actioned') echo "Report actioned — recipe removed.";
            ?>
        </div>
    <?php endif; ?>

    <div class="tabs">
        <a href="?tab=official" class="tab <?php echo $tab==='official'?'active':''; ?>">
            <i data-lucide="badge-check" class="lucide-sm"></i> Official
            <span class="badge"><?php echo $count_official; ?></span>
        </a>
        <a href="?tab=submissions" class="tab <?php echo $tab==='submissions'?'active':''; ?>">
            <i data-lucide="users" class="lucide-sm"></i> User submissions
            <span class="badge"><?php echo $count_submissions; ?></span>
        </a>
        <a href="?tab=reports" class="tab <?php echo $tab==='reports'?'active':''; ?>">
            <i data-lucide="flag" class="lucide-sm"></i> Reports
            <span class="badge <?php echo $count_reports > 0 ? 'danger' : ''; ?>"><?php echo $count_reports; ?></span>
        </a>
    </div>

    <div class="panel">
        <?php
        if ($tab === 'official'):
            $q = mysqli_query($conn, "SELECT * FROM recipes WHERE user_id IS NULL ORDER BY recipe_name ASC");
        elseif ($tab === 'submissions'):
            $q = mysqli_query($conn, "SELECT r.*, u.username FROM recipes r LEFT JOIN users u ON r.user_id=u.id WHERE r.user_id IS NOT NULL ORDER BY r.created_at DESC");
        else:
            $q = mysqli_query($conn, "SELECT rr.*, r.recipe_name, r.is_hidden, r.user_id, u.username AS author FROM recipe_reports rr
                                       LEFT JOIN recipes r ON rr.recipe_id=r.id
                                       LEFT JOIN users u ON r.user_id=u.id
                                       WHERE rr.status='pending'
                                       ORDER BY rr.created_at DESC");
        endif;

        if (mysqli_num_rows($q) === 0): ?>
            <div class="empty-state">
                <?php if ($tab === 'reports') echo "No pending reports. Nice work."; ?>
                <?php if ($tab === 'submissions') echo "No user-submitted recipes yet."; ?>
                <?php if ($tab === 'official') echo "No official recipes."; ?>
            </div>
        <?php else: ?>

            <?php if ($tab === 'reports'): ?>
                <?php while ($r = mysqli_fetch_assoc($q)): ?>
                    <div class="recipe-row">
                        <div class="info">
                            <div class="title">
                                <?php echo htmlspecialchars($r['recipe_name']); ?>
                                <?php if ($r['is_hidden']): ?><span class="badge-tag hidden">Auto-hidden</span><?php endif; ?>
                                <span class="badge-tag reported"><?php echo htmlspecialchars($r['reason']); ?></span>
                            </div>
                            <div class="ingredients">
                                <strong>Reported by:</strong> <?php echo htmlspecialchars($r['reporter_id']); ?> &middot;
                                <strong>Author:</strong> <?php echo htmlspecialchars($r['author'] ?? 'Unknown'); ?>
                            </div>
                            <?php if ($r['details']): ?>
                                <div class="instructions">
                                    <strong>Details:</strong> <?php echo htmlspecialchars($r['details']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="actions">
                            <a href="admin_recipes.php?dismiss_report=<?php echo (int)$r['id']; ?>" class="btn btn-secondary btn-sm">
                                <i data-lucide="x" class="lucide-sm"></i> Dismiss
                            </a>
                            <a href="admin_recipes.php?action_report=<?php echo (int)$r['id']; ?>"
                               class="btn btn-sm" style="background: var(--red-50); color: var(--red-700);"
                               onclick="return confirm('Delete this recipe and mark the report as actioned?')">
                                <i data-lucide="trash-2" class="lucide-sm"></i> Remove recipe
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>

            <?php else: ?>
                <?php while ($r = mysqli_fetch_assoc($q)): ?>
                    <div class="recipe-row">
                        <div class="info">
                            <div class="title">
                                <?php echo htmlspecialchars($r['recipe_name']); ?>
                                <?php if ($tab === 'submissions'): ?>
                                    <?php if ($r['is_hidden']): ?><span class="badge-tag hidden">Hidden</span><?php endif; ?>
                                    <span class="badge-tag author">by <?php echo htmlspecialchars($r['username'] ?? 'Unknown'); ?></span>
                                    <?php if (!$r['is_public']): ?><span class="badge-tag author">Private</span><?php endif; ?>
                                    <?php if ($r['report_count'] > 0): ?><span class="badge-tag reported"><?php echo (int)$r['report_count']; ?> report(s)</span><?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <div class="ingredients">
                                <strong>Ingredients:</strong> <?php echo htmlspecialchars($r['ingredients']); ?>
                            </div>
                            <?php if (!empty($r['instructions'])): ?>
                                <div class="instructions">
                                    <strong>Method:</strong> <?php echo htmlspecialchars($r['instructions']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="actions">
                            <?php if ($tab === 'official'): ?>
                                <button class="btn btn-secondary btn-sm"
                                    onclick='openRecipeModal(<?php echo (int)$r["id"]; ?>, <?php echo json_encode($r["recipe_name"]); ?>, <?php echo json_encode($r["ingredients"]); ?>, <?php echo json_encode($r["instructions"]); ?>)'>
                                    <i data-lucide="pencil" class="lucide-sm"></i> Edit
                                </button>
                                <a href="admin_recipes.php?delete_id=<?php echo (int)$r['id']; ?>" class="btn btn-sm"
                                   style="background: var(--red-50); color: var(--red-700);"
                                   onclick="return confirm('Delete this recipe?')">
                                    <i data-lucide="trash-2" class="lucide-sm"></i>
                                </a>
                            <?php else: ?>
                                <a href="admin_recipes.php?hide_id=<?php echo (int)$r['id']; ?>" class="btn btn-secondary btn-sm">
                                    <i data-lucide="<?php echo $r['is_hidden'] ? 'eye' : 'eye-off'; ?>" class="lucide-sm"></i>
                                    <?php echo $r['is_hidden'] ? 'Unhide' : 'Hide'; ?>
                                </a>
                                <a href="admin_recipes.php?delete_user_recipe=<?php echo (int)$r['id']; ?>" class="btn btn-sm"
                                   style="background: var(--red-50); color: var(--red-700);"
                                   onclick="return confirm('Permanently delete this recipe?')">
                                    <i data-lucide="trash-2" class="lucide-sm"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</main>

<!-- Official recipe add/edit modal -->
<div id="recipeModal" class="modal-overlay">
    <div class="modal-card">
        <h3 id="modalTitle">New official recipe</h3>
        <form method="POST" id="recipeForm">
            <input type="hidden" name="action" id="form_action" value="add">
            <input type="hidden" name="recipe_id" id="form_recipe_id" value="">

            <div class="field">
                <label>Recipe name</label>
                <input type="text" id="recipe_name" name="recipe_name" required maxlength="255">
            </div>
            <div class="field">
                <label>Ingredients <span class="text-muted text-xs">(comma-separated)</span></label>
                <textarea id="ingredients" name="ingredients" rows="3" required></textarea>
            </div>
            <div class="field">
                <label>Instructions</label>
                <textarea id="instructions" name="instructions" rows="5"></textarea>
            </div>
            <div style="display: flex; gap: 8px; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" style="flex:1;" onclick="closeRecipeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex:1;">Save recipe</button>
            </div>
        </form>
    </div>
</div>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
    lucide.createIcons();

    function openRecipeModal(id, name, ingredients, instructions) {
        const modal = document.getElementById('recipeModal');
        if (id) {
            document.getElementById('form_action').value = 'edit';
            document.getElementById('form_recipe_id').value = id;
            document.getElementById('recipe_name').value = name || '';
            document.getElementById('ingredients').value = ingredients || '';
            document.getElementById('instructions').value = instructions || '';
            document.getElementById('modalTitle').textContent = 'Edit recipe';
        } else {
            document.getElementById('form_action').value = 'add';
            document.getElementById('form_recipe_id').value = '';
            document.getElementById('recipeForm').reset();
            document.getElementById('modalTitle').textContent = 'New official recipe';
        }
        modal.classList.add('open');
    }
    function closeRecipeModal() { document.getElementById('recipeModal').classList.remove('open'); }
    document.getElementById('recipeModal').addEventListener('click', function(e) {
        if (e.target === this) closeRecipeModal();
    });
</script>

</body>
</html>