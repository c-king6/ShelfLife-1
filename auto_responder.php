<?php
// Rule-based auto-responder for the ShelfLife support chat.
//
// Uses a menu + keyword matching system. Not an LLM.
// When nothing matches, we escalate to a human.

// -----------------------------------------------------------------
// Numbered menu shortcuts
// -----------------------------------------------------------------
$MENU_RESPONSES = [

    '1' => [
        'title' => 'Adding items',
        'body'  => "Here's how to add an item to your pantry:\n\n" .
                   "1. Tap **Add item** at the top of your pantry\n" .
                   "2. Enter the item name and price (price helps track savings)\n" .
                   "3. Scan the barcode to auto-fill, or type it manually\n" .
                   "4. Pick an expiry date — the quick-pick buttons are faster\n" .
                   "5. Tap **Stock in pantry**\n\n" .
                   "Need more help? Reply **human** to reach an admin.",
    ],

    '2' => [
        'title' => 'Expiry & notifications',
        'body'  => "ShelfLife tracks expiry dates and alerts you before items go bad.\n\n" .
                   "• You'll get an email when an item is close to expiring\n" .
                   "• Set how many days in advance at **Profile → Notifications**\n" .
                   "• Turn email alerts on or off anytime\n" .
                   "• Expired items move to the **Waste bin**\n\n" .
                   "Need more help? Reply **human** to reach an admin.",
    ],

    '3' => [
        'title' => 'Shopping list',
        'body'  => "The shopping list helps you plan before going to the store:\n\n" .
                   "1. Tap **To-Buy** on your dashboard\n" .
                   "2. Quick-add items you need\n" .
                   "3. When you buy them, tick the box and tap **Bought**\n" .
                   "4. Set expiry dates in **New groceries**\n\n" .
                   "Need more help? Reply **human** to reach an admin.",
    ],

    '4' => [
        'title' => 'Recipes & meal planning',
        'body'  => "We have three recipe features:\n\n" .
                   "• **Recipes** — Browse official recipes or community-shared ones\n" .
                   "• **Meal plan** — Plan meals for the week; tap **Suggest my week** to auto-fill\n" .
                   "• **Recipes you can make** — On the dashboard, we show recipes using what you already have\n\n" .
                   "Need more help? Reply **human** to reach an admin.",
    ],

    '5' => [
        'title' => 'Waste log & savings',
        'body'  => "The waste tracker shows how much money you've saved vs. wasted:\n\n" .
                   "• When you delete an item, we ask if you used it or threw it away\n" .
                   "• Your savings card on the dashboard shows totals each month\n" .
                   "• **View full history** shows every item and its status\n" .
                   "• Add prices to your items for accurate tracking\n\n" .
                   "Need more help? Reply **human** to reach an admin.",
    ],

    '6' => [
        'title' => 'Account & security',
        'body'  => "Common account questions:\n\n" .
                   "• **Forgot password?** Use the link on the login page\n" .
                   "• **Change password** — Profile → Security → Change password\n" .
                   "• **Switch accounts** — Profile → Switch account\n" .
                   "• **Export your data** — Profile → Export my data (CSV)\n" .
                   "• **Verify email** — check your inbox for the 6-digit code\n\n" .
                   "For anything else, reply **human** to reach an admin.",
    ],

    '7' => [
        'title' => 'Troubleshooting',
        'body'  => "Try these common fixes first:\n\n" .
                   "1. Refresh the page (Ctrl + Shift + R for hard refresh)\n" .
                   "2. Check your internet connection\n" .
                   "3. Log out and log back in\n" .
                   "4. Clear your browser cache\n" .
                   "5. Try a different browser (Chrome, Firefox, Edge)\n\n" .
                   "Still stuck? Reply **human** with what you did and what happened.",
    ],

    '8' => [
        'title' => 'About ShelfLife',
        'body'  => "ShelfLife is a smart pantry app built as a capstone project.\n\n" .
                   "**What it does:**\n" .
                   "• Track groceries and expiry dates\n" .
                   "• Reduce household food waste\n" .
                   "• Save money on groceries\n" .
                   "• Suggest recipes based on what you have\n\n" .
                   "**Free to use.** No ads, no subscriptions.\n\n" .
                   "Anything specific I can help with?",
    ],

    '9' => [
        'title' => 'Talk to a human',
        'body'  => "Understood. I've forwarded your message to our support team. An admin will respond as soon as they're available.\n\n" .
                   "You can keep typing to add more details. If you'd like the assistant back, just type **bot**.",
        'escalate' => true,
    ],
];

