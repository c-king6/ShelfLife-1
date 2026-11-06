<?php
require_once "session_config.php";
include "db_connect.php";
require_once "categories_config.php";


header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

// --- Figure out the week we're planning ---
$week_offset = (int)($_POST['week_offset'] ?? 0);
if ($week_offset < -52) $week_offset = -52;
if ($week_offset > 52)  $week_offset = 52;

$today  = new DateTime('today');
$dow    = (int)$today->format('N');
$monday = clone $today;
$monday->modify('-' . ($dow - 1) . ' days');
$monday->modify(($week_offset * 7) . ' days');

$week_days = [];
for ($i = 0; $i < 7; $i++) {
    $d = clone $monday;
    $d->modify("+$i days");
    $week_days[] = $d;
}

$week_start = $monday->format('Y-m-d');
$week_end   = $week_days[6]->format('Y-m-d');

// --- Pantry inventory with expiry priority ---
// Items expiring soonest get highest weight
$pantry_items = [];
$pq = mysqli_query($conn, "SELECT LOWER(TRIM(item_name)) AS name, expiry_date,
                                  DATEDIFF(expiry_date, CURDATE()) AS days_left
                           FROM inventory
                           WHERE user_id='$user_id' AND is_bought=1");
while ($p = mysqli_fetch_assoc($pq)) {
    $dl = ($p['days_left'] !== null) ? (int)$p['days_left'] : 999;
    $pantry_items[strtolower(trim($p['name']))] = $dl;
}

// --- Fetch all available recipes ---
$rq = mysqli_query($conn, "SELECT id, recipe_name, ingredients
                           FROM recipes
                           WHERE is_approved=1 AND is_hidden=0
                           AND (is_public=1 OR user_id='$user_id' OR user_id IS NULL)");
    

function ingredient_list($ingredients) {
    // Exclude pantry staples — a recipe shouldn't be penalized for
    // needing salt or oil the user presumably already has.
    $split = shelflife_split_ingredients($ingredients);
    return array_map(function($x) { return strtolower(trim($x)); }, $split['needed']);
}

// Score each recipe
$recipes = [];
while ($r = mysqli_fetch_assoc($rq)) {
    $parts = ingredient_list($r['ingredients']);
    $have  = 0;
    $urgency_score = 0;

    foreach ($parts as $p) {
        $matched = false;
        foreach ($pantry_items as $pantry_name => $days_left) {
            if (strpos($pantry_name, $p) !== false || strpos($p, $pantry_name) !== false) {
                $matched = true;
                // Weight: item expiring in 1 day = 10 pts, 7 days = 4 pts, fresh = 0
                if ($days_left <= 3)       $urgency_score += 10;
                elseif ($days_left <= 7)   $urgency_score += 4;
                break;
            }
        }
        if ($matched) $have++;
    }

    $total = count($parts);
    $ready = ($total > 0 && $have === $total);

    $score = $urgency_score;
    if ($ready) $score += 5;

    $recipes[] = [
        'id'          => (int)$r['id'],
        'name'        => $r['recipe_name'],
        'ingredients' => $r['ingredients'],
        'health'      => health_tag($r['ingredients']),
        'have'        => $have,
        'total'       => $total,
        'ready'       => $ready,
        'score'       => $score,
    ];
}

if (empty($recipes)) {
    echo json_encode(['success' => false, 'error' => 'No recipes available to suggest.']);
    exit();
}

// --- Fetch existing plan to know which slots to fill ---
$existing = [];
$eq = mysqli_query($conn, "SELECT plan_date, meal_slot, recipe_id FROM meal_plan
                           WHERE user_id='$user_id'
                           AND plan_date BETWEEN '$week_start' AND '$week_end'");
while ($e = mysqli_fetch_assoc($eq)) {
    $existing[$e['plan_date']][$e['meal_slot']] = (int)$e['recipe_id'];
}

// --- Build the plan ---
$planned  = [];
$used_ids = [];

// Don't repeat recipes within the week — pick a fresh one per slot
function pick_best(&$recipes, $used_ids, $planned_health, $prefer_health = null) {
    $best = null;
    $best_score = -PHP_INT_MAX;

    foreach ($recipes as $r) {
        if (in_array($r['id'], $used_ids, true)) continue;

        $score = $r['score'];

        // Health balance adjustment
        $veg_count  = $planned_health['Veggie-heavy'] ?? 0;
        $meat_count = $planned_health['Meat-heavy']   ?? 0;

                // Aim for a realistic mix. Penalize whichever side we have too much of.
        if ($r['health'] === 'Meat-heavy' && $meat_count > $veg_count) {
            $score -= 12;
        }
        if ($r['health'] === 'Veggie-heavy' && $veg_count > $meat_count + 1) {
            $score -= 12;
        }
        // Boost the under-represented side
        if ($r['health'] === 'Meat-heavy' && $veg_count > $meat_count) {
            $score += 6;
        }
        if ($r['health'] === 'Veggie-heavy' && $meat_count > $veg_count) {
            $score += 6;
        }
        if ($r['health'] === 'Balanced' && abs($meat_count - $veg_count) >= 2) {
            $score += 4;
        }

        if ($prefer_health && $r['health'] === $prefer_health) {
            $score += 5;
        }

        if ($score > $best_score) {
            $best_score = $score;
            $best = $r;
        }
    }
    return $best;
}

mysqli_begin_transaction($conn);

try {
    $planned_health = ['Veggie-heavy' => 0, 'Balanced' => 0, 'Meat-heavy' => 0, 'Light' => 0];

    foreach ($week_days as $day) {
        $date = $day->format('Y-m-d');
        foreach (['lunch', 'dinner'] as $slot) {
            // Skip slots that already have a plan
            if (isset($existing[$date][$slot])) {
                // Still count their health for balance
                $existing_recipe_id = $existing[$date][$slot];
                foreach ($recipes as $r) {
                    if ($r['id'] === $existing_recipe_id) {
                        $planned_health[$r['health']]++;
                        $used_ids[] = $r['id'];
                        break;
                    }
                }
                continue;
            }

            $pick = pick_best($recipes, $used_ids, $planned_health);
            if (!$pick) {
                // All recipes used — reset and allow reuse
                $used_ids = [];
                $pick = pick_best($recipes, $used_ids, $planned_health);
            }
            if (!$pick) continue;

            $planned_health[$pick['health']]++;
            $used_ids[] = $pick['id'];

            $recipe_id = (int)$pick['id'];
            $date_esc  = mysqli_real_escape_string($conn, $date);
            $slot_esc  = mysqli_real_escape_string($conn, $slot);

            mysqli_query($conn, "INSERT INTO meal_plan (user_id, plan_date, meal_slot, recipe_id)
                                 VALUES ('$user_id', '$date_esc', '$slot_esc', $recipe_id)
                                 ON DUPLICATE KEY UPDATE recipe_id = $recipe_id");
            $planned[] = ['date' => $date, 'slot' => $slot, 'recipe' => $pick['name']];
        }
    }

    mysqli_commit($conn);
    echo json_encode([
        'success' => true,
        'count'   => count($planned),
        'planned' => $planned,
    ]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'error' => 'Could not save plan.']);
}
?>