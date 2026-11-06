<?php
/**
 * mobile_nav.php — Mobile top bar + bottom tab bar
 *
 * Include right before </body> on any page that has <nav class="topnav">.
 * On mobile (≤768px), the desktop topnav hides and this takes over.
 * On desktop, this entire component is hidden by CSS.
 */

if (!isset($_SESSION['user_id'])) return;

$m_current  = basename($_SERVER['SCRIPT_NAME'] ?? '');
$m_is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$m_user_id  = (int)$_SESSION['user_id'];

// Avatar + username
$m_user = null;
if (isset($conn)) {
    $q = mysqli_query($conn, "SELECT username, profile_pic FROM users WHERE id = '$m_user_id'");
    $m_user = $q ? mysqli_fetch_assoc($q) : null;
}
$m_name     = $m_user['username'] ?? ($_SESSION['username'] ?? 'user');
$m_pic      = $m_user['profile_pic'] ?? 'default_avatar.jpg';
$m_avatar   = "uploads/" . htmlspecialchars($m_pic);
$m_fallback = "https://ui-avatars.com/api/?name=" . urlencode($m_name) . "&background=2d5a3d&color=fff";

// Which tab is active
$m_on_pantry  = in_array($m_current, ['dashboard.php','shopping_list.php','incoming_groceries.php','edit_item.php','bulk_edit.php','waste_log.php'], true);
$m_on_recipes = in_array($m_current, ['recipes.php','recipe_recommendations.php','admin_recipes.php'], true);
$m_on_meal    = ($m_current === 'meal_planner.php');
$m_on_support = ($m_current === 'chat.php');
$m_on_admin   = ($m_current === 'admin.php');

// FAB only on the main pantry page
$m_show_fab = ($m_current === 'dashboard.php');
?>
<style>
.m-topbar, .m-tabbar, .m-fab { display: none; }

@media (max-width: 768px) {
    .topnav { display: none !important; }
    main { padding-bottom: 100px !important; }

    .m-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 16px;
        background: var(--surface);
        border-bottom: 1px solid var(--border);
        position: sticky;
        top: 0;
        z-index: 200;
    }
    .m-topbar .m-brand {
        display: flex; align-items: center; gap: 10px;
        font-family: var(--font-display);
        font-weight: 700;
        font-size: 16px;
        letter-spacing: -0.02em;
        color: var(--ink-900);
        text-decoration: none;
    }
    .m-topbar .m-brand-icon {
        width: 32px; height: 32px;
        border-radius: var(--r-sm);
        background: var(--green-500);
        display: flex; align-items: center; justify-content: center;
        color: white;
    }
    .m-topbar .m-brand-icon svg { width: 18px; height: 18px; }

    .m-topbar .m-avatar-link {
        display: flex; align-items: center;
        text-decoration: none;
    }
    .m-topbar .m-avatar {
        width: 38px; height: 38px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--green-500);
    }

    .m-tabbar {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        position: fixed;
        left: 0; right: 0; bottom: 0;
        background: var(--surface);
        border-top: 1px solid var(--border);
        padding: 6px 4px calc(6px + env(safe-area-inset-bottom, 0px));
        z-index: 900;
        box-shadow: 0 -2px 12px rgba(26,31,28,0.05);
    }
    .m-tab {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 3px;
        padding: 8px 4px;
        text-decoration: none;
        color: var(--ink-500);
        font-size: 10.5px;
        font-weight: 600;
        letter-spacing: 0.01em;
        transition: color 0.15s;
        border-radius: var(--r-sm);
        font-family: var(--font-body);
    }
    .m-tab svg { width: 22px; height: 22px; stroke-width: 1.8; }
    .m-tab.active { color: var(--green-600); }
    .m-tab.active svg { stroke-width: 2.2; }
    .m-tab:active { background: var(--sand-100); }

    .m-fab {
        display: flex;
        align-items: center;
        justify-content: center;
        position: fixed;
        right: 18px;
        bottom: calc(84px + env(safe-area-inset-bottom, 0px));
        width: 54px; height: 54px;
        border-radius: 50%;
        background: var(--green-500);
        color: white;
        text-decoration: none;
        box-shadow: 0 6px 18px rgba(45,90,61,0.35);
        z-index: 950;
        transition: transform 0.1s, background 0.15s;
    }
    .m-fab:active { transform: scale(0.94); }
    .m-fab svg { width: 24px; height: 24px; stroke-width: 2.2; }
}
</style>

<!-- Mobile top bar -->
<div class="m-topbar">
    <a href="dashboard.php" class="m-brand">
        <span class="m-brand-icon"><i data-lucide="leaf"></i></span>
        ShelfLife
    </a>
    <a href="profile.php" class="m-avatar-link" title="Profile">
        <img src="<?php echo $m_avatar; ?>"
             class="m-avatar"
             alt="Profile"
             onerror="this.src='<?php echo $m_fallback; ?>'">
    </a>
</div>

<?php if ($m_show_fab): ?>
<a href="index.php" class="m-fab" title="Add item">
    <i data-lucide="plus"></i>
</a>
<?php endif; ?>

<!-- Mobile bottom tab bar -->
<nav class="m-tabbar">
    <a href="dashboard.php" class="m-tab <?php echo $m_on_pantry ? 'active' : ''; ?>">
        <i data-lucide="package"></i>
        <span>Pantry</span>
    </a>
    <a href="recipes.php" class="m-tab <?php echo $m_on_recipes ? 'active' : ''; ?>">
        <i data-lucide="chef-hat"></i>
        <span>Recipes</span>
    </a>
    <a href="meal_planner.php" class="m-tab <?php echo $m_on_meal ? 'active' : ''; ?>">
        <i data-lucide="calendar"></i>
        <span>Meal plan</span>
    </a>
    <?php if ($m_is_admin): ?>
        <a href="admin.php" class="m-tab <?php echo $m_on_admin ? 'active' : ''; ?>">
            <i data-lucide="shield"></i>
            <span>Admin</span>
        </a>
    <?php else: ?>
        <a href="chat.php" class="m-tab <?php echo $m_on_support ? 'active' : ''; ?>">
            <i data-lucide="message-circle"></i>
            <span>Support</span>
        </a>
    <?php endif; ?>
</nav>

<script>if (typeof lucide !== 'undefined') lucide.createIcons();</script>