<?php
session_start();

// 1. FIRST: Check if they are logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. SECOND: Now that we KNOW they are logged in, set the variable
$user_id = $_SESSION['user_id']; 

include "db_connect.php";
$today = date("Y-m-d");

$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$view = isset($_GET['view']) ? $_GET['view'] : 'pantry'; // Handle tab switching

$categories = ['Dairy', 'Meat/Poultry', 'Grains/Canned', 'Fruits', 'Vegetables', 'Bakery', 'Pasta/Grains', 'Condiments', 'Spices/Seasonings', 'Snacks/Drinks', 'General'];

function normalize($name) {
    $name = strtolower(trim($name));
    $name = str_replace(['leaves', 's'], '', $name); 
    return $name;
}

// UPDATED STATS: Added user_id filter to all counts
$user_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT username, profile_pic FROM users WHERE id = '$user_id'"));
$stat_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM inventory WHERE is_bought=1 AND user_id = '$user_id'"))['total'];
$stat_urgent = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM inventory WHERE is_bought=1 AND user_id = '$user_id' AND expiry_date IS NOT NULL AND DATEDIFF(expiry_date, '$today') <= 3 AND DATEDIFF(expiry_date, '$today') >= 0"))['total'];
$stat_expired = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM inventory WHERE is_bought=1 AND user_id = '$user_id' AND expiry_date < '$today'"))['total'];

$search_results_count = 0;
if ($search !== '') {
    // UPDATED: Added user_id filter to search check
    $search_check = mysqli_query($conn, "SELECT COUNT(*) as total FROM inventory WHERE is_bought = 1 AND user_id = '$user_id' AND item_name LIKE '%$search%'");
    $search_results_count = mysqli_fetch_assoc($search_check)['total'];
}
?>      

