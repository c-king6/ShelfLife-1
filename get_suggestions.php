<?php
include "db_connect.php";

// Helper function to match what we have in the dashboard
function normalize($name) {
    $name = strtolower(trim($name));
    $name = str_replace(['leaves', 's'], '', $name); 
    return $name;
}

if (isset($_POST['items'])) {
    $selected_items = json_decode($_POST['items']);
    $normalized_selected = array_map('normalize', $selected_items);

    // Fetch all recipes from your database
    $query = "SELECT * FROM recipes";
    $result = mysqli_query($conn, $query);
    
    $found_any = false;

    if (mysqli_num_rows($result) > 0) {
        while ($recipe = mysqli_fetch_assoc($result)) {
            $needed_ingredients = explode(',', $recipe['ingredients']);
            $normalized_needed = array_map('normalize', $needed_ingredients);

            // Check if any of our selected items are in this recipe
            $matches = array_intersect($normalized_needed, $normalized_selected);

            if (count($matches) > 0) {
                $found_any = true;
                $match_count = count($matches);
                $total_needed = count($normalized_needed);
                
                // Determine status
                $status_text = ($match_count == $total_needed) ? "✅ READY TO COOK" : "⚠️ MATCHES $match_count OF $total_needed ITEMS";
                $status_color = ($match_count == $total_needed) ? "#6B8E23" : "#E67E22";

                // Output the HTML for the recipe card
                echo "
                <a href='recipe_recommendations.php?recipe_id={$recipe['id']}' class='modal-recipe-card'>
                    <h4 style='margin:0; color:#2F2F2F; font-weight: 800;'>{$recipe['recipe_name']}</h4>
                    <p style='font-size:0.75em; margin:5px 0; color:#7f8c8d;'>Includes: " . implode(', ', $matches) . "</p>
                    <p style='font-size:0.8em; margin:0; font-weight:bold; color:$status_color;'>$status_text</p>
                </a>";
            }
        }
    }

    if (!$found_any) {
        echo "<div style='text-align:center; padding:20px;'>
                <p style='font-size:2em;'>🧐</p>
                <p style='color:#7f8c8d;'>No recipes found for those specific items.</p>
              </div>";
    }
} else {
    echo "No items selected.";
}
?>