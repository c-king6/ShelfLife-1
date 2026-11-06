<?php
/**
 * household_helper.php — Shared helpers for household sharing.
 *
 * Provides:
 *   - get_household_user_ids()   → list of user IDs in my household (incl. me)
 *   - get_household_members()    → full member details (id, name, avatar, role)
 *   - get_household_info()       → household row for the current user
 *   - generate_invite_code()     → random 6-char code, safe alphabet
 *   - log_household_activity()   → insert into household_activity
 *   - has_unread_household_activity() → red-dot check
 *   - get_household_activity()   → recent activity feed
 *   - user_can_view_item()       → for permission checks
 */

require_once __DIR__ . '/db_connect.php';

/**
 * Returns an array of user IDs in the same household as $user_id,
 * INCLUDING $user_id. Returns just [$user_id] if no household.
 */
function get_household_user_ids($conn, $user_id) {
    $user_id = (int)$user_id;
    $q = mysqli_query($conn, "SELECT household_id FROM users WHERE id = $user_id");
    $row = $q ? mysqli_fetch_assoc($q) : null;

    if (!$row || empty($row['household_id'])) {
        return [$user_id];
    }

    $hid = (int)$row['household_id'];
    $ids = [];
    $mq = mysqli_query($conn, "SELECT id FROM users WHERE household_id = $hid");
    while ($m = mysqli_fetch_assoc($mq)) {
        $ids[] = (int)$m['id'];
    }
    if (empty($ids)) $ids[] = $user_id;
    return $ids;
}

/**
 * Returns full member rows for the current user's household.
 * Returns an empty array if not in a household.
 */
