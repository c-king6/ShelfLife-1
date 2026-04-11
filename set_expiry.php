<?php
include "db_connect.php";
$ids = $_GET['ids'];
$result = mysqli_query($conn, "SELECT * FROM inventory WHERE id IN ($ids)");
?>

<!DOCTYPE html>
<html>
<head>
    <title>ShelfLife - Set Dates</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600&family=Open+Sans:wght@400&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Open Sans', sans-serif; background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('index.JPG'); background-size: cover; min-height: 100vh; display: flex; justify-content: center; align-items: center; margin: 0; }
        .container { background: #F9F7F2; padding: 40px; border-radius: 20px; width: 450px; box-shadow: 0 20px 40px rgba(0,0,0,0.4); }
        h2 { font-family: 'Montserrat', sans-serif; text-transform: uppercase; letter-spacing: 2px; text-align: center; }
        .item-row { margin-bottom: 20px; border-bottom: 1px solid #E8E4D9; padding-bottom: 10px; }
        label { display: block; font-weight: 600; font-size: 0.8em; color: #5D5D5D; margin-bottom: 5px; }
        input[type="date"] { width: 100%; padding: 10px; border-radius: 8px; border: 2px solid #E8E4D9; box-sizing: border-box; }
        button { background: #6B8E23; color: white; width: 100%; padding: 15px; border: none; border-radius: 12px; font-family: 'Montserrat'; font-weight: bold; cursor: pointer; margin-top: 20px; }
    </style>
</head>
<body>

<div class="container">
    <h2>📅 Set Expiry Dates</h2>
    <p style="text-align: center; font-size: 0.9em; color: #7f8c8d;">Add dates for your new items to enable alerts.</p>
    
    <form action="finalize_stock.php" method="POST">
        <?php while($row = mysqli_fetch_assoc($result)): ?>
            <div class="item-row">
                <p><strong><?php echo $row['item_name']; ?></strong></p>
                <input type="hidden" name="ids[]" value="<?php echo $row['id']; ?>">
                <label>Expiration Date:</label>
                <input type="date" name="dates[]" required>
            </div>
        <?php endwhile; ?>

        <button type="submit">Complete Stocking</button>
    </form>
</div>

</body>
</html>