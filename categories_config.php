<?php
// SHARED CATEGORIES CONFIG
//
// This is the single source of truth for both (1) the list of valid
// pantry categories, and (2) the keyword-matching logic that guesses a
// category from an item's name.
//
// WHY THIS FILE EXISTS: before this fix, the category list was copied
// into three different files (dashboard.php, edit_item.php,
// bulk_edit.php) and each one had a DIFFERENT set of categories.
// Separately, the auto-categorizer keyword logic was copied into three
// OTHER files (save_food.php, mark_bought.php, process_shopping.php),
// again each with different keyword coverage. That meant the exact
// same item name could get categorized differently depending on which
// page you added it from, and editing an item through one form could
// silently change its category because that form's dropdown didn't
// even have the item's current category as an option.
//
// Every file that needs the category list or the auto-categorizer now
// does `require_once "categories_config.php";` and calls the function/
// array below, instead of keeping its own copy.

// The canonical list of categories, used everywhere a dropdown or
// filter needs to show/accept a category. This matches the 11
// categories dashboard.php already used (the most complete of the
// three original lists).
$SHELFLIFE_CATEGORIES = [
    'Dairy',
    'Meat/Poultry',
    'Grains/Canned',
    'Fruits',
    'Vegetables',
    'Bakery',
    'Pasta/Grains',
    'Condiments',
    'Spices/Seasonings',
    'Snacks/Drinks',
    'General'
];

/**
 * Guess a pantry category from an item's name.
 *
 * This merges the keyword lists that used to be spread across
 * save_food.php, mark_bought.php, and process_shopping.php into one
 * combined list, so no matter which page an item is added or moved
 * through, it gets the same (and the most complete) categorization.
 *
 * @param string $item_name
 * @return string One of the values in $SHELFLIFE_CATEGORIES
 */
function shelflife_categorize($item_name) {
    $name = strtolower($item_name);

    if (preg_match('/milk|cheese|egg|butter|yogurt|dairy|cream|sour cream/', $name)) {
        return "Dairy";
    }
    if (preg_match('/chicken|beef|meat|pork|fish|steak|bacon|ham|turkey|hotdog|sausage|shrimp|seafood|tilapia|bangus/', $name)) {
        return "Meat/Poultry";
    }
    if (preg_match('/apple|banana|orange|fruit|grape|mango|berry|melon|lemon|lime|pear|peach|plum|calamansi|pineapple|papaya/', $name)) {
        return "Fruits";
    }
    if (preg_match('/carrot|potato|onion|garlic|tomato|veg|cabbage|lettuce|broccoli|corn|cucumber|ginger|kale|spinach|celery|mushroom|eggplant|kangkong|pechay|malunggay|okra|sitaw/', $name)) {
        return "Vegetables";
    }
    if (preg_match('/bread|loaf|bun|pastry|cake|cookie|muffin|bagel|bakery|flour/', $name)) {
        return "Bakery";
    }
    if (preg_match('/rice|pasta|noodle|spaghetti|cereal|grain|oats|quinoa/', $name)) {
        return "Pasta/Grains";
    }
    if (preg_match('/pepper|chili|chilis|vinegar|leaf|leaves|spice|seasoning|herb|salt|oil|cinnamon|powder|clove|thyme|rosemary|patis|toyo/', $name)) {
        return "Spices/Seasonings";
    }
    if (preg_match('/ketchup|mayo|mustard|sauce|soy sauce|jam|honey|syrup|dressing/', $name)) {
        return "Condiments";
    }
    if (preg_match('/can|canned|soup|tuna|sardine|sardines|spam|beans|lentils/', $name)) {
        return "Grains/Canned";
    }
    if (preg_match('/chips|soda|juice|coffee|tea|water|snack|chocolate|candy|popcorn/', $name)) {
        return "Snacks/Drinks";
    }

    return "General";
}

/**
 * Categorize a recipe by health balance based on its ingredients.
 * Returns one of: Veggie-heavy, Balanced, Meat-heavy, Light
 */
