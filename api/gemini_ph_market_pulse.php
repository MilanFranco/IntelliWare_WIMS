<?php
/**
 * api/gemini_ph_market_pulse.php
 *
 * Real-Time Philippine Consumer Pulse & Daily Demand Intelligence Engine
 * Powered dynamically by Google Gemini AI and Live Warehouse Inventory DB Sync.
 *
 * Dynamically analyzes what Filipino consumers want to buy on specific days,
 * weather events, payday cycles, and cultural moments, cross-referencing
 * directly with the store's active inventory catalog in real-time.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
}

if (!isset($_SESSION['Username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/services/PhilippineSeasonalService.php';

// Parse JSON input
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?? [];

$targetDate  = !empty($data['date']) ? $data['date'] : date('Y-m-d');
$rawEvent    = !empty($data['event']) ? $data['event'] : (!empty($data['day_type']) ? $data['day_type'] : 'auto');
$region      = !empty($data['region']) ? $data['region'] : 'Philippines (Nationwide)';
$customQuery = trim($data['custom_query'] ?? $data['prompt'] ?? '');

// Map quick preset event keys from frontend to internal day types
$eventMap = [
    'payday'            => 'payday',
    'tgif'              => 'friday_night',
    'friday_night'      => 'friday_night',
    'weekend_groceries' => 'saturday_haul',
    'saturday_haul'     => 'saturday_haul',
    'sunday_salusalo'   => 'sunday_family',
    'sunday_family'     => 'sunday_family',
    'rainy_comfort'     => 'rainy_typhoon',
    'rainy_typhoon'     => 'rainy_typhoon',
    'summer_heat'       => 'summer_heat',
    'undas'             => 'holiday_undas',
    'holiday_undas'     => 'holiday_undas',
    'simbang_gabi'      => 'simbang_gabi',
    'holy_week'         => 'holy_week',
    'back_to_school'    => 'regular_weekday',
    'regular_weekday'   => 'regular_weekday'
];
$dayType = $eventMap[$rawEvent] ?? ($rawEvent ?: 'auto');

$timestamp = strtotime($targetDate) ?: time();
$dayOfWeek = date('l', $timestamp);
$dayNum    = (int)date('j', $timestamp);
$monthNum  = (int)date('n', $timestamp);
$monthName = date('F', $timestamp);
$formattedDate = date('F j, Y', $timestamp);

// Automatic day classification if 'auto'
if ($dayType === 'auto') {
    if ($dayNum === 15 || $dayNum === 30 || $dayNum === 31 || ($dayNum === 14 && $dayOfWeek === 'Friday')) {
        $dayType = 'payday';
    } elseif ($monthNum === 11 && ($dayNum === 1 || $dayNum === 2)) {
        $dayType = 'holiday_undas';
    } elseif ($monthNum === 12 && $dayNum >= 16 && $dayNum <= 25) {
        $dayType = 'simbang_gabi';
    } elseif ($dayOfWeek === 'Friday') {
        $dayType = 'friday_night';
    } elseif ($dayOfWeek === 'Saturday') {
        $dayType = 'saturday_haul';
    } elseif ($dayOfWeek === 'Sunday') {
        $dayType = 'sunday_family';
    } elseif ($monthNum >= 6 && $monthNum <= 10) {
        $dayType = 'rainy_typhoon';
    } elseif ($monthNum >= 3 && $monthNum <= 5) {
        $dayType = 'summer_heat';
    } else {
        $dayType = 'regular_weekday';
    }
}

// Map day type to friendly titles & cultural drivers
$dayTypeLabels = [
    'payday'           => '💰 Payday / Quincena (15th / 30th Cutoff)',
    'friday_night'     => '🍻 Friday TGIF / Inuman & Unwinding Night',
    'saturday_haul'    => '🛒 Saturday Weekly Supermarket Haul',
    'sunday_family'    => '👨‍👩‍👧‍👦 Sunday Family Salu-Salo & Church Day',
    'rainy_typhoon'    => '🌧️ Rainy Season / Tag-ulan Comfort Food & Emergency',
    'summer_heat'      => '☀️ Tag-init / Summer Heatwave Hydration & Treats',
    'holiday_undas'    => '🕯️ Undas (All Saints / Souls Day Family Gathering)',
    'simbang_gabi'     => '🎄 Simbang Gabi / Pasko Early Holiday Rush',
    'holy_week'        => '✝️ Semana Santa / Holy Week Fasting & Seafood',
    'regular_weekday'  => '🏢 Regular Workday / Quick Merienda & Baon'
];

$activeDayLabel = $dayTypeLabels[$dayType] ?? 'Daily Consumer Pulse';

// ── 1. Fetch Google Gemini API settings from database ───────────────────────
$settingsRes = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'ai_vision_%'");
$aiSettings = [
    'ai_vision_enabled' => '1',
    'ai_vision_model'   => 'gemini-3.5-flash-lite',
    'ai_vision_api_key' => ''
];
if ($settingsRes) {
    while ($row = $settingsRes->fetch_assoc()) {
        $aiSettings[$row['setting_key']] = $row['setting_value'];
    }
}
$apiKey = trim($aiSettings['ai_vision_api_key'] ?? '');

// ── 2. Fetch Active Warehouse Product Catalog ───────────────────────────────
$catalogProducts = [];
$catalogById = [];
$catalogByCategory = [];
$catRes = $conn->query("SELECT product_id, product_name, category, quantity, reorder_point FROM products WHERE archived = 0 ORDER BY category ASC, quantity ASC");
if ($catRes) {
    while ($r = $catRes->fetch_assoc()) {
        $item = [
            'id'       => (int)$r['product_id'],
            'name'     => $r['product_name'],
            'category' => $r['category'],
            'stock'    => (int)$r['quantity'],
            'reorder'  => (int)$r['reorder_point']
        ];
        $catalogProducts[] = $item;
        $catalogById[$item['id']] = $item;
        $catalogByCategory[$item['category']][] = $item;
    }
}

// Helper to cross-reference product name with catalog
function matchWithCatalog(string $prodName, array $catalog): ?array {
    $prodLower = strtolower(trim($prodName));
    $bestMatch = null;
    $highestScore = 0;

    foreach ($catalog as $catItem) {
        $catLower = strtolower($catItem['name']);
        $score = 0;

        if ($catLower === $prodLower) {
            $score += 20;
        } elseif (stripos($catLower, $prodLower) !== false || stripos($prodLower, $catLower) !== false) {
            $score += 10;
        } else {
            $words = preg_split('/[\s,\-\(\)\/]+/', $prodLower, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($words as $w) {
                if (strlen($w) >= 3 && stripos($catLower, $w) !== false) {
                    $score += 3;
                }
            }
        }

        if ($score > $highestScore && $score >= 3) {
            $highestScore = $score;
            $bestMatch = $catItem;
        }
    }

    return $bestMatch;
}

// Build a concise catalog summary text for Gemini context
$catalogSummaryLines = [];
foreach ($catalogProducts as $cp) {
    $catalogSummaryLines[] = "[ID: {$cp['id']}] {$cp['name']} ({$cp['category']} | Stock: {$cp['stock']} units | MinReorder: {$cp['reorder']})";
}
$catalogContextText = implode("\n", $catalogSummaryLines);

// ── 3. Call Google Gemini AI with Live Catalog Context ──────────────────────
$geminiResponse = null;
$geminiModelUsed = '';
$isAiLive = false;

if (!empty($apiKey)) {
    $prompt = "You are a Chief Retail Analyst and Philippine FMCG Market Intelligence Expert for IntelliWare WIMS (Warehouse Inventory Management System).
Analyze real consumer purchasing behavior, daily Filipino cravings, cultural moments, household cash flow, and supermarket demand for this specific day and context:

Target Date: {$formattedDate} ({$dayOfWeek})
Day / Event Type: {$activeDayLabel} (Category: {$dayType})
Month: {$monthName} (Month {$monthNum})
Philippine Region: " . strtoupper($region) . "
" . ($customQuery ? "User Focus / Specific Query: \"$customQuery\"\n" : "") . "

ACTIVE WAREHOUSE INVENTORY (Real products currently registered in this warehouse):
{$catalogContextText}

CRITICAL INSTRUCTIONS:
1. Identify what everyday Filipino consumers (mothers, workers, students, barkada, families) actively WANT, BUY, and CRAVE on this specific day ({$formattedDate}, {$dayOfWeek}).
2. From the ACTIVE WAREHOUSE INVENTORY list above, select 4 to 5 products that best match these cravings and daily consumption patterns.
   - You MUST use the exact product name and exact ID from the inventory list above.
   - Inspect the product's actual 'Stock' and 'MinReorder' threshold to generate tailored, dynamic stock advice:
     * If Stock <= MinReorder or Stock is 0: highlight procurement/restock urgency.
     * If Stock > MinReorder: suggest specific merchandising, promo bundles, or prime shelf placement.
3. Suggest 1 to 2 high-demand trending Philippine consumer products that are currently NOT in the warehouse inventory (Market Opportunities to onboard). For these, set product_id to null and is_market_opportunity to true.
4. Avoid repetitive boilerplate or generic stock phrases. Tailor each item's why_people_want_this, retail_merchandising_tip, and stock_advice dynamically to its specific real-world context and stock buffer.

Return ONLY a valid JSON object matching this exact schema:
{
  \"consumer_sentiment_summary\": \"2-3 concise sentences describing Filipino consumer psychology, spending power, and shopping mindset on this specific day.\",
  \"spending_power_level\": \"High (Payday Surge)\" | \"Moderate\" | \"Value-Focused / Budget\",
  \"primary_driver\": \"Short descriptive headline of the main consumption trigger\",
  \"trending_categories\": [
    {\"name\": \"Category Name\", \"lift_pct\": \"+75%\", \"demand_level\": \"Surging\" | \"High\" | \"Steady\"}
  ],
  \"top_consumer_wants\": [
    {
      \"product_id\": 123,
      \"product_name\": \"Exact product name from inventory list, or new unstocked item name\",
      \"category\": \"Category name\",
      \"why_people_want_this\": \"Concrete 1-2 sentence explanation of why Filipinos crave or need this on this specific day/weather/event.\",
      \"target_buyer\": \"Target demographic (e.g. Working Parents, Barkada, College Students, Households)\",
      \"demand_surge\": \"e.g. +75% Weekend Surge\",
      \"is_market_opportunity\": false,
      \"retail_merchandising_tip\": \"Practical advice for store displays or bundle promotions.\",
      \"stock_advice\": \"Context-aware advice based on its real stock level and expected demand surge.\"
    }
  ]
}
Provide exactly 6 top in-demand consumer products.";

    $payload = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.35,
            "response_mime_type" => "application/json"
        ]
    ];

    $models = [
        $aiSettings['ai_vision_model'] ?? 'gemini-3.5-flash-lite',
        'gemini-3.5-flash-lite',
        'gemini-2.5-flash',
        'gemini-1.5-flash',
        'gemini-pro'
    ];
    $models = array_unique(array_filter($models));

    foreach ($models as $m) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$m}:generateContent?key=" . urlencode($apiKey);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 18);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200) {
            $json = json_decode($res, true);
            $rawText = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
            if ($rawText) {
                $cleaned = trim($rawText);
                if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $cleaned, $match)) {
                    $cleaned = $match[1];
                }
                $parsed = json_decode($cleaned, true);
                if ($parsed && !empty($parsed['top_consumer_wants'])) {
                    $geminiResponse = $parsed;
                    $geminiModelUsed = $m;
                    $isAiLive = true;
                    break;
                }
            }
        }
    }
}

// ── 4. Dynamic Offline Fallback (Sync with Active DB Inventory) ─────────────
if (!$geminiResponse) {
    $fallbackTargetCats = [];
    $fallbackSentiment = '';
    $fallbackMood = 'Moderate';
    $fallbackDriver = $activeDayLabel;

    switch ($dayType) {
        case 'payday':
            $fallbackTargetCats = ['Dry Goods', 'Canned Goods', 'Frozen Foods', 'Dairy Products', 'Beverages'];
            $fallbackSentiment = "The 15th/30th 'Quincena' payday brings fresh household liquidity. Families eagerly restock pantry staples, reward children with favorite meals, and purchase bulk grocery hauls.";
            $fallbackMood = 'Peak High (Payday Liquidity Surge)';
            $fallbackDriver = '15th/30th Salary Release & Bulk Pantry Restocking';
            break;
        case 'friday_night':
            $fallbackTargetCats = ['Beverages', 'Canned Goods', 'Snacks and Biscuits', 'Frozen Foods'];
            $fallbackSentiment = "Friday evening brings the 'TGIF' mindset across Philippine cities. Consumers purchase pulutan, chilled beers, ready-to-heat party foods, and savory snacks to unwind after work.";
            $fallbackMood = 'High (End-of-Week Social Spending)';
            $fallbackDriver = 'TGIF Inuman & Social Barkada Gatherings';
            break;
        case 'saturday_haul':
            $fallbackTargetCats = ['Dry Goods', 'Frozen Foods', 'Canned Goods', 'Dairy Products', 'Bakery Products'];
            $fallbackSentiment = "Saturday morning and afternoon are peak grocery hours for Filipino families doing their comprehensive weekly supermarket runs.";
            $fallbackMood = 'High (Weekend Grocery Restock)';
            $fallbackDriver = 'Saturday Family Supermarket & Wet Market Haul';
            break;
        case 'sunday_family':
            $fallbackTargetCats = ['Bakery Products', 'Dairy Products', 'Dry Goods', 'Beverages', 'Condiments and Sauces'];
            $fallbackSentiment = "Sundays center on family church attendance, leisurely morning pandesal, grand lunch potlucks (salu-salo), and shared sweet drinks.";
            $fallbackMood = 'Family Leisure & Celebration';
            $fallbackDriver = 'Sunday Family Salu-Salo & Church Day';
            break;
        case 'rainy_typhoon':
            $fallbackTargetCats = ['Dry Goods', 'Canned Goods', 'Beverages', 'Bakery Products', 'Personal Care'];
            $fallbackSentiment = "During monsoon rainy days and typhoon weather, Filipinos gravitate strongly toward warm comfort foods, instant broths, hot coffee, and storm preparedness essentials.";
            $fallbackMood = 'Needs-Driven / Preparedness Focused';
            $fallbackDriver = 'Tag-ulan Comfort Food & Storm Preparedness';
            break;
        case 'summer_heat':
            $fallbackTargetCats = ['Beverages', 'Dairy Products', 'Frozen Foods', 'Snacks and Biscuits'];
            $fallbackSentiment = "Scorching Philippine summer heatwaves drive continuous demand for cold refreshments, halo-halo ingredients, ice cream treats, and hydration.";
            $fallbackMood = 'High (Hydration & Summer Treats)';
            $fallbackDriver = 'Tag-init Heatwave Refreshment & Halo-Halo Surge';
            break;
        default:
            $fallbackTargetCats = ['Dry Goods', 'Beverages', 'Canned Goods', 'Bakery Products'];
            $fallbackSentiment = "Everyday consumer demand focuses on quick breakfast staples, baon for school and office merienda, and affordable daily pantry replenishment.";
            $fallbackMood = 'Steady / Value-Focused';
            $fallbackDriver = 'Regular Workday / Quick Merienda & Baon';
            break;
    }

    $pickedWants = [];
    $usedIds = [];

    // Dynamically pick matching products from real catalog
    foreach ($fallbackTargetCats as $cat) {
        if (!empty($catalogByCategory[$cat])) {
            foreach ($catalogByCategory[$cat] as $cp) {
                if (!in_array($cp['id'], $usedIds)) {
                    $usedIds[] = $cp['id'];
                    $buffer = $cp['stock'] - $cp['reorder'];
                    $stockMsg = ($cp['stock'] <= $cp['reorder'])
                        ? "Low Stock: Only {$cp['stock']} units left (below reorder threshold of {$cp['reorder']}). Restock immediately."
                        : "Healthy Stock: {$cp['stock']} units available (+{$buffer} above minimum threshold). Position on prominent front displays.";

                    $pickedWants[] = [
                        'product_id'               => $cp['id'],
                        'product_name'             => $cp['name'],
                        'category'                 => $cp['category'],
                        'why_people_want_this'     => "Essential {$cp['category']} staple experiencing heightened consumer interest during {$activeDayLabel}.",
                        'target_buyer'             => 'Household Shoppers & Everyday Consumers',
                        'demand_surge'             => '+65% ' . ucfirst(str_replace('_', ' ', $dayType)) . ' Surge',
                        'is_market_opportunity'    => false,
                        'retail_merchandising_tip' => "Display prominently in eye-level aisle shelves or near checkouts.",
                        'stock_advice'             => $stockMsg
                    ];
                    break;
                }
            }
        }
        if (count($pickedWants) >= 5) break;
    }

    // Include 1 dynamic market opportunity SKU
    $opps = [
        'payday'            => ['name' => 'Magnolia Gold Butter 225g', 'cat' => 'Dairy Products', 'buyer' => 'Home Bakers & Cooks', 'surge' => '+75% Surge'],
        'friday_night'      => ['name' => 'San Mig Light 6-Pack Cans', 'cat' => 'Beverages', 'buyer' => 'Young Professionals & Barkada', 'surge' => '+90% TGIF Surge'],
        'saturday_haul'     => ['name' => 'Eden Melt Cheese 430g Block', 'cat' => 'Dairy Products', 'buyer' => 'Family Grocery Shoppers', 'surge' => '+70% Weekend Surge'],
        'sunday_family'     => ['name' => 'Selecta Ice Cream 1.5L Double Dutch', 'cat' => 'Frozen Foods', 'buyer' => 'Sunday Family Hosts', 'surge' => '+80% Sunday Surge'],
        'rainy_typhoon'     => ['name' => 'Nongshim Shin Ramyun Spicy Noodle Bowl', 'cat' => 'Dry Goods', 'buyer' => 'Cold Weather Shoppers', 'surge' => '+110% Rainy Surge'],
        'summer_heat'       => ['name' => 'Magnolia Ube Ice Cream 1.5L Tub', 'cat' => 'Frozen Foods', 'buyer' => 'Kids & Summer Hosts', 'surge' => '+85% Summer Surge'],
    ];
    $opp = $opps[$dayType] ?? ['name' => 'Nestlé All-Purpose Cream 250ml', 'cat' => 'Dairy Products', 'buyer' => 'Dessert Makers', 'surge' => '+60% Surge'];

    $pickedWants[] = [
        'product_id'               => null,
        'product_name'             => $opp['name'],
        'category'                 => $opp['cat'],
        'why_people_want_this'     => "High external consumer crave item trending across Philippine supermarkets during {$activeDayLabel}.",
        'target_buyer'             => $opp['buyer'],
        'demand_surge'             => $opp['surge'],
        'is_market_opportunity'    => true,
        'retail_merchandising_tip' => "Top recommended SKU to onboard for incremental basket size expansion.",
        'stock_advice'             => "Not currently stocked in catalog. High-opportunity item to procure from distributors."
    ];

    $geminiResponse = [
        'consumer_sentiment_summary' => $fallbackSentiment,
        'spending_power_level'       => $fallbackMood,
        'primary_driver'             => $fallbackDriver,
        'trending_categories'        => array_map(function($cat) {
            return ['name' => $cat, 'lift_pct' => '+65%', 'demand_level' => 'High'];
        }, array_slice($fallbackTargetCats, 0, 4)),
        'top_consumer_wants'         => $pickedWants
    ];
    $geminiModelUsed = 'Philippine Market Intelligence Engine (Live DB Sync)';
    $isAiLive = false;
}

// ── 5. Cross-Reference & Dynamic Inventory Metric Binding ───────────────────
$processedWants = [];
foreach ($geminiResponse['top_consumer_wants'] as $want) {
    $matched = null;
    $pId = !empty($want['product_id']) ? (int)$want['product_id'] : null;

    if ($pId && isset($catalogById[$pId])) {
        $matched = $catalogById[$pId];
    } else {
        $matched = matchWithCatalog($want['product_name'] ?? '', $catalogProducts);
    }

    $matchedInStock = false;
    $currentStock = 0;
    $reorderPoint = 0;
    $productId = null;
    $stockAdvice = '';
    $stockStatus = 'opportunity';
    $statusBadge = 'market_opportunity';

    if ($matched) {
        $matchedInStock = true;
        $productId = $matched['id'];
        $currentStock = $matched['stock'];
        $reorderPoint = $matched['reorder'];
        $buffer = $currentStock - $reorderPoint;

        if ($currentStock <= 0) {
            $stockStatus = 'out_of_stock';
            $statusBadge = 'critical_stockout';
            $stockAdvice = (!empty($want['stock_advice']) && stripos($want['stock_advice'], 'stock') !== false)
                ? $want['stock_advice']
                : "Out of Stock (0 units remaining • Min threshold: {$reorderPoint}). Urgent purchase order required before peak {$dayOfWeek} demand.";
        } elseif ($currentStock <= $reorderPoint) {
            $stockStatus = 'low_stock';
            $statusBadge = 'low_stock';
            $stockAdvice = (!empty($want['stock_advice']) && stripos($want['stock_advice'], 'stock') !== false)
                ? $want['stock_advice']
                : "Low Stock: Only {$currentStock} units left (below minimum reorder point of {$reorderPoint}). Restock immediately to capture peak demand.";
        } else {
            $stockStatus = 'in_stock';
            $statusBadge = 'in_stock';
            $stockAdvice = (!empty($want['stock_advice']) && (stripos($want['stock_advice'], 'stock') !== false || stripos($want['stock_advice'], 'shelf') !== false || stripos($want['stock_advice'], 'unit') !== false))
                ? $want['stock_advice']
                : "Healthy Stock: {$currentStock} units available (+{$buffer} units above reorder threshold). Position on prominent front displays.";
        }
    } else {
        $matchedInStock = false;
        $stockStatus = 'opportunity';
        $statusBadge = 'market_opportunity';
        $stockAdvice = (!empty($want['stock_advice']) && (stripos($want['stock_advice'], 'catalog') !== false || stripos($want['stock_advice'], 'onboard') !== false))
            ? $want['stock_advice']
            : "High-intent market SKU not yet in warehouse catalog. Recommended candidate to onboard to capture local retail spend.";
    }

    $processedWants[] = [
        'product_name'             => $matched ? $matched['name'] : ($want['product_name'] ?? 'Product'),
        'category'                 => $matched ? $matched['category'] : ($want['category'] ?? 'Retail Goods'),
        'why_people_want_this'     => $want['why_people_want_this'] ?? '',
        'target_buyer'             => $want['target_buyer'] ?? 'General Shoppers',
        'demand_surge'             => $want['demand_surge'] ?? '+50% Surge',
        'retail_merchandising_tip' => $want['retail_merchandising_tip'] ?? '',
        'matched_in_stock'         => $matchedInStock,
        'product_id'               => $productId,
        'current_stock'            => $currentStock,
        'reorder_point'            => $reorderPoint,
        'stock_status'             => $stockStatus,
        'status_badge'             => $statusBadge,
        'stock_advice'             => $stockAdvice
    ];
}

$conn->close();

echo json_encode([
    'success'                    => true,
    'is_ai_live'                 => $isAiLive,
    'model'                      => $geminiModelUsed,
    'target_date'                => $formattedDate,
    'day_of_week'                => $dayOfWeek,
    'day_type'                   => $dayType,
    'day_type_label'             => $activeDayLabel,
    'region'                     => strtoupper($region),
    'consumer_sentiment_summary' => $geminiResponse['consumer_sentiment_summary'] ?? '',
    'spending_power_level'       => $geminiResponse['spending_power_level'] ?? 'High',
    'primary_driver'             => $geminiResponse['primary_driver'] ?? $activeDayLabel,
    'trending_categories'        => $geminiResponse['trending_categories'] ?? [],
    'top_consumer_wants'         => $processedWants
]);
