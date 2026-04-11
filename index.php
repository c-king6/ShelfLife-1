<?php
// 1. START THE SESSION (This must be the very first thing in the file)
session_start();

// 2. CHECK IF USER IS LOGGED IN
if (!isset($_SESSION['user_id'])) {
    // If no session exists, boot them to the login page
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ShelfLife - Add Item</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    
    <style>
    body { 
        font-family: 'Open Sans', sans-serif; 
        margin: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), 
                    url('index.JPG');
        background-size: cover;
        background-position: center;
    }

    .container { 
        width: 100%;
        max-width: 400px; 
        background-color: #F9F7F2; 
        padding: 40px; 
        border-radius: 20px; 
        box-shadow: 0 20px 40px rgba(0,0,0,0.4); 
        text-align: center;
        border: 1px solid rgba(0,0,0,0.05);
    }

    h2 { 
        font-family: 'Montserrat', sans-serif;
        color: #2F2F2F; 
        margin-bottom: 30px; 
        font-size: 2.1em;
        font-weight: 600;
        letter-spacing: 2px;
        text-transform: uppercase;
    }

    label { 
        display: block; 
        text-align: left; 
        font-weight: 600; 
        margin-bottom: 8px; 
        color: #5D5D5D;
        font-size: 0.8em;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    input, select { 
        font-family: 'Open Sans', sans-serif;
        width: 100%; 
        padding: 14px; 
        margin-bottom: 25px; 
        border: 2px solid #E8E4D9; 
        border-radius: 12px; 
        background-color: white;
        font-size: 1em;
        box-sizing: border-box;
        transition: all 0.3s ease;
    }

    input:focus { 
        border-color: #6B8E23; 
        outline: none;
        box-shadow: 0 0 8px rgba(107, 142, 35, 0.2);
    }

    button { 
        font-family: 'Montserrat', sans-serif;
        background-color: #6B8E23; 
        color: white; 
        padding: 16px;
        border: none; 
        border-radius: 12px; 
        width: 100%;
        font-size: 1.1em;
        font-weight: bold;
        cursor: pointer; 
        box-shadow: 0 4px 15px rgba(107, 142, 35, 0.3);
        transition: all 0.3s ease;
    }

    button:hover { 
        background-color: #556B2F; 
        transform: translateY(-3px); 
        box-shadow: 0 8px 20px rgba(107, 142, 35, 0.4);
    }

    .cancel-link { 
        display: inline-block;
        margin-top: 25px; 
        text-decoration: none; 
        color: #8B8B8B; 
        font-size: 0.9em;
        transition: color 0.3s;
    }

    .cancel-link:hover { color: #2F2F2F; }

    /* Small welcome text for the logged in user */
    .user-welcome {
        font-size: 0.8em;
        color: #6B8E23;
        margin-bottom: 10px;
        font-weight: bold;
    }
    </style>
</head>
<body>

<div class="container">
    <div class="user-welcome">HI, <?php echo strtoupper($_SESSION['username']); ?>!</div>
    <h2>🍎 Add to Pantry</h2>
    <form action="save_food.php" method="POST">
        <input type="hidden" name="is_bought" value="1">
        
        <label>Item Name</label>
        <input type="text" name="item_name" placeholder="e.g. Fresh Milk" required>

        <label>Quantity</label>
        <input type="number" name="quantity" value="1" min="1">

        <label>Expiration Date</label>
        <input type="date" name="expiry_date">

        <button type="submit">Stock in Pantry</button>
    </form>
    <a href="dashboard.php" class="cancel-link">Return to My Pantry</a>
</div>

</body>
</html>