<!DOCTYPE html>
<html lang="en">
<head>
    <div class="user-profile-bar">
    <a href="profile.php" class="user-info">
        <span class="user-name"><?php echo strtoupper($user_info['username']); ?></span>
        <img src="uploads/<?php echo $user_info['profile_pic']; ?>" class="user-avatar" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo $user_info['username']; ?>&background=6B8E23&color=fff'">
    </a>
    
    </div>
    <meta charset="UTF-8">
    <title>ShelfLife - Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;800&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    
    <style>
        
        .user-profile-bar {
             display: flex; align-items: center; justify-content: flex-end;
             padding: 5px 20px;     
             position: sticky; top: 0; z-index: 1500;
        }
        .user-info { display: flex; align-items: center; gap: 12px; text-shadow: 0 0 10px #0fa, 0 0 20px #0fa, 0 0 30px #0fa; color: #2F2F2F; }
        .user-avatar { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; border: 2px solid #6B8E23; }
        .user-name { font-family: 'Montserrat'; font-weight: 800; font-size: 0.85em; }
        .logout-btn { font-size: 0.75em; color: #e74c3c; text-decoration: none; font-weight: bold; margin-left: 15px; }
        
        body {
            font-family: 'Open Sans', sans-serif; margin: 0; padding: 15px; padding-bottom: 120px;
            background-color: #f4f7f6; 
            background-image: linear-gradient(rgba(0, 0, 0, 0.1), rgba(0, 0, 0, 0.1)), url('dashboard.JPG'); 
            background-size: cover; background-position: center; background-attachment: fixed;
        }

        /* TAB SYSTEM STYLES */
        .tabs-container { display: flex; justify-content: center; gap: 5px; margin-bottom: 25px; }
        .tab { 
            padding: 12px 25px; border-radius: 15px 15px 0 0; border: none; cursor: pointer; 
            font-family: 'Montserrat'; font-weight: 800; font-size: 0.8em; transition: 0.3s;
            background: rgba(255,255,255,0.4); color: #7f8c8d; text-decoration: none;
            backdrop-filter: blur(5px);
        }
        .tab.active { background: rgba(255,255,255,0.9); color: #6B8E23; border-bottom: 4px solid #6B8E23; }
        .tab-expired.active { color: #e74c3c; border-bottom-color: #e74c3c; }

        .pantry-grid::-webkit-scrollbar { height: 6px; }
        .pantry-grid::-webkit-scrollbar-track { background: rgba(0,0,0,0.05); border-radius: 10px; }
        .pantry-grid::-webkit-scrollbar-thumb { background: rgba(107, 142, 35, 0.4); border-radius: 10px; }

        .shelf-content::-webkit-scrollbar { width: 4px; }
        .shelf-content::-webkit-scrollbar-track { background: transparent; }
        .shelf-content::-webkit-scrollbar-thumb { background: rgba(107, 142, 35, 0.2); border-radius: 10px; }

        .menu-container { display: flex; gap: 10px; justify-content: center; margin-bottom: 20px; }
        .menu-box { background-color: rgba(249, 247, 242, 0.9); padding: 10px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); text-align: center; width: 130px; text-decoration: none; color: #2F2F2F; transition: 0.3s; font-family: 'Montserrat'; font-size: 0.85em; }
        
        .stats-bar { display: flex; justify-content: center; gap: 10px; margin-bottom: 25px; font-family: 'Montserrat'; }
        .stat-card { background: white; padding: 8px 15px; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.08); text-align: center; min-width: 100px; border: 1px solid #eee; }

        .search-container { text-align: center; margin-bottom: 30px; position: relative; max-width: 400px; margin-left: auto; margin-right: auto; }
        .search-wrapper { position: relative; display: flex; align-items: center; }
        .search-input { padding: 10px 15px; width: 100%; border-radius: 20px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.1); font-size: 0.9em; }
        .clear-btn { position: absolute; right: 90px; background: none; border: none; color: #999; font-size: 1.1em; cursor: pointer; display: <?php echo $search ? 'block' : 'none'; ?>; }
        .search-btn { margin-left: 8px; padding: 10px 20px; border-radius: 20px; border: none; background: #6B8E23; color: white; cursor: pointer; font-family: 'Montserrat'; font-weight: bold; font-size: 0.85em; }

        .pantry-grid { display: flex; overflow-x: auto; gap: 15px; padding: 10px 10px 30px 10px; align-items: flex-start; scroll-behavior: smooth; }
        
        .category-column { 
            flex: 0 0 240px; 
            background: rgba(255, 255, 255, 0.8); 
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 20px; 
            padding: 15px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.1), inset 0 2px 10px rgba(0,0,0,0.05); 
            height: 480px; 
            border: 1px solid rgba(255,255,255,0.4); 
            display: flex;
            flex-direction: column;
            transition: 0.3s ease;
        }
        
        .column-header { 
            font-family: 'Montserrat'; font-size: 0.8em; text-align: center; color: #2F2F2F; 
            padding-bottom: 10px; border-bottom: 3px solid #6B8E23; margin-bottom: 15px; 
            text-transform: uppercase; letter-spacing: 1.5px; font-weight: 800;
            flex-shrink: 0;
        }

        .shelf-content {
            overflow-y: auto;
            flex-grow: 1;
            padding-right: 5px;
            display: flex;
            flex-direction: column;
        }
        
        .food-card { 
            background: white; margin-bottom: 10px; padding: 12px; border-radius: 12px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.02); border-left: 6px solid #ccc; 
            cursor: pointer; display: flex; align-items: center; gap: 10px; transition: 0.2s; 
            text-transform: capitalize; flex-shrink: 0;
        }
        .food-card:hover { transform: scale(1.03); box-shadow: 0 6px 12px rgba(0,0,0,0.06); }
        .food-card.selected { background-color: #f0f4e8; border-right: 3px solid #6B8E23; }
        
        .item-checkbox { cursor: pointer; width: 14px; height: 14px; flex-shrink: 0; accent-color: #6B8E23; }

        .fresh { border-left-color: #2ecc71; }
        .warning { border-left-color: #f1c40f; background: #fffdf5; }
        .expired { border-left-color: #e74c3c; background: #fff5f5; }
        
        .empty-placeholder { 
            text-align: center; color: #7f8c8d; font-size: 0.75em; 
            margin: auto 0; 
            display: flex; flex-direction: column; align-items: center; gap: 8px; opacity: 0.6;
        }

        .expired-container { 
            max-width: 900px; margin: 0 auto; background: rgba(255,255,255,0.9); 
            padding: 30px; border-radius: 25px; box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }

        .suggestions-section { margin-top: 40px; background: rgba(255, 255, 255, 0.98); padding: 25px; border-radius: 25px; box-shadow: 0 -10px 40px rgba(0,0,0,0.05); }
        .recipe-box { background: #F9F7F2; padding: 15px; border-radius: 12px; border-left: 5px solid #E67E22; margin-bottom: 12px; transition: 0.3s; cursor: pointer; }
        
        .action-bar { position: fixed; bottom: -100px; left: 50%; transform: translateX(-50%); background: #2F2F2F; color: white; padding: 15px 35px; border-radius: 50px; display: flex; align-items: center; gap: 20px; transition: 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); z-index: 1000; box-shadow: 0 15px 35px rgba(0,0,0,0.4); }
        .action-bar.active { bottom: 25px; }
        .bar-btn { border: none; padding: 10px 22px; border-radius: 25px; font-weight: bold; cursor: pointer; color: white; font-family: 'Montserrat'; font-size: 0.85em; }

        /* MODAL STYLES */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.6); backdrop-filter: blur(5px);
            display: none; justify-content: center; align-items: center; z-index: 2000;
        }
        .modal-content {
            background: white; width: 90%; max-width: 600px; max-height: 80vh;
            border-radius: 25px; overflow-y: auto; position: relative;
            padding: 40px; box-shadow: 0 20px 50px rgba(0,0,0,0.3);
            animation: modalFade 0.3s ease;
        }
        .close-modal {
            position: absolute; top: 20px; right: 20px; font-size: 1.5em;
            background: none; border: none; cursor: pointer; color: #7f8c8d;
        }
        @keyframes modalFade {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
</head>
<body>
    <h2 style="text-align:center; font-family:'Montserrat'; letter-spacing:4px; color: #2F2F2F; margin-top: 20px; font-weight: 800; font-size: 1.3em;">🏠 SHELFLIFE PANTRY</h2>
    
    <div class="menu-container">
        <a href="index.php" class="menu-box">🍎 Add Food</a>
        <a href="shopping_list.php" class="menu-box">🛒 To-Buy</a>
    </div>

    <div class="tabs-container">
        <a href="?view=pantry" class="tab <?php echo $view == 'pantry' ? 'active' : ''; ?>">📦 MY SHELVES</a>
        <a href="?view=expired" class="tab tab-expired <?php echo $view == 'expired' ? 'active' : ''; ?>">⚠️ WASTE BIN (<?php echo $stat_expired; ?>)</a>
    </div>

    <?php if($view == 'pantry'): ?>
    <div class="stats-bar">
        <div class="stat-card">
            <span style="display: block; font-size: 1.5em; font-weight: 800; color: #6B8E23;"><?php echo $stat_total; ?></span>
            <span style="font-size: 0.6em; color: #7f8c8d; text-transform: uppercase; font-weight: bold;">Items</span>
        </div>
        <div class="stat-card" style="border-bottom: 4px solid #e74c3c;">
            <span style="display: block; font-size: 1.5em; font-weight: 800; color: #e74c3c;"><?php echo $stat_urgent; ?></span>
            <span style="font-size: 0.6em; color: #7f8c8d; text-transform: uppercase; font-weight: bold;">Expiring</span>
        </div>
    </div>

    <div class="search-container">
        <form action="dashboard.php" method="GET" class="search-wrapper">
            <input type="hidden" name="view" value="pantry">
            <input type="text" name="search" id="searchInput" class="search-input" placeholder="Search shelves..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="button" class="clear-btn" id="clearSearch" onclick="window.location.href='dashboard.php?view=pantry'">✖</button>
            <button type="submit" class="search-btn">🔍</button>
        </form>
    </div>
    <?php endif; ?>

    <form id="bulkForm" method="POST">
        <?php if($view == 'pantry'): ?>
            <div class="pantry-grid">
                <?php 
                if ($stat_total == 0 && $search == ''): ?>
                    <div style="text-align: center; width: 100%; padding: 60px 20px;">
                        <div style="font-size: 6em; margin-bottom: 20px;">📦</div>
                        <h3 style="font-family: 'Montserrat'; color: #2F2F2F; font-weight: 800;">Your pantry is empty</h3>
                        <a href="index.php" style="display: inline-block; padding: 15px 35px; background: #6B8E23; color: white; text-decoration: none; border-radius: 30px; font-family: 'Montserrat'; font-weight: bold;">+ Add First Item</a>
                    </div>
                <?php 
                else: 
                    foreach ($categories as $cat): 
                        $cat_esc = mysqli_real_escape_string($conn, $cat);
                        // Query filtered by user_id
                        $query = "SELECT MIN(id) as display_id, item_name, SUM(quantity) as total_quantity, expiry_date 
                                  FROM inventory 
                                  WHERE user_id = '$user_id' 
                                  AND category = '$cat_esc' 
                                  AND is_bought = 1 
                                  AND (expiry_date >= '$today' OR expiry_date IS NULL)
                                  AND item_name LIKE '%$search%' 
                                  GROUP BY item_name, expiry_date 
                                  ORDER BY expiry_date ASC";
                        $items = mysqli_query($conn, $query);
                ?>
                        <div class="category-column">
                            <div class="column-header"><?php echo $cat; ?></div>
                            <div class="shelf-content">
                                <?php if (mysqli_num_rows($items) > 0):
                                    while ($row = mysqli_fetch_assoc($items)): 
                                        $diff = ($row['expiry_date']) ? (strtotime($row['expiry_date']) - strtotime($today)) / 86400 : 999;
                                        $status_class = ($diff <= 3) ? "warning" : "fresh"; ?>
                                        <label class="food-card <?php echo $status_class; ?>">
                                            <input type="checkbox" name="item_ids[]" value="<?php echo $row['display_id']; ?>" class="item-checkbox">
                                            <div style="flex-grow:1; min-width: 0;">
                                                <span style="font-weight:600; font-size: 0.85em; color: #2F2F2F; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($row['item_name']); ?> (x<?php echo $row['total_quantity']; ?>)</span>
                                                <span style="display:block; font-size:0.7em; color:#7f8c8d; margin-top: 4px; font-weight: 600;"><?php echo ($row['expiry_date'] == NULL) ? "NO EXPIRATION" : floor($diff)."d left"; ?></span>
                                            </div>
                                        </label>
                                    <?php endwhile; 
                                else: ?>
                                    <div class="empty-placeholder">
                                        <span>Shelf Empty</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; 
                endif; ?>
            </div>
        <?php else: ?>
            <div class="expired-container">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <div>
                        <h3 style="font-family:'Montserrat'; color:#e74c3c; margin:0; font-weight: 800;">🗑️ WASTE BIN</h3>
                        <p style="font-size:0.85em; color:#7f8c8d; margin-top:5px;">Clear out items that have already expired.</p>
                    </div>
                    <?php if($stat_expired > 0): ?>
                    <button type="button" onclick="toggleAll(true)" style="background:#eee; border:none; padding:8px 15px; border-radius:10px; font-family:'Montserrat'; font-size:0.75em; font-weight:bold; cursor:pointer;">Select All</button>
                    <?php endif; ?>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px;">
                    <?php 
                    $expired_q = mysqli_query($conn, "SELECT * FROM inventory WHERE is_bought=1 AND user_id = '$user_id' AND expiry_date < '$today' ORDER BY expiry_date ASC");
                    if(mysqli_num_rows($expired_q) > 0):
                        while($exp = mysqli_fetch_assoc($expired_q)): ?>
                            <label class="food-card expired">
                                <input type="checkbox" name="item_ids[]" value="<?php echo $exp['id']; ?>" class="item-checkbox">
                                <div style="flex-grow:1;">
                                    <span style="font-weight:700; font-size: 0.9em; display:block;"><?php echo htmlspecialchars($exp['item_name']); ?></span>
                                    <span style="font-size:0.75em; color:#c0392b; font-weight:bold;">Expired <?php echo date('M d', strtotime($exp['expiry_date'])); ?></span>
                                </div>
                            </label>
                        <?php endwhile;
                    else: ?>
                        <div style="grid-column: 1/-1; text-align: center; padding: 50px;">
                            <span style="font-size: 4em;">✨</span>
                            <h4 style="font-family:'Montserrat'; color:#6B8E23;">No expired food!</h4>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if($view == 'pantry'): ?>
        <div class="suggestions-section">
            <h3 style="font-family:'Montserrat'; color:#2F2F2F; border-bottom: 3px solid #6B8E23; padding-bottom: 12px; margin-bottom: 30px; font-weight: 800;">✨ SMART SUGGESTIONS</h3>
            <div class="suggestion-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px;">
                <div class="suggestion-group">
                    <h4 style="color:#e74c3c; font-family: 'Montserrat'; font-size: 1em; margin-bottom: 20px;">🚨 USE THESE SOON</h4>
                    <?php
                    $q_waste = mysqli_query($conn, "SELECT id, item_name, expiry_date FROM inventory 
                                                   WHERE is_bought=1 AND user_id = '$user_id' AND expiry_date IS NOT NULL 
                                                   AND DATEDIFF(expiry_date, '$today') <= 3 
                                                   AND DATEDIFF(expiry_date, '$today') >= 0 
                                                   ORDER BY expiry_date ASC");
                    if(mysqli_num_rows($q_waste) > 0){
                        while($w = mysqli_fetch_assoc($q_waste)){
                            $d = (strtotime($w['expiry_date']) - strtotime($today)) / 86400;
                            echo "<label class='food-card expired'>
                                    <input type='checkbox' name='item_ids[]' value='{$w['id']}' class='item-checkbox'>
                                    <div style='flex-grow:1;'>
                                        <strong style='color: #2F2F2F; display: block;'>{$w['item_name']}</strong>
                                        <span style='font-size:0.85em; color:#c0392b; font-weight: bold;'>Expires in ".floor($d)." days</span>
                                    </div>
                                  </label>";
                        }
                    } else {
                        echo "<p style='font-size:0.9em; color:#7f8c8d; font-style: italic;'>No urgent items. Great job!</p>";
                    }
                    ?>
                </div>

                <div class="suggestion-group">
                    <h4 style="color:#6B8E23; font-family: 'Montserrat'; font-size: 1em; margin-bottom: 20px;">💡 AVAILABLE RECIPES</h4>
                    <?php
                    $q_all = mysqli_query($conn, "SELECT DISTINCT item_name FROM inventory WHERE is_bought=1 AND user_id = '$user_id' AND (expiry_date >= '$today' OR expiry_date IS NULL)");
                    $all_norm = []; 
                    while($r = mysqli_fetch_assoc($q_all)) { $all_norm[] = normalize($r['item_name']); }
                    
                    if(!empty($all_norm)){
                        $recipes = mysqli_query($conn, "SELECT * FROM recipes LIMIT 5");
                        $recipe_found = false;
                        while($rec = mysqli_fetch_assoc($recipes)){
                            $needed = array_map('normalize', explode(',', $rec['ingredients']));
                            $matches = array_intersect($needed, $all_norm);
                            if(count($matches) > 0){
                                $recipe_found = true;
                                $ready = (count($matches) == count($needed)) ? "✅ READY TO COOK" : "⚠️ NEED ". (count($needed) - count($matches)) ." MORE";
                                echo "<a href='recipe_recommendations.php?recipe_id={$rec['id']}' class='recipe-link' style='text-decoration:none; color:inherit;'>";
                                echo "<div class='recipe-box'>
                                        <h4 style='margin:0; color:#2F2F2F; font-weight: 800;'>{$rec['recipe_name']}</h4>
                                        <p style='font-size:0.85em; margin:8px 0 0 0; font-weight: bold; color: #6B8E23; letter-spacing: 0.5px;'>$ready</p>
                                      </div>";
                                echo "</a>";
                            }
                        }
                        if(!$recipe_found) echo "<p style='font-size:0.9em; color:#7f8c8d; font-style: italic;'>Add more items to unlock recipes!</p>";
                    } else {
                        echo "<p style='font-size:0.9em; color:#7f8c8d;'>Add food to see what you can cook.</p>";
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div id="action-bar" class="action-bar">
            <span id="selected-count" style="font-family: 'Montserrat'; font-size: 0.85em; font-weight: 800;">0 SELECTED</span>
            <div style="display: flex; gap: 10px;">
                <?php if($view == 'expired'): ?>
                    <button type="submit" formaction="bulk_delete.php" class="bar-btn" style="background:#e74c3c;">🗑️ Discard Items</button>
                <?php else: ?>
                    <button type="submit" formaction="recipe_recommendations.php" class="bar-btn" style="background:#E67E22;">🍳 Cook</button>
                    <button type="submit" formaction="bulk_edit.php" class="bar-btn" style="background:#6B8E23;">✏️ Edit</button>
                    <button type="submit" formaction="bulk_delete.php" class="bar-btn" style="background:#E2725B;">🗑️ Delete</button>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <div id="recipeModal" class="modal-overlay">
        <div class="modal-content">
            <button class="close-modal" id="closeModal">&times;</button>
            <div id="modalBody">
                <p>Loading recipe...</p>
            </div>
        </div>
    </div>

    <script>
        function updateActionBar() {
            const count = document.querySelectorAll('.item-checkbox:checked').length;
            document.getElementById('selected-count').innerText = count + (count === 1 ? " ITEM SELECTED" : " ITEMS SELECTED");
            document.getElementById('action-bar').classList.toggle('active', count > 0);
        }

        document.querySelectorAll('.food-card').forEach(card => {
            card.addEventListener('click', function(e) {
                const checkbox = this.querySelector('.item-checkbox');
                if(e.target !== checkbox) checkbox.checked = !checkbox.checked;
                this.classList.toggle('selected', checkbox.checked);
                updateActionBar();
            });
        });

        function toggleAll(checked) {
            document.querySelectorAll('.item-checkbox').forEach(cb => {
                cb.checked = checked;
                cb.parentElement.classList.toggle('selected', checked);
            });
            updateActionBar();
        }

        const modal = document.getElementById('recipeModal');
        const modalBody = document.getElementById('modalBody');
        const closeModal = document.getElementById('closeModal');

        document.querySelectorAll('.recipe-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const url = this.getAttribute('href');
                modal.style.display = 'flex';
                modalBody.innerHTML = '<p style="text-align:center; font-family:Montserrat;">Fetching recipe...</p>';

                fetch(url)
                    .then(response => response.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        modalBody.innerHTML = doc.body.innerHTML;
                    })
                    .catch(() => {
                        modalBody.innerHTML = '<p>Error loading recipe.</p>';
                    });
            });
        });

        closeModal.onclick = () => modal.style.display = 'none';
        window.onclick = (e) => { if (e.target == modal) modal.style.display = 'none'; }
    </script>
</body>
</html>