function get_household_members($conn, $user_id) {
    $user_id = (int)$user_id;
    $q = mysqli_query($conn, "SELECT household_id FROM users WHERE id = $user_id");
    $row = $q ? mysqli_fetch_assoc($q) : null;
    if (!$row || empty($row['household_id'])) return [];

    $hid = (int)$row['household_id'];
    $out = [];
    $mq = mysqli_query($conn, "
        SELECT id, username, profile_pic, role
        FROM users
        WHERE household_id = $hid
        ORDER BY id ASC
    ");
    while ($m = mysqli_fetch_assoc($mq)) {
        $out[] = $m;
    }
    return $out;
}

/**
 * Returns the household row (id, name, owner_id, invite_code, invite_expires_at)
 * or null if the user isn't in one.
 */
function get_household_info($conn, $user_id) {
    $user_id = (int)$user_id;
    $q = mysqli_query($conn, "
        SELECT h.*
        FROM users u
        JOIN households h ON h.id = u.household_id
        WHERE u.id = $user_id
        LIMIT 1
    ");
    return $q ? mysqli_fetch_assoc($q) : null;
}

/**
 * Generate a 6-character invite code that avoids confusing letters.
 * Alphabet: A-H, J-N, P-Z, 2-9  (no I, O, 0, 1)
 */
function generate_invite_code() {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $code;
}

/**
 * Generate a code that's guaranteed unique in the households table.
 */
function generate_unique_invite_code($conn) {
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $code = generate_invite_code();
        $safe = mysqli_real_escape_string($conn, $code);
        $q = mysqli_query($conn, "SELECT id FROM households WHERE invite_code = '$safe'");
        if (mysqli_num_rows($q) === 0) return $code;
    }
    // Fallback: append time-based entropy (extremely unlikely to be reached)
    return strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
}

/**
 * Log a household activity row.
 *
 * $actor_id      — who did it
 * $target_user_id — whose item it was
 * $action        — 'joined','left','created','added','edited','deleted_consumed',
 *                  'deleted_wasted','cooked','invite_regenerated'
 * $item_name     — optional
 * $item_id       — optional
 * $details       — optional short string
 */
function log_household_activity($conn, $actor_id, $target_user_id, $action, $item_name = null, $item_id = null, $details = null) {
    $actor_id       = (int)$actor_id;
    $target_user_id = (int)$target_user_id;

    // Look up actor's household
    $aq = mysqli_query($conn, "SELECT household_id FROM users WHERE id = $actor_id");
    $ar = $aq ? mysqli_fetch_assoc($aq) : null;
    if (!$ar || empty($ar['household_id'])) return 0;

    $hid = (int)$ar['household_id'];

    $action_e  = mysqli_real_escape_string($conn, $action);
    $item_sql  = ($item_name !== null) ? "'" . mysqli_real_escape_string($conn, $item_name) . "'" : "NULL";
    $itemid_sql= ($item_id !== null)   ? (int)$item_id                                   : "NULL";
    $details_sql = ($details !== null) ? "'" . mysqli_real_escape_string($conn, $details) . "'" : "NULL";

    mysqli_query($conn, "
        INSERT INTO household_activity
            (household_id, actor_id, target_user_id, action, item_name, item_id, details)
        VALUES
            ($hid, $actor_id, $target_user_id, '$action_e', $item_sql, $itemid_sql, $details_sql)
    ");

    return (int)mysqli_insert_id($conn);
}

/**
 * Has the current user missed activity since they last viewed the feed?
 * Only counts activity where:
 *   - someone ELSE did something to the current user's item, OR
 *   - a new member joined/left (target_user_id = the actor, but we still notify)
 */
function has_unread_household_activity($conn, $user_id) {
    $user_id = (int)$user_id;
    $uq = mysqli_query($conn, "SELECT household_id, household_last_seen_activity_id FROM users WHERE id = $user_id");
    $u = $uq ? mysqli_fetch_assoc($uq) : null;
    if (!$u || empty($u['household_id'])) return false;

    $hid = (int)$u['household_id'];
    $last_seen = (int)$u['household_last_seen_activity_id'];

    $q = mysqli_query($conn, "
        SELECT COUNT(*) AS c
        FROM household_activity
        WHERE household_id = $hid
          AND id > $last_seen
          AND actor_id != $user_id
    ");
    $row = $q ? mysqli_fetch_assoc($q) : null;
    return $row && (int)$row['c'] > 0;
}

/**
 * Mark the current user as having seen all activity up to the latest row.
 */
function mark_household_activity_seen($conn, $user_id) {
    $user_id = (int)$user_id;
    $uq = mysqli_query($conn, "SELECT household_id FROM users WHERE id = $user_id");
    $u = $uq ? mysqli_fetch_assoc($uq) : null;
    if (!$u || empty($u['household_id'])) return;

    $hid = (int)$u['household_id'];
    $last_q = mysqli_query($conn, "SELECT MAX(id) AS m FROM household_activity WHERE household_id = $hid");
    $last = $last_q ? (int)(mysqli_fetch_assoc($last_q)['m'] ?? 0) : 0;

    mysqli_query($conn, "UPDATE users SET household_last_seen_activity_id = $last WHERE id = $user_id");
}

/**
 * Fetch recent activity for the current user's household.
 * Returns rows with actor + target user details joined in.
 */
function get_household_activity($conn, $user_id, $limit = 100) {
    $user_id = (int)$user_id;
    $limit = (int)$limit;
    $uq = mysqli_query($conn, "SELECT household_id FROM users WHERE id = $user_id");
    $u = $uq ? mysqli_fetch_assoc($uq) : null;
    if (!$u || empty($u['household_id'])) return [];

    $hid = (int)$u['household_id'];

    $out = [];
    $q = mysqli_query($conn, "
        SELECT
            a.*,
            actor.username  AS actor_name,
            actor.profile_pic AS actor_pic,
            target.username AS target_name
        FROM household_activity a
        LEFT JOIN users actor  ON actor.id  = a.actor_id
        LEFT JOIN users target ON target.id = a.target_user_id
        WHERE a.household_id = $hid
        ORDER BY a.id DESC
        LIMIT $limit
    ");
    while ($r = mysqli_fetch_assoc($q)) $out[] = $r;
    return $out;
}

/**
 * Can $user_id act on (view/use/delete) item $item_id?
 * Yes if the item belongs to the user OR to someone in their household.
 */
function user_can_view_item($conn, $user_id, $item_id) {
    $user_id = (int)$user_id;
    $item_id = (int)$item_id;
    $ids = get_household_user_ids($conn, $user_id);
    if (empty($ids)) return false;
    $list = implode(',', $ids);
    $q = mysqli_query($conn, "SELECT user_id FROM inventory WHERE id = $item_id AND user_id IN ($list) LIMIT 1");
    if (!$q || mysqli_num_rows($q) === 0) return false;
    return (int)mysqli_fetch_assoc($q)['user_id'];
}

/**
 * Fetch the owner_id of an inventory item, or null if not found.
 */
function get_item_owner($conn, $item_id) {
    $item_id = (int)$item_id;
    $q = mysqli_query($conn, "SELECT user_id FROM inventory WHERE id = $item_id LIMIT 1");
    if (!$q || mysqli_num_rows($q) === 0) return null;
    return (int)mysqli_fetch_assoc($q)['user_id'];
}