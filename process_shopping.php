<?php
require_once "session_config.php";
include "db_connect.php";
require_once "categories_config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if (isset($_POST['bought_ids'])) {
    $ids    = $_POST['bought_ids'];
    $action = $_POST['action'] ?? '';

    if ($action === 'done') {
        foreach ($ids as $id) {
            $id = (int)$id;

            $res = mysqli_query($conn, "SELECT item_name, category FROM inventory WHERE id = $id AND user_id = '$user_id'");
            $row = mysqli_fetch_assoc($res);

            if ($row) {
                if ($row['category'] === 'General' || $row['category'] === '') {
                    $new_cat = shelflife_categorize($row['item_name']);
                } else {
                    $new_cat = $row['category'];
                }
                $new_cat = mysqli_real_escape_string($conn, $new_cat);

                // Sync ALL container columns to match the quantity
                mysqli_query($conn, "UPDATE inventory SET
                                        is_bought = 1,
                                        category = '$new_cat',
                                        expiry_date = NULL,
                                        container_count = quantity,
                                        initial_quantity = quantity,
                                        current_quantity = quantity,
                                        size_per_unit = 1,
                                        size_unit = 'pcs',
                                        unit = 'pcs'
                                     WHERE id = $id AND user_id = '$user_id'");
            }
        }
        header("Location: incoming_groceries.php");
        exit();
    }

    if ($action === 'delete') {
        foreach ($ids as $id) {
            $id = (int)$id;
            mysqli_query($conn, "DELETE FROM inventory WHERE id = $id AND user_id = '$user_id'");
        }
        header("Location: shopping_list.php");
        exit();
    }
}

header("Location: shopping_list.php");
exit();
?>