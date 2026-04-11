<?php
include "db_connect.php";

// Get the item ID from the URL
if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    $result = mysqli_query($conn, "SELECT * FROM inventory WHERE id = '$id'");
    $item = mysqli_fetch_assoc($result);
} else {
    header("Location: dashboard.php");
    exit();
}

$categories = ['Meat/Poultry', 'Grains/Canned', 'Fruits', 'Vegetables', 'Bakery', 'Pasta/Grains', 'Condiments', 'General'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ShelfLife - Edit Item</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Open Sans', sans-serif; 
            background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('index.JPG'); 
            background-size: cover; 
            display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; 
        }
        .container { 
            background-color: #F9F7F2; padding: 40px; border-radius: 20px; 
            width: 100%; max-width: 400px; box-shadow: 0 20px 40px rgba(0,0,0,0.4); text-align: center; 
        }
        h2 { font-family: 'Montserrat', sans-serif; text-transform: uppercase; letter-spacing: 2px; color: #2F2F2F; margin-bottom: 25px; }
        label { display: block; text-align: left; font-weight: 600; margin-bottom: 5px; color: #5D5D5D; font-size: 0.8em; text-transform: uppercase; }
        input, select { width: 100%; padding: 12px; margin-bottom: 20px; border: 2px solid #E8E4D9; border-radius: 10px; box-sizing: border-box; }
        button { background-color: #6B8E23; color: white; padding: 15px; border: none; border-radius: 10px; width: 100%; font-family: 'Montserrat'; font-weight: bold; cursor: pointer; transition: 0.3s; }
        button:hover { background-color: #556B2F; }
        .cancel-link { display: block; margin-top: 15px; color: #8B8B8B; text-decoration: none; font-size: 0.9em; }
    </style>
</head>
<body>

<div class="container">
    <h2>✏️ Edit Item</h2>
    <form action="update_item.php" method="POST">
        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">

        <label>Item Name</label>
        <input type="text" name="item_name" value="<?php echo htmlspecialchars($item['item_name']); ?>" required>

        <label>Quantity</label>
        <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" required>

        <label>Category (Manual Override)</label>
        <select name="category">
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat; ?>" <?php if($item['category'] == $cat) echo 'selected'; ?>>
                    <?php echo $cat; ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Expiration Date</label>
        <input type="date" name="expiry_date" value="<?php echo $item['expiry_date']; ?>">

        <button type="submit">Update Item</button>
    </form>
    <a href="dashboard.php" class="cancel-link">Cancel and Return</a>
</div>

</body>
</html>