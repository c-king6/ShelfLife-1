<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if (!isset($_POST['item_ids']) || empty($_POST['item_ids'])) {
    header("Location: dashboard.php");
    exit();
}

$selected_ids = implode(',', array_map('intval', $_POST['item_ids']));

// UPDATED: Added AND user_id = '$user_id' to both queries
$find_match_query = "SELECT * FROM inventory 
                     WHERE (item_name, category, COALESCE(expiry_date, '1900-01-01')) IN 
                     (SELECT item_name, category, COALESCE(expiry_date, '1900-01-01') 
                      FROM inventory WHERE id IN ($selected_ids) AND user_id = '$user_id')
                     AND is_bought = 1 AND user_id = '$user_id'";

$result = mysqli_query($conn, $find_match_query);
$categories = ['Dairy', 'Meat/Poultry', 'Grains/Canned', 'Fruits', 'Vegetables', 'Bakery', 'Pasta/Grains', 'Condiments', 'Snacks/Drinks', 'General'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ShelfLife - Batch Update</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Open Sans', sans-serif; margin: 0; padding: 40px; background-color: #f4f7f6; }
        .batch-container { 
            background: white; padding: 40px; border-radius: 30px; 
            box-shadow: 0 10px 40px rgba(0,0,0,0.05); max-width: 900px; margin: auto; 
            text-align: center;
        }
        .header-section { margin-bottom: 30px; }
        .header-section h2 { font-family: 'Montserrat'; color: #2F2F2F; margin: 0; text-transform: uppercase; letter-spacing: 1px; }
        
        .labels { display: flex; justify-content: space-between; padding: 0 15px; margin-bottom: 10px; color: #6B8E23; font-weight: bold; font-size: 0.85em; text-align: left; }
        .col-name { flex: 3; }
        .col-qty { flex: 1; margin-left: 15px; }
        .col-cat { flex: 2; margin-left: 15px; }
        .col-date { flex: 2; margin-left: 15px; }

        .item-row { display: flex; gap: 15px; margin-bottom: 15px; align-items: center; padding-top: 15px; border-top: 1px solid #eee; }
        input, select { padding: 10px; border: 1px solid #ccc; border-radius: 8px; font-family: 'Open Sans'; font-size: 0.9em; width: 100%; box-sizing: border-box; }
        .input-qty { text-align: center; background: #f9f9f9; color: #555; font-weight: bold; border: 1px solid #eee; }

        .apply-btn { background: #6B8E23; color: white; padding: 15px; border: none; border-radius: 12px; width: 100%; font-family: 'Montserrat'; font-weight: bold; font-size: 1.1em; cursor: pointer; margin-top: 20px; transition: 0.3s; }
        .apply-btn:hover { background: #556B2F; }
        .discard-link { display: block; margin-top: 20px; color: #888; text-decoration: none; font-size: 0.9em; }
    </style>
</head>
<body>

<div class="batch-container">
    <div class="header-section">
        <h2>✏️ Batch Update</h2>
    </div>

    <form action="process_bulk_update.php" method="POST">
        <div class="labels">
            <span class="col-name">Item Name</span>
            <span class="col-qty">Qty</span>
            <span class="col-cat">Category</span>
            <span class="col-date">Expiry Date</span>
        </div>

        <?php 
        while($row = mysqli_fetch_assoc($result)) {
            // We store the ID of every row we are expanding so the processor can delete them
            echo '<input type="hidden" name="old_ids[]" value="'.$row['id'].'">';

            // LOOP: If this row has quantity 6, it will draw 6 individual rows
            for ($i = 0; $i < $row['quantity']; $i++) {
                ?>
                <div class="item-row">
                    <div class="col-name">
                        <input type="text" name="item_name[]" value="<?php echo htmlspecialchars($row['item_name']); ?>" required>
                    </div>
                    <div class="col-qty">
                        <input type="number" name="quantity[]" class="input-qty" value="1" readonly>
                    </div>
                    <div class="col-cat">
                        <select name="category[]">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat; ?>" <?php echo ($row['category'] == $cat) ? 'selected' : ''; ?>>
                                    <?php echo $cat; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-date">
                        <input type="date" name="expiry_date[]" value="<?php echo $row['expiry_date']; ?>">
                    </div>
                </div>
                <?php
            }
        }
        ?>

        <button type="submit" class="apply-btn">Apply All Changes</button>
        <a href="dashboard.php" class="discard-link">Discard Changes</a>
    </form>
</div>

</body>
</html>