function health_tag($ingredients) {
    $ing = strtolower($ingredients);
    $meat = preg_match_all('/chicken|pork|beef|fish|shrimp|meat|bacon|sausage|ham|hotdog|tuna|sardine|squid|crab/', $ing);
    $veg  = preg_match_all('/cabbage|carrot|broccoli|spinach|kale|lettuce|tomato|onion|garlic|ginger|pepper|mushroom|eggplant|squash|okra|sitaw|kangkong|pechay|malunggay|sayote|ampalaya|cucumber|corn|beans|lentils/', $ing);

    if ($veg >= $meat * 2 + 1) return 'Veggie-heavy';
    if ($meat >= $veg * 2 + 1) return 'Meat-heavy';
    if ($meat > 0 && $veg > 0) return 'Balanced';
    return 'Light';
}

/**
 * Same classification as health_tag(), but also returns a color key
 * for the UI dots/labels. Returns [label, color] where color is one of:
 * 'green', 'amber', 'red'.
 *
 * This is a thin wrapper around health_tag() so the actual meat/veggie
 * logic lives in exactly ONE place. meal_planner.php used to declare its
 * own health_tag() that returned an array, which clashed with the one
 * above (returns a string) and caused a 'Cannot redeclare health_tag()'
 * fatal error.
 */
function health_tag_full($ingredients) {
    $label = health_tag($ingredients);
    $colors = [
        'Veggie-heavy' => 'green',
        'Meat-heavy'   => 'red',
        'Balanced'     => 'amber',
        'Light'        => 'green',
    ];
    return [$label, $colors[$label] ?? 'green'];
}

// ============================================================
// PANTRY STAPLES
//
// Ingredients assumed present in every kitchen. Excluded from
// recipe-matching so "Ready to cook" reflects real availability
// rather than requiring users to log salt, oil, and seasoning.
//
// Design note: intentionally culture-neutral. Universal basics
// only — no soy sauce, vinegar, or fish sauce, since not every
// household keeps those.
// ============================================================
$SHELFLIFE_STAPLES = [
    'salt',
    'pepper',
    'black pepper',
    'peppercorn',
    'oil',
    'cooking oil',
    'vegetable oil',
    'water',
    'sugar',
    'garlic',
    'onion'
];

/**
 * Return true if a single ingredient string is a pantry staple.
 * Matches loosely so "minced garlic" and "cooking oil" qualify.
 */
function shelflife_is_staple($ingredient) {
    global $SHELFLIFE_STAPLES;
    $needle = strtolower(trim($ingredient));
    if ($needle === '') return false;
    foreach ($SHELFLIFE_STAPLES as $s) {
        if ($needle === $s) return true;
        if (strpos($needle, $s) !== false) return true;
    }
    return false;
}

/**
 * Split a comma-separated ingredient list into "needed" (must
 * actually be in the pantry) and "staples" (assumed present).
 *
 * Returns:
 *   ['needed' => [...], 'staples' => [...], 'all' => [...]]
 */
function shelflife_split_ingredients($ingredients) {
    $all = array_values(array_filter(array_map('trim', explode(',', $ingredients))));
    $needed  = [];
    $staples = [];
    foreach ($all as $ing) {
        if (shelflife_is_staple($ing)) {
            $staples[] = $ing;
        } else {
            $needed[] = $ing;
        }
    }
    return ['needed' => $needed, 'staples' => $staples, 'all' => $all];
}

/**
 * Count how many non-staple ingredients the user actually has.
 * $pantry_items is an array of lowercase item names.
 *
 * Returns:
 *   ['have' => N, 'total' => M, 'staples_count' => K]
 *   where M = number of non-staple ingredients the recipe needs.
 */
function shelflife_count_match($ingredients, $pantry_items) {
    $split  = shelflife_split_ingredients($ingredients);
    $needed = $split['needed'];
    $have   = 0;

    foreach ($needed as $ing) {
        $needle = strtolower(trim($ing));
        if ($needle === '') continue;
        foreach ($pantry_items as $p) {
            $p_lower = strtolower(trim($p));
            if (strpos($p_lower, $needle) !== false || strpos($needle, $p_lower) !== false) {
                $have++;
                break;
            }
        }
    }

    return [
        'have'          => $have,
        'total'         => count($needed),
        'staples_count' => count($split['staples']),
    ];
}

?>