// -----------------------------------------------------------------
// Keyword rules — first match wins
// -----------------------------------------------------------------
$KEYWORD_RULES = [

    // ---- GREETINGS ----
    [
        'keywords' => ['hello', 'hi there', 'hey there', 'good morning', 'good afternoon', 'good evening', 'kumusta', 'kamusta'],
        'body'     => "Hi! I'm ShelfLife's virtual assistant. 🤖\n\n" .
                      "I can help with:\n\n" .
                      "**1** — Adding items\n" .
                      "**2** — Expiry & notifications\n" .
                      "**3** — Shopping list\n" .
                      "**4** — Recipes & meal planning\n" .
                      "**5** — Waste log & savings\n" .
                      "**6** — Account & security\n" .
                      "**7** — Troubleshooting\n" .
                      "**8** — About ShelfLife\n" .
                      "**9** — Talk to a human\n\n" .
                      "Type a number, or describe what you need.",
    ],
    [
        'keywords' => ['thank', 'thanks', 'salamat', 'appreciate'],
        'body'     => "You're welcome! 😊 Anything else I can help with?",
    ],
    [
        'keywords' => ['bye', 'goodbye', 'see you', 'paalam'],
        'body'     => "Goodbye! Come back anytime you need help. 👋",
    ],
    [
        'keywords' => ['who are you', 'what are you', 'are you a bot', 'are you human', 'are you ai'],
        'body'     => "I'm ShelfLife's automated assistant. I answer common questions instantly so you don't have to wait for an admin.\n\n" .
                      "For complex issues, type **human** to reach a real person.",
    ],

    // ---- ABOUT ----
    [
        'keywords' => ['what is shelflife', 'about shelflife', 'about this app', 'about this site'],
        'body'     => $MENU_RESPONSES['8']['body'],
    ],
    [
        'keywords' => ['free', 'cost', 'price of app', 'subscription', 'payment', 'bayad'],
        'body'     => "ShelfLife is completely **free**. No ads, no subscriptions, no hidden fees. 💚\n\n" .
                      "Your data stays on our server and is only used to run your pantry.",
    ],
    [
        'keywords' => ['privacy', 'my data', 'personal info', 'who can see'],
        'body'     => "Your pantry data is private to your account. Only you can see your items, recipes, and chat with support.\n\n" .
                      "You can export or delete your data anytime:\n" .
                      "• **Export** — Profile → Export my data (CSV)\n" .
                      "• **Delete account** — reply **human** to request deletion",
    ],

    // ---- ADDING ITEMS ----
    [
        'keywords' => ['add item', 'add food', 'how to add', 'add an item', 'new item', 'add product', 'magdagdag'],
        'body'     => $MENU_RESPONSES['1']['body'],
    ],
    [
        'keywords' => ['edit item', 'change item', 'update item', 'modify item'],
        'body'     => "To edit an item:\n\n" .
                      "1. On your dashboard, tick the checkbox next to the item\n" .
                      "2. A black action bar appears at the bottom\n" .
                      "3. Tap **Edit** to change its name, price, or expiry\n\n" .
                      "You can edit multiple items at once with the batch editor.",
    ],
    [
        'keywords' => ['delete item', 'remove item', 'erase item', 'tanggal'],
        'body'     => "To delete an item:\n\n" .
                      "1. Tick the checkbox next to the item\n" .
                      "2. Tap **Delete** on the action bar\n" .
                      "3. We'll ask if you used it or threw it away — pick one\n\n" .
                      "That answer feeds your **Savings tracker**.",
    ],
    [
        'keywords' => ['quantity', 'how many', 'amount', 'how much left'],
        'body'     => "**Quantity** = how many of that item you have.\n\n" .
                      "• Adds together: 2 milk cartons with the same expiry = 1 card showing (×2)\n" .
                      "• If prices or expiries differ, we keep them separate\n" .
                      "• Edit quantity from the item's edit page",
    ],
    [
        'keywords' => ['barcode', 'scan', 'scanner', 'camera', 'qr'],
        'body'     => "To scan a barcode:\n\n" .
                      "1. Tap **Scan barcode** on the Add Item page\n" .
                      "2. Allow camera access when prompted\n" .
                      "3. Point at the product barcode\n" .
                      "4. Name and brand auto-fill from a public product database\n" .
                      "5. You can still edit everything before saving\n\n" .
                      "**Note:** camera works best on a phone, and needs HTTPS or localhost.",
    ],
    [
        'keywords' => ['price', 'cost of item', 'item price', 'magkano'],
        'body'     => "Prices are **optional** but useful:\n\n" .
                      "• Add the price when you add an item\n" .
                      "• ShelfLife remembers the last price you used for that item\n" .
                      "• It shows your previous prices when you type the name again\n" .
                      "• This powers the **Savings tracker** on your dashboard\n\n" .
                      "Only priced items count toward the savings math.",
    ],
    [
        'keywords' => ['category', 'categories', 'organize', 'organise', 'sort'],
        'body'     => "Items are automatically sorted into categories based on their name:\n\n" .
                      "• Dairy, Meat/Poultry, Fruits, Vegetables\n" .
                      "• Bakery, Pasta/Grains, Grains/Canned\n" .
                      "• Condiments, Spices/Seasonings, Snacks/Drinks\n" .
                      "• General (anything else)\n\n" .
                      "You can override the category when editing an item.",
    ],

    // ---- EXPIRY ----
    [
        'keywords' => ['expiry', 'expire', 'expiration', 'expires', 'due date', 'best before'],
        'body'     => "Every item can have an expiry date:\n\n" .
                      "• **Green** = fresh (4+ days left)\n" .
                      "• **Amber** = expiring soon (3 days or less)\n" .
                      "• **Red** = already expired → move to the Waste bin\n\n" .
                      "You'll get an email alert before items expire. Set the timing at **Profile → Notifications**.",
    ],
    [
        'keywords' => ['color', 'colour', 'what does green mean', 'what does red mean', 'what does amber mean'],
        'body'     => "Colours on item cards tell you the status:\n\n" .
                      "• 🟢 **Green bar** = Fresh (4+ days until expiry)\n" .
                      "• 🟡 **Amber bar** = Expiring soon (3 days or less)\n" .
                      "• 🔴 **Red bar** = Already expired\n\n" .
                      "You'll see them on the left side of each item card.",
    ],
    [
        'keywords' => ['waste bin', 'wastebin', 'expired items', 'old items'],
        'body'     => "The **Waste bin** shows all items that have already expired:\n\n" .
                      "1. Open the **Waste bin** tab on your dashboard\n" .
                      "2. Tick the items you want to discard\n" .
                      "3. Tap **Discard** — they'll be logged as wasted\n\n" .
                      "This keeps your pantry clean and your savings stats accurate.",
    ],

    // ---- NOTIFICATIONS ----
    [
        'keywords' => ['notification', 'notify', 'alert', 'email alert', 'reminder'],
        'body'     => "ShelfLife emails you before items expire:\n\n" .
                      "• Go to **Profile → Notifications**\n" .
                      "• Turn email alerts on or off\n" .
                      "• Pick how many days before expiry to be alerted (1, 3, 5, or 7)\n\n" .
                      "Emails are sent automatically. If you stop receiving them, check your spam folder.",
    ],
    [
        'keywords' => ['didn\'t get email', 'no email', 'not receiving', 'no notification', 'walang email'],
        'body'     => "If you're not getting emails:\n\n" .
                      "1. Check your **spam folder**\n" .
                      "2. Confirm your email address at **Profile**\n" .
                      "3. Make sure **Email alerts** is ON in Profile → Notifications\n" .
                      "4. Emails are sent on a schedule (roughly every hour)\n\n" .
                      "Still nothing? Reply **human** with your email address.",
    ],

    // ---- SHOPPING LIST ----
    [
        'keywords' => ['shopping list', 'to buy', 'to-buy', 'buy list', 'bibili'],
        'body'     => $MENU_RESPONSES['3']['body'],
    ],
    [
        'keywords' => ['incoming', 'new groceries', 'just bought', 'newly bought'],
        'body'     => "When you mark items as bought, they go to **New groceries**:\n\n" .
                      "1. Set the expiry date for each item\n" .
                      "2. Choose a category (auto-suggested based on the name)\n" .
                      "3. Tap **Move to pantry**\n\n" .
                      "Then they'll show up on your dashboard shelves.",
    ],

    // ---- RECIPES ----
    [
        'keywords' => ['recipe', 'cook', 'cooking', 'meal', 'lutuin'],
        'body'     => $MENU_RESPONSES['4']['body'],
    ],
    [
        'keywords' => ['how to cook', 'recipe instructions', 'recipe steps'],
        'body'     => "To see how to cook a recipe:\n\n" .
                      "1. Open the **Recipes** tab\n" .
                      "2. Pick a recipe\n" .
                      "3. The card shows ingredients and step-by-step method\n\n" .
                      "If you have all the ingredients, tap **Cook this now** to remove them from your pantry automatically.",
    ],
    [
        'keywords' => ['meal plan', 'planner', 'weekly plan', 'menu plan'],
        'body'     => "The Meal Planner lets you assign recipes to days:\n\n" .
                      "1. Open **Meal plan** from your dashboard\n" .
                      "2. Tap **+** on any lunch or dinner slot\n" .
                      "3. Pick a recipe, or tap **Suggest my week** for an auto-filled week\n\n" .
                      "Suggestions prioritize items close to expiring and balance your health.",
    ],
    [
        'keywords' => ['save recipe', 'bookmark', 'favorite', 'saved recipe'],
        'body'     => "Tap the **bookmark icon** on any recipe to save it.\n\n" .
                      "Saved recipes appear in the **Saved** tab on the Recipes page. You can also copy a recipe to your own collection with **Add to my recipes**.",
    ],
    [
        'keywords' => ['share recipe', 'post recipe', 'my recipe', 'submit recipe', 'add recipe'],
        'body'     => "To share your own recipe:\n\n" .
                      "1. Open **Recipes**\n" .
                      "2. Tap **Share recipe** at the top right\n" .
                      "3. Fill in the name, ingredients, and instructions\n" .
                      "4. Choose **public** (visible to everyone) or **private** (only you)\n" .
                      "5. Tap **Publish**\n\n" .
                      "Public recipes appear in the **Community** tab.",
    ],
    [
        'keywords' => ['report recipe', 'inappropriate', 'spam recipe', 'flag recipe'],
        'body'     => "To report a recipe:\n\n" .
                      "1. Find the recipe in **Community**\n" .
                      "2. Tap the **flag icon** on the card\n" .
                      "3. Pick a reason and add details\n\n" .
                      "After 3 reports, we automatically hide the recipe pending admin review.",
    ],
    [
        'keywords' => ['health', 'veggie', 'meat', 'balanced', 'diet', 'nutrition'],
        'body'     => "Recipes are tagged by health balance:\n\n" .
                      "• 🟢 **Veggie-heavy** — mostly vegetables\n" .
                      "• 🟠 **Balanced** — mix of meat and vegetables\n" .
                      "• 🔴 **Meat-heavy** — mostly meat\n" .
                      "• ⚪ **Light** — minimal ingredients\n\n" .
                      "Your **Meal plan** shows a weekly balance summary so you can see how you're eating.",
    ],

    // ---- WASTE & SAVINGS ----
    [
        'keywords' => ['waste', 'wasted', 'threw', 'thrown', 'spoiled', 'nasira'],
        'body'     => $MENU_RESPONSES['5']['body'],
    ],
    [
        'keywords' => ['saving', 'save money', 'budget', 'savings', 'tipid'],
        'body'     => "Your Savings card on the dashboard shows:\n\n" .
                      "• **Saved** — value of items you consumed before expiring\n" .
                      "• **Wasted** — value of items that went bad\n" .
                      "• **Efficiency** — percentage of grocery value used well\n\n" .
                      "Only items with a price are counted. Add prices when you add items for accurate tracking.",
    ],

    // ---- ACCOUNT ----
    [
        'keywords' => ['forgot password', 'reset password', 'cant login', "can't login", 'cannot login', 'lost password'],
        'body'     => "To reset your password:\n\n" .
                      "1. Go to the login page\n" .
                      "2. Click **Forgot password?**\n" .
                      "3. Enter your registered email\n" .
                      "4. Check your inbox for a 6-digit code\n" .
                      "5. Enter the code + your new password\n\n" .
                      "If you don't receive the email, check spam, then reply **human**.",
    ],
    [
        'keywords' => ['change password', 'update password', 'new password'],
        'body'     => "To change your password:\n\n" .
                      "1. Open your **Profile**\n" .
                      "2. Tap **Change password**\n" .
                      "3. Enter your current password and the new one\n\n" .
                      "You'll need to know your current password to change it.",
    ],
    [
        'keywords' => ['switch account', 'switch user', 'log out', 'logout', 'sign out'],
        'body'     => "From your Profile:\n\n" .
                      "• **Switch account** — pick another user, enter their password\n" .
                      "• **Sign out** — logs you out completely\n\n" .
                      "If you switch accounts with 'Keep me signed in' off, you'll be logged out when you close the browser.",
    ],
    [
        'keywords' => ['verify', 'verification', 'no code', 'code not working', 'verify email'],
        'body'     => "After signing up, we email a 6-digit code:\n\n" .
                      "• The code expires in 15 minutes\n" .
                      "• Check your spam folder if you don't see it\n" .
                      "• You can request a new one from the verify page\n\n" .
                      "If it still fails, reply **human** to reach an admin.",
    ],
    [
        'keywords' => ['code expired', 'reset code expired', 'verification expired', 'code too old'],
        'body'     => "Codes expire after **15 minutes** for security.\n\n" .
                      "To get a new one:\n" .
                      "• **Verification code** — click **Resend code** on the verify page\n" .
                      "• **Password reset code** — click **Resend code** on the reset page\n\n" .
                      "Check your spam folder if it doesn't arrive within a minute.",
    ],
    [
        'keywords' => ['change email', 'update email', 'new email'],
        'body'     => "To change your email address:\n\n" .
                      "For now, please reply **human** — an admin can update it for you.\n\n" .
                      "We're building a self-service email change feature for a future release.",
    ],
    [
        'keywords' => ['export', 'download', 'csv', 'my data', 'backup'],
        'body'     => "You can download your pantry as a spreadsheet:\n\n" .
                      "1. Open your Profile\n" .
                      "2. Tap **Export my data**\n" .
                      "3. A CSV file downloads with all your items\n\n" .
                      "It opens in Excel or Google Sheets.",
    ],
    [
        'keywords' => ['delete account', 'close account', 'remove account', 'cancel account'],
        'body'     => "To permanently delete your account:\n\n" .
                      "Reply **human** with the request. An admin will confirm and remove your account along with all associated data.\n\n" .
                      "This action cannot be undone.",
    ],

    // ---- TROUBLESHOOTING ----
    [
        'keywords' => ['not working', 'broken', 'bug', 'error', 'issue', 'problem', 'glitch', 'sira'],
        'body'     => $MENU_RESPONSES['7']['body'],
    ],
    [
        'keywords' => ['slow', 'lag', 'loading', 'mabagal'],
        'body'     => "If the app feels slow:\n\n" .
                      "• Check your internet connection\n" .
                      "• Close other browser tabs\n" .
                      "• Refresh the page (Ctrl + Shift + R)\n\n" .
                      "If it persists, reply **human** with what page was slow.",
    ],
    [
        'keywords' => ['nothing happens', 'nothing works', 'click nothing', 'doesnt work'],
        'body'     => "Try these first:\n\n" .
                      "1. Hard refresh the page (Ctrl + Shift + R)\n" .
                      "2. Check your internet\n" .
                      "3. Log out and back in\n" .
                      "4. Try Chrome or Edge\n\n" .
                      "If nothing changes, reply **human** with what button/page and I'll escalate.",
    ],

    // ---- SEARCH ----
    [
        'keywords' => ['search', 'find item', 'filter', 'look for', 'hanap'],
        'body'     => "You can search for items:\n\n" .
                      "• On the **Dashboard** — use the search bar to find pantry items\n" .
                      "• On the **Recipes** page — search by name or ingredient\n" .
                      "• On the **Recipes** page — use the health filter chips (Veggie, Balanced, Meat, Light)\n\n" .
                      "Search by typing part of the name — partial matches work.",
    ],

    // ---- MOBILE ----
    [
        'keywords' => ['app', 'install', 'mobile', 'phone', 'android', 'ios', 'pwa', 'home screen'],
        'body'     => "ShelfLife is a **Progressive Web App** — you can install it like a real app:\n\n" .
                      "**On Android (Chrome):**\n" .
                      "• Tap the **⋮** menu → **Install app**\n\n" .
                      "**On iPhone (Safari):**\n" .
                      "• Tap **Share** → **Add to Home Screen**\n\n" .
                      "Once installed, it gets its own icon and opens full-screen without the browser bar.",
    ],

    // ---- ESCALATION ----
    [
        'keywords' => ['human', 'real person', 'agent', 'support team', 'talk to someone', 'manager'],
        'body'     => "I've forwarded your message to our support team. An admin will reply as soon as they're online.\n\n" .
                      "You can keep typing to add more details. If you'd like the assistant back, just type **bot**.",
        'escalate' => true,
    ],
    [
        'keywords' => ['urgent', 'emergency', 'asap', 'right now', 'important'],
        'body'     => "I've flagged this as urgent. An admin will be notified and will respond as soon as possible.",
        'escalate' => true,
    ],
];

// -----------------------------------------------------------------
// Default fallback
// -----------------------------------------------------------------
$DEFAULT_RESPONSE = [
    'body'  => "I didn't quite understand your question. Here are common topics:\n\n" .
               "**1** — Adding items\n" .
               "**2** — Expiry & notifications\n" .
               "**3** — Shopping list\n" .
               "**4** — Recipes & meal planning\n" .
               "**5** — Waste log & savings\n" .
               "**6** — Account & security\n" .
               "**7** — Troubleshooting\n" .
               "**8** — About ShelfLife\n" .
               "**9** — Talk to a human\n\n" .
               "Or reply **human** to reach an admin directly.",
    'escalate' => false,
];

// -----------------------------------------------------------------
// Main responder function
// Returns [reply_text, should_escalate]
// -----------------------------------------------------------------
function auto_respond($user_message) {
    global $MENU_RESPONSES, $KEYWORD_RULES, $DEFAULT_RESPONSE;

    $msg = strtolower(trim($user_message));

    // 1) Menu number (1-9)?
    if (preg_match('/^\s*[1-9]\s*$/', $msg)) {
        $num = trim($msg);
        if (isset($MENU_RESPONSES[$num])) {
            return [
                $MENU_RESPONSES[$num]['body'],
                !empty($MENU_RESPONSES[$num]['escalate']),
            ];
        }
    }

    // 2) Keyword matching
    foreach ($KEYWORD_RULES as $rule) {
        foreach ($rule['keywords'] as $kw) {
            if (strpos($msg, $kw) !== false) {
                return [
                    $rule['body'],
                    !empty($rule['escalate']),
                ];
            }
        }
    }

    // 3) Fallback
    return [$DEFAULT_RESPONSE['body'], $DEFAULT_RESPONSE['escalate']];
}

// -----------------------------------------------------------------
// Helper to format bot messages (renders **bold** and line breaks)
// -----------------------------------------------------------------
function format_bot_message($text) {
    $safe = htmlspecialchars($text);
    $safe = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $safe);
    return nl2br($safe);
}

/**
 * Process an incoming user message and insert an auto-reply if appropriate.
 * Shared by send_message.php and upload_chat_image.php.
 *
 * Returns the new reply message id, or 0 if no reply was sent.
 */
function process_auto_reply($conn, $sender_id, $text) {
    $sender_id = (int)$sender_id;

    // Find the first admin account
    $admin_q   = mysqli_query($conn, "SELECT id FROM users WHERE role='admin' ORDER BY id ASC LIMIT 1");
    $admin_row = mysqli_fetch_assoc($admin_q);
    $admin_id  = $admin_row ? (int)$admin_row['id'] : 1;

    // Escalation status
    $uq = mysqli_query($conn, "SELECT support_escalated FROM users WHERE id = '$sender_id'");
    $user_row = mysqli_fetch_assoc($uq);
    $is_escalated = $user_row ? (int)$user_row['support_escalated'] : 0;

    // NEW: Auto-timeout logic. If escalated, check when the admin last replied.
    // If the admin hasn't replied in 30 minutes, reset to the bot.
    if ($is_escalated) {
        $lq = mysqli_query($conn, "SELECT timestamp FROM messages WHERE sender_id = '$admin_id' AND receiver_id = '$sender_id' ORDER BY id DESC LIMIT 1");
        $last_admin_msg = mysqli_fetch_assoc($lq);
        if ($last_admin_msg) {
            $last_time = strtotime($last_admin_msg['timestamp']);
            // 30 minutes = 1800 seconds
            if ((time() - $last_time) > 1800) {
                mysqli_query($conn, "UPDATE users SET support_escalated = 0 WHERE id = '$sender_id'");
                $is_escalated = 0; // Turn the bot back on
            }
        }
    }

    $msg_lower = strtolower($text);
    $wants_bot = (strpos($msg_lower, 'bot') !== false)
              || (strpos($msg_lower, 'menu') !== false)
              || (strpos($msg_lower, 'automated') !== false)
              || (strpos($msg_lower, 'auto reply') !== false);

    if ($is_escalated && !$wants_bot) {
        return 0; // admin will handle it manually
    }

    if ($wants_bot && $is_escalated) {
        mysqli_query($conn, "UPDATE users SET support_escalated = 0 WHERE id = '$sender_id'");
    }

    list($reply_text, $should_escalate) = auto_respond($text);
    $reply_esc = mysqli_real_escape_string($conn, $reply_text);

    mysqli_query($conn,
        "INSERT INTO messages (sender_id, receiver_id, message_text, is_auto_reply)
         VALUES ('$admin_id', '$sender_id', '$reply_esc', 1)"
    );

    if ($should_escalate) {
        mysqli_query($conn, "UPDATE users SET support_escalated = 1 WHERE id = '$sender_id'");
    }

    return (int)mysqli_insert_id($conn);
}

?>