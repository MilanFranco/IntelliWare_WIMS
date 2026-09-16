<?php
/**
 * src/services/PhilippineSeasonalService.php
 * Comprehensive Philippine Seasonal Market Intelligence & Demand Forecasting Service.
 * Provides month-by-month analysis of in-demand products, cultural events, weather drivers,
 * category multipliers, and real-time inventory gap calculations for the Philippine market.
 */

class PhilippineSeasonalService
{
    /**
     * Get complete Philippine seasonal profile for a given month (1 to 12).
     */
    public static function getMonthProfile(int $month): array
    {
        $profiles = self::getAllMonthProfiles();
        $m = max(1, min(12, $month));
        return $profiles[$m] ?? $profiles[1];
    }

    /**
     * Get 12-month calendar profiles of Philippine seasonal trends.
     */
    public static function getAllMonthProfiles(): array
    {
        return [
            1 => [
                'month_num'    => 1,
                'month_name'   => 'January',
                'short_name'   => 'Jan',
                'tagline'      => 'Post-Holiday Recovery & Staple Restocking',
                'season_badge' => 'Bagong Taon / Budget Reset',
                'season_icon'  => '⚖️',
                'weather_ph'   => 'Amihan (Cool Northeast Monsoon)',
                'cultural_drivers' => [
                    'Post-holiday household budget recovery and tightening',
                    'Health & wellness New Year resolutions',
                    'Replenishment of depleted pantry staples after New Year feasts',
                    'Feast of the Black Nazarene (Quiapo) & Sinulog / Ati-Atihan festivals'
                ],
                'in_demand_categories' => [
                    'Dry Goods'        => 1.35, // 35% above average
                    'Canned Goods'     => 1.40,
                    'Beverages'        => 1.20,
                    'Dairy Products'   => 1.15,
                    'Personal Care'    => 1.10,
                    'Frozen Foods'     => 0.90,
                ],
                'top_products' => [
                    [
                        'name' => 'Premium Rice (Sinandomeng / Dinorado 5kg & 25kg)',
                        'category' => 'Dry Goods',
                        'keywords' => ['rice', 'sinandomeng', 'dinorado', 'jasmin'],
                        'demand_lift' => '+45%',
                        'multiplier' => 1.45,
                        'reason' => 'Pantry staples need complete replenishment after heavy Christmas & New Year celebrations.',
                        'priority' => 'critical'
                    ],
                    [
                        'name' => 'Canned Tuna & Sardines (Century Tuna, 555, Mega)',
                        'category' => 'Canned Goods',
                        'keywords' => ['tuna', 'sardine', 'century', 'mega', '555', 'canned'],
                        'demand_lift' => '+40%',
                        'multiplier' => 1.40,
                        'reason' => 'Budget-friendly, high-protein pantry essentials as households recover from holiday expenses.',
                        'priority' => 'high'
                    ],
                    [
                        'name' => 'Instant & Roast Coffee (Nescafé, Kopiko, Great Taste)',
                        'category' => 'Beverages',
                        'keywords' => ['coffee', 'nescafe', 'kopiko', 'great taste'],
                        'demand_lift' => '+30%',
                        'multiplier' => 1.30,
                        'reason' => 'Cooler Amihan mornings drive steady hot beverage consumption across all regions.',
                        'priority' => 'high'
                    ],
                    [
                        'name' => 'Oats, Cereals & Healthy Dairy (Quaker Oats, Low Fat Milk)',
                        'category' => 'Dry Goods',
                        'keywords' => ['oat', 'quaker', 'cereal', 'oatside', 'milk'],
                        'demand_lift' => '+25%',
                        'multiplier' => 1.25,
                        'reason' => 'New Year fitness and healthy lifestyle resolutions spike breakfast cereal purchases.',
                        'priority' => 'medium'
                    ],
                    [
                        'name' => 'Sliced Bread Loaves & Spreads (Gardenia, Sandwich Spreads)',
                        'category' => 'Bakery Products',
                        'keywords' => ['bread', 'gardenia', 'loaf', 'spread', 'mayo'],
                        'demand_lift' => '+20%',
                        'multiplier' => 1.20,
                        'reason' => 'Quick, affordable workday breakfasts and student snack replenishment.',
                        'priority' => 'medium'
                    ]
                ]
            ],
            2 => [
                'month_num'    => 2,
                'month_name'   => 'February',
                'short_name'   => 'Feb',
                'tagline'      => "Valentine's, Confectionery & Baon Snacks",
                'season_badge' => "Valentine's & Midterm",
                'season_icon'  => '💖',
                'weather_ph'   => 'Amihan Transition (Pleasant to Warm)',
                'cultural_drivers' => [
                    "Valentine's Day romantic celebrations, dinners & gifts",
                    'Chinese / Lunar New Year (Binondo festivities & tikoy feasts)',
                    'School & University midterm exam study sessions',
                    'Payday weekend gift giving & date night spending'
                ],
                'in_demand_categories' => [
                    'Snacks and Biscuits'          => 1.50,
                    'Alcohol and Spirits'          => 1.35,
                    'Personal Care'                => 1.30,
                    'Bakery Products'              => 1.25,
                    'Frozen Foods'                 => 1.20,
                    'Condiments and Sauces'        => 1.10,
                ],
                'top_products' => [
                    [
                        'name' => 'Chocolates & Confectionery (Cadbury, Toblerone, Ferrero)',
                        'category' => 'Snacks and Biscuits',
                        'keywords' => ['chocolate', 'cadbury', 'toblerone', 'ferrero', 'candy'],
                        'demand_lift' => '+80%',
                        'multiplier' => 1.80,
                        'reason' => "Valentine's week triggers the annual peak in gifting sweets, boxed chocolates, and candies.",
                        'priority' => 'critical'
                    ],
                    [
                        'name' => 'Wines, Cocktails & Spirits (Red Wine, Smirnoff, Alfonso)',
                        'category' => 'Beverages',
                        'keywords' => ['wine', 'spirit', 'alfonso', 'smirnoff', 'beer'],
                        'demand_lift' => '+45%',
                        'multiplier' => 1.45,
                        'reason' => 'Romantic dinners, Valentine gatherings, and Chinese New Year family toasts.',
                        'priority' => 'high'
                    ],
                    [
                        'name' => 'Ready-to-Fry Chicken Nuggets & Hotdogs (Purefoods, CDO)',
                        'category' => 'Frozen Foods',
                        'keywords' => ['nugget', 'hotdog', 'purefoods', 'cdo', 'tender juicy'],
                        'demand_lift' => '+35%',
                        'multiplier' => 1.35,
                        'reason' => 'Convenient school baon snacks and quick meals during busy midterm schedules.',
                        'priority' => 'high'
                    ],
                    [
                        'name' => 'Specialty Cakes & Pastries (Red Ribbon, Goldilocks style)',
                        'category' => 'Bakery Products',
                        'keywords' => ['cake', 'pastry', 'cupcake', 'goldilocks', 'bakery'],
                        'demand_lift' => '+40%',
                        'multiplier' => 1.40,
                        'reason' => 'Surge in celebratory cake purchases for Valentine events and birthdays.',
                        'priority' => 'high'
                    ],
                    [
                        'name' => 'Personal Care & Fragrances (Dove, Nivea, Rexona, Cologne)',
                        'category' => 'Personal Care',
                        'keywords' => ['soap', 'dove', 'nivea', 'lotion', 'perfume', 'cologne'],
                        'demand_lift' => '+25%',
                        'multiplier' => 1.25,
                        'reason' => 'Grooming and personal care purchases surge ahead of Valentine dates and social outings.',
                        'priority' => 'medium'
                    ]
                ]
            ],
            3 => [
                'month_num'    => 3,
                'month_name'   => 'March',
                'short_name'   => 'Mar',
                'tagline'      => 'Early Summer Heat & Lenten Transition',
                'season_badge' => 'Early Summer / Tag-init',
                'season_icon'  => '☀️',
                'weather_ph'   => 'Onset of Tag-init (Dry & Rising Temperatures)',
                'cultural_drivers' => [
                    'Early summer heatwave onset across Luzon, Visayas, and Mindanao',
                    'Lenten fasting (Kuwaresma / Friday meat abstinence)',
                    'Graduation & Moving-Up celebrations and banquets',
                    'Fire Prevention Month awareness & emergency water stocking'
                ],
                'in_demand_categories' => [
                    'Beverages'                    => 1.65,
                    'Frozen Foods'                 => 1.30,
                    'Canned Goods'                 => 1.35,
                    'Dairy Products'               => 1.25,
                    'Household Items'              => 1.15,
                ],
                'top_products' => [
                    [
                        'name' => 'Cold Beverages & Soda (Coca-Cola, Sprite, Royal 1.5L)',
                        'category' => 'Beverages',
                        'keywords' => ['coca-cola', 'coke', 'sprite', 'royal', 'pepsi', 'soft drink'],
                        'demand_lift' => '+65%',
                        'multiplier' => 1.65,
                        'reason' => 'Rising temperatures drive intense demand for cold carbonated drinks and family packs.',
                        'priority' => 'critical'
                    ],
                    [
                        'name' => 'Bottled Mineral Water & Ready-to-Drink Juices (Nature Spring, Summit, C2)',
                        'category' => 'Beverages',
                        'keywords' => ['water', 'mineral', 'nature spring', 'summit', 'c2', 'juice'],
                        'demand_lift' => '+55%',
                        'multiplier' => 1.55,
                        'reason' => 'Hydration demand accelerates as outdoor heat index reaches high thresholds.',
                        'priority' => 'critical'
                    ],
                    [
                        'name' => 'Frozen Fish Fillet & Seafood (Bangus, Dory, Shrimps)',
                        'category' => 'Frozen Foods',
                        'keywords' => ['fish', 'bangus', 'dory', 'tilapia', 'seafood', 'shrimp'],
                        'demand_lift' => '+45%',
                        'multiplier' => 1.45,
                        'reason' => 'Lenten meat fasting drives strong substitution to fish and seafood viands.',
                        'priority' => 'high'
                    ],
                    [
                        'name' => 'Canned Tuna Flakes & Mackerel (San Marino, Century Tuna)',
                        'category' => 'Canned Goods',
                        'keywords' => ['tuna', 'san marino', 'century', 'mackerel'],
                        'demand_lift' => '+40%',
                        'multiplier' => 1.40,
                        'reason' => 'Preferred quick Lenten meal for households and karinderyas observing meat abstinence.',
                        'priority' => 'high'
                    ],
                    [
                        'name' => 'Party Pasta & Tomato Sauces (Royal Spaghetti, Del Monte)',
                        'category' => 'Dry Goods',
                        'keywords' => ['pasta', 'spaghetti', 'macaroni', 'del monte', 'sauce'],
                        'demand_lift' => '+30%',
                        'multiplier' => 1.30,
                        'reason' => 'Graduation, moving-up parties and year-end school celebrations.',
                        'priority' => 'medium'
                    ]
                ]
            ],
            4 => [
                'month_num'    => 4,
                'month_name'   => 'April',
                'short_name'   => 'Apr',
                'tagline'      => 'Peak Summer Vacation & Semana Santa',
                'season_badge' => 'Peak Summer / Holy Week',
                'season_icon'  => '🏖️',
                'weather_ph'   => 'Peak Tag-init (Highest Heat Index of the Year)',
                'cultural_drivers' => [
                    'Semana Santa / Holy Week countrywide pilgrimage & travel rush',
                    'Peak summer beach outings, pool parties & out-of-town trips',
                    'Halo-halo & refreshing cold dessert season',
                    'Araw ng Kagitingan extended holiday weekend'
                ],
                'in_demand_categories' => [
                    'Beverages'                    => 1.85,
                    'Dairy Products'               => 1.70,
                    'Canned Goods'                 => 1.50,
                    'Personal Care'                => 1.40,
                    'Snacks and Biscuits'          => 1.35,
                ],
                'top_products' => [
                    [
                        'name' => 'Halo-Halo Ingredients (Evaporated Milk, Condensed Milk, Nata de Coco, Kaong)',
                        'category' => 'Dairy Products',
                        'keywords' => ['evap', 'condensed', 'milk', 'carnation', 'alaska', 'nata', 'kaong'],
                        'demand_lift' => '+90%',
                        'multiplier' => 1.90,
                        'reason' => 'Peak halo-halo, ice candy, and cold dessert preparation by households and street vendors.',
                        'priority' => 'critical'
                    ],
                    [
                        'name' => 'Bottled Water, Sports Drinks & Cold Sodas (Gatorade, Pocari Sweat, Coke)',
                        'category' => 'Beverages',
                        'keywords' => ['water', 'gatorade', 'pocari', 'coke', 'sprite', 'juice'],
                        'demand_lift' => '+85%',
                        'multiplier' => 1.85,
                        'reason' => 'Annual peak in consumer thirst and outdoor electrolyte replenishment during April heat.',
                        'priority' => 'critical'
                    ],
                    [
                        'name' => 'Canned Seafood & Tuna (Century Tuna, San Marino Corned Tuna)',
                        'category' => 'Canned Goods',
                        'keywords' => ['tuna', 'san marino', 'century', 'sardine', 'canned'],
                        'demand_lift' => '+60%',
                        'multiplier' => 1.60,
                        'reason' => 'Holy Week strict meat fasting across the entire country peaks in Holy Wednesday–Good Friday.',
                        'priority' => 'critical'
                    ],
                    [
                        'name' => 'Sunblock, Refreshing Soaps & Personal Hygiene (Safeguard, Nivea Sun, Dove)',
                        'category' => 'Personal Care',
                        'keywords' => ['sunblock', 'soap', 'safeguard', 'dove', 'shampoo', 'lotion'],
                        'demand_lift' => '+45%',
                        'multiplier' => 1.45,
                        'reason' => 'Beach vacations and multi-shower daily hygiene routines due to high heat & humidity.',
                        'priority' => 'high'
                    ],
                    [
                        'name' => 'Travel Snacks, Chips & Biscuits (SkyFlakes, Piattos, Chippy, Rebisco)',
                        'category' => 'Snacks and Biscuits',
                        'keywords' => ['biscuit', 'skyflakes', 'rebisco', 'piattos', 'chippy', 'snack'],
                        'demand_lift' => '+35%',
                        'multiplier' => 1.35,
                        'reason' => 'Essential "baon" for long provincial road trips, bus travels, and island hopping.',
                        'priority' => 'high'
                    ]
                ]
            ],
            5 => [
                'month_num'    => 5,
                'month_name'   => 'May',
                'short_name'   => 'May',
                'tagline'      => 'Fiesta Season, Santacruzan & Mother\'s Day',
                'season_badge' => 'Fiesta Month / Santacruzan',
                'season_icon'  => '🎊',
                'weather_ph'   => 'Late Summer Transition to Habagat (Hot with Thunderstorms)',
                'cultural_drivers' => [
                    'Flores de Mayo & Santacruzan celebrations in every town & barangay',
                    'Town fiesta banquets, lechon & viand catering across provinces',
                    'Mother\'s Day grand family reunions & dining',
                    'Pre-school year shopping and budget prep'
                ],
                'in_demand_categories' => [
                    'Condiments and Sauces'        => 1.60,
                    'Alcohol and Spirits'          => 1.55,
                    'Dry Goods'                    => 1.50,
                    'Frozen Foods'                 => 1.45,
                    'Beverages'                    => 1.40,
                ],
                'top_products' => [
                    [
                        'name' => 'Cooking Essentials (Cooking Oil, Soy Sauce, Vinegar, Tomato Paste, Del Monte)',
                        'category' => 'Condiments and Sauces',
                        'keywords' => ['oil', 'soy sauce', 'silver swan', 'datu puti', 'vinegar', 'tomato', 'del monte'],
                        'demand_lift' => '+65%',
                        'multiplier' => 1.65,
                        'reason' => 'Town fiestas demand enormous bulk cooking for Menudo, Afritada, Mechado, and Adobo feasts.',
                        'priority' => 'critical'
                    ],
                    [
                        'name' => 'Beer, Gin & Spirits (San Miguel Pale Pilsen, Red Horse, Ginebra San Miguel)',
                        'category' => 'Beverages',
                        'keywords' => ['beer', 'red horse', 'san miguel', 'ginebra', 'emperador', 'gin'],
                        'demand_lift' => '+55%',
                        'multiplier' => 1.55,
                        'reason' => 'Fiesta inuman sessions, barangay street parties, and Mother\'s Day family toasts.',
                        'priority' => 'high'
                    ],
                    [
                        'name' => 'Party Spaghetti, Macaroni & Sauces (Royal, Fiesta Pasta)',
                        'category' => 'Dry Goods',
                        'keywords' => ['pasta', 'spaghetti', 'macaroni', 'royal', 'fiesta'],
                        'demand_lift' => '+50%',
                        'multiplier' => 1.50,
                        'reason' => 'Filipino Sweet-Style Spaghetti is a mandatory staple in every May fiesta and Santacruzan banquet.',
                        'priority' => 'high'
                    ],
                    [
                        'name' => 'Frozen Pork, Chicken & Marinated Viands (Pork Belly, Chicken Wings)',
                        'category' => 'Frozen Foods',
                        'keywords' => ['pork', 'chicken', 'meat', 'beef', 'frozen'],
                        'demand_lift' => '+45%',
                        'multiplier' => 1.45,
                        'reason' => 'Heavy demand for bulk meat cuts for grilling, barbecue, and catering dishes.',
                        'priority' => 'high'
                    ],
                    [
                        'name' => 'Soft Drinks 1.5L / Cases (Coke, Sprite, Royal Cases)',
                        'category' => 'Beverages',
                        'keywords' => ['coke', 'sprite', 'royal', 'soft drink', 'beverage'],
                        'demand_lift' => '+40%',
                        'multiplier' => 1.40,
                        'reason' => 'Serving visitors and guests who go house-to-house during barangay fiesta tours.',
                        'priority' => 'medium'
                    ]
                ]
            ],
            6 => [
                'month_num'    => 6,
                'month_name'   => 'June',
                'short_name'   => 'Jun',
                'tagline'      => 'Balik-Eskwela & Rainy Season Kickoff',
                'season_badge' => 'Back-to-School / Tag-ulan',
                'season_icon'  => '🎒',
                'weather_ph'   => 'Onset of Habagat (Southwest Monsoon / Rainy Days)',
                'cultural_drivers' => [
                    'Balik-Eskwela (School reopening for basic education & universities)',
                    'Onset of the Philippine rainy season (Tag-ulan)',
                    'Father\'s Day celebrations & Philippine Independence Day (June 12)',
                    'Morning rush breakfast routines resume for students & parents'
                ],
                'in_demand_categories' => [
                    'Stationery and Office Supplies' => 2.20,
                    'Snacks and Biscuits'            => 1.65,
                    'Bakery Products'                => 1.50,
                    'Beverages'                      => 1.40,
                    'Household Items'                => 1.30,
                ],
                'top_products' => [
                    [
                        'name' => 'School Stationery (Notebooks, Ballpens, Intermediate Pads, Crayons)',
                        'category' => 'Stationery and Office Supplies',
                        'keywords' => ['notebook', 'pen', 'paper', 'pad', 'stationery', 'ballpen', 'crayon', 'pencil'],
                        'demand_lift' => '+120%',
                        'multiplier' => 2.20,
                        'reason' => 'Balik-Eskwela shopping represents the highest annual peak for stationery and paper supplies.',
                        'priority' => 'critical'
                    ],
                    [
                        'name' => 'Student Baon Packs & Biscuits (Fita, Combi, Oishi, Rebisco, Fudgee Barr)',
                        'category' => 'Snacks and Biscuits',
                        'keywords' => ['biscuit', 'fudgee', 'rebisco', 'fita', 'oishi', 'snack', 'cake'],
                        'demand_lift' => '+65%',
                        'multiplier' => 1.65,
                        'reason' => 'Parents stock up on individual snack packs for children\'s daily school lunchboxes (baon).',
                        'priority' => 'critical'
                    ],
                    [
                        'name' => 'Breakfast Bread Loaves & Sandwich Spreads (Gardenia, Cheez Whiz, Mayo)',
                        'category' => 'Bakery Products',
                        'keywords' => ['bread', 'gardenia', 'loaf', 'cheez whiz', 'spread', 'mayo'],
                        'demand_lift' => '+50%',
                        'multiplier' => 1.50,
                        'reason' => 'Fast morning breakfast preparation before school departure at 6:00 AM.',
                        'priority' => 'high'
                    ],
                    [
                        'name' => 'Powdered Chocolate & Milk Drinks (Milo, Bear Brand, Ovaltine)',
                        'category' => 'Beverages',
                        'keywords' => ['milo', 'bear brand', 'milk', 'chocolate', 'ovaltine'],
                        'demand_lift' => '+45%',
                        'multiplier' => 1.45,
                        'reason' => 'Energy drinks for school mornings and warm chocolate for rainy mornings.',
                        'priority' => 'high'
                    ],
                    [
                        'name' => 'Laundry Detergent & Fabric Conditioner (Ariel, Tide, Downy)',
                        'category' => 'Household Items',
                        'keywords' => ['detergent', 'ariel', 'tide', 'surf', 'downy', 'bleach'],
                        'demand_lift' => '+30%',
                        'multiplier' => 1.30,
                        'reason' => 'School uniforms require frequent washing, and rainy days need anti-kulob fabric softeners.',
                        'priority' => 'medium'
                    ]
                ]
            ],
            7 => [
                'month_num'    => 7,
                'month_name'   => 'July',
                'short_name'   => 'Jul',
                'tagline'      => 'Habagat Monsoon, Typhoons & Comfort Foods',
                'season_badge' => 'Typhoon Season / Tag-ulan',
                'season_icon'  => '🌧️',
                'weather_ph'   => 'Peak Habagat Monsoon (Frequent Typhoons & Flooding)',
                'cultural_drivers' => [
                    'Typhoon season warnings (PAGASA storm signals) & rainy stay-at-home days',
                    'Class suspensions and remote work days during monsoon floods',
                    'Flu and cold season health remedies',
                    'Desire for warm, fast-cooking comfort meals'
                ],
                'in_demand_categories' => [
                    'Noodles and Instant Food'     => 1.85,
                    'Canned Goods'                 => 1.65,
                    'Beverages'                    => 1.45,
                    'Household Items'              => 1.35,
                    'Personal Care'                => 1.25,
                ],
                'top_products' => [
                    [
                        'name' => 'Instant Noodles & Soup Bowls (Lucky Me! Pancit Canton, La Paz Batchoy, Bulalo, Jin Ramen)',
                        'category' => 'Dry Goods',
                        'keywords' => ['noodle', 'lucky me', 'pancit canton', 'ramen', 'soup', 'instant'],
                        'demand_lift' => '+85%',
                        'multiplier' => 1.85,
                        'reason' => 'The ultimate rainy day Filipino comfort food and quick emergency meals during storms.',
                        'priority' => 'critical'
                    ],
                    [
                        'name' => 'Canned Corned Beef & Meat Loaf (Purefoods, Argentina, Star)',
                        'category' => 'Canned Goods',
                        'keywords' => ['corned beef', 'purefoods', 'argentina', 'meat loaf', 'canned'],
                        'demand_lift' => '+65%',
                        'multiplier' => 1.65,
                        'reason' => 'Typhoon pantry stockpiling and easy hot viands when wet market access is limited.',
                        'priority' => 'critical'
                    ],
                    [
                        'name' => 'Instant Hot Coffee & 3-in-1 Packs (Nescafé Blend 43, Kopiko Blanca, Great Taste White)',
                        'category' => 'Beverages',
                        'keywords' => ['coffee', 'kopiko', 'nescafe', 'great taste', 'blanca'],
                        'demand_lift' => '+45%',
                        'multiplier' => 1.45,
                        'reason' => 'Continuous rainy, overcast weather causes frequent hot coffee drinking throughout the day.',
                        'priority' => 'high'
                    ],
                    [
                        'name' => 'Disinfectants, Cleaners & Bleach (Zonrox, Lysol, Domex)',
                        'category' => 'Household Items',
                        'keywords' => ['zonrox', 'bleach', 'cleaner', 'lysol', 'domex', 'disinfectant'],
                        'demand_lift' => '+35%',
                        'multiplier' => 1.35,
                        'reason' => 'Flooding cleanup, mud removal, and household sanitation during damp monsoon weeks.',
                        'priority' => 'medium'
                    ],
                    [
                        'name' => 'Vitamin C & Flu Remedies (Ascorbic Acid, Citrus Drinks)',
                        'category' => 'Personal Care',
                        'keywords' => ['vitamin', 'soap', 'hygiene', 'medicine', 'care'],
                        'demand_lift' => '+30%',
                        'multiplier' => 1.30,
                        'reason' => 'Immunity boosting during sudden temperature drops and wet monsoon season.',
                        'priority' => 'medium'
                    ]
                ]
            ],
            8 => [
                'month_num'    => 8,
                'month_name'   => 'August',
                'short_name'   => 'Aug',
                'tagline'      => 'Ghost Month, Emergency Stockpiles & Buwan ng Wika',
                'season_badge' => 'Emergency Stockpiling',
                'season_icon'  => '📦',
                'weather_ph'   => 'Mid-Monsoon (Heavy Rainfall & Southwest Winds)',
                'cultural_drivers' => [
                    'Ghost Month caution (slower big-ticket purchasing, steady FMCG demand)',
                    'Buwan ng Wika & National Heroes Day celebrations in schools',
                    'Disaster preparedness month & typhoon relief packaging',
                    'Long weekend getaways during Ninoy Aquino Day & National Heroes Day'
                ],
                'in_demand_categories' => [
                    'Dry Goods'                    => 1.60,
                    'Canned Goods'                 => 1.55,
                    'Bakery Products'              => 1.40,
                    'Snacks and Biscuits'          => 1.35,
                    'Household Items'              => 1.25,
                ],
                'top_products' => [
                    [
                        'name' => 'Rice Sacks (25kg & 50kg Master Sacks)',
                        'category' => 'Dry Goods',
                        'keywords' => ['rice', 'sack', 'sinandomeng', 'dinorado', 'jasmin'],
                        'demand_lift' => '+60%',
                        'multiplier' => 1.60,
                        'reason' => 'Households and LGUs stockpile full rice sacks for storm seasons and relief packing.',
                        'priority' => 'critical'
                    ],
                    [
                        'name' => 'Canned Sardines & Mackerel in Tomato Sauce (Mega, 555, Ligo)',
                        'category' => 'Canned Goods',
                        'keywords' => ['sardine', 'mega', '555', 'ligo', 'canned'],
                        'demand_lift' => '+55%',
                        'multiplier' => 1.55,
                        'reason' => 'Primary relief package item and high-volume pantry standby during stormy weeks.',
                        'priority' => 'high'
                    ],
                    [
                        'name' => 'Crackers & Long-Shelf Biscuits (SkyFlakes, Magic Flakes, Fita)',
                        'category' => 'Snacks and Biscuits',
                        'keywords' => ['skyflakes', 'magic', 'fita', 'biscuit', 'cracker'],
                        'demand_lift' => '+40%',
                        'multiplier' => 1.40,
                        'reason' => 'Non-perishable ready-to-eat food during power outages and heavy rainfall.',
                        'priority' => 'high'
                    ],
                    [
                        'name' => 'Hot Chocolate & Instant Champorado mixes',
                        'category' => 'Dry Goods',
                        'keywords' => ['champorado', 'chocolate', 'tablea', 'oat'],
                        'demand_lift' => '+35%',
                        'multiplier' => 1.35,
                        'reason' => 'Traditional rainy afternoon comfort snack paired with tuyo/sardines.',
                        'priority' => 'medium'
                    ],
                    [
                        'name' => 'Batteries, Matches & Flashlight Supplies',
                        'category' => 'Household Items',
                        'keywords' => ['battery', 'match', 'candle', 'emergency'],
                        'demand_lift' => '+30%',
                        'multiplier' => 1.30,
                        'reason' => 'Emergency preparedness against typhoon-induced power blackouts (brownouts).',
                        'priority' => 'medium'
                    ]
                ]
            ],
            9 => [
                'month_num'    => 9,
                'month_name'   => 'September',
                'short_name'   => 'Sep',
                'tagline'      => 'Ber Months Kickoff & Early Holiday Buildup',
                'season_badge' => 'Ber Months / Early Christmas',
                'season_icon'  => '🎄',
                'weather_ph'   => 'Monsoon Easing (Pleasant evenings & occasional typhoons)',
                'cultural_drivers' => [
                    'Kickoff of the Philippines\' world-famous "Ber Months" early Christmas countdown (Sept 1)',
                    'Jose Mari Chan music starts playing across malls and stores nationwide',
                    'Early holiday budget planning and bulk dry-goods layaways',
                    'Mid-year corporate bonus announcements & early gift hoarding'
                ],
                'in_demand_categories' => [
                    'Dry Goods'                    => 1.55,
                    'Dairy Products'               => 1.50,
                    'Condiments and Sauces'        => 1.45,
                    'Frozen Foods'                 => 1.35,
                    'Beverages'                    => 1.30,
                ],
                'top_products' => [
                    [
                        'name' => 'Spaghetti Noodles & Sweet Style Sauces (Royal, Fiesta, Del Monte)',
                        'category' => 'Dry Goods',
                        'keywords' => ['spaghetti', 'pasta', 'del monte', 'sauce', 'royal', 'fiesta'],
                        'demand_lift' => '+55%',
                        'multiplier' => 1.55,
                        'reason' => 'Early holiday shoppers begin hoarding non-perishable spaghetti sets before price hikes.',
                        'priority' => 'critical'
                    ],
                    [
                        'name' => 'Condensed & Evaporated Milk (Alaska, Carnation, Jolly Cow)',
                        'category' => 'Dairy Products',
                        'keywords' => ['condensed', 'evap', 'alaska', 'carnation', 'milk'],
                        'demand_lift' => '+50%',
                        'multiplier' => 1.50,
                        'reason' => 'Bakeries and dessert makers test recipe batches for upcoming Christmas fruit salads.',
                        'priority' => 'high'
                    ],
                    [
                        'name' => 'Fruit Cocktail Cans (Del Monte, Today\'s, Seasons 836g / 3kg)',
                        'category' => 'Canned Goods',
                        'keywords' => ['fruit cocktail', 'del monte', 'today', 'peach', 'canned fruit'],
                        'demand_lift' => '+45%',
                        'multiplier' => 1.45,
                        'reason' => 'Early retail inventory buildup for the quintessential Filipino Christmas fruit salad.',
                        'priority' => 'high'
                    ],
                    [
                        'name' => 'Frozen Hotdogs & Chicken Nuggets (Purefoods TJ, CDO Idol)',
                        'category' => 'Frozen Foods',
                        'keywords' => ['hotdog', 'purefoods', 'nugget', 'cdo', 'tender juicy'],
                        'demand_lift' => '+35%',
                        'multiplier' => 1.35,
                        'reason' => 'Children\'s birthday parties and early holiday family gatherings.',
                        'priority' => 'medium'
                    ],
                    [
                        'name' => 'Specialty Coffee & Powdered Creamers (Coffee-Mate, Nescafe Gold)',
                        'category' => 'Beverages',
                        'keywords' => ['coffee', 'creamer', 'coffee-mate', 'nescafe'],
                        'demand_lift' => '+30%',
                        'multiplier' => 1.30,
                        'reason' => 'Cooler early morning temperatures and guest entertaining preparations.',
                        'priority' => 'medium'
                    ]
                ]
            ],
            10 => [
                'month_num'    => 10,
                'month_name'   => 'October',
                'short_name'   => 'Oct',
                'tagline'      => 'Oktoberfest, Halloween & Pre-Undas Restock',
                'season_badge' => 'Oktoberfest / Pre-Undas',
                'season_icon'  => '🎃',
                'weather_ph'   => 'Onset of Amihan (Cooler breezes, pleasant climate)',
                'cultural_drivers' => [
                    'Oktoberfest beer festivals, bar nights & live music events',
                    'Trick-or-treat Halloween costume parties & confectionery rushes',
                    'Preparation for Undas (All Saints\' Day) cemetery visits and road trips',
                    'Early holiday bonus releases and Christmas bazaar stocking'
                ],
                'in_demand_categories' => [
                    'Alcohol and Spirits'          => 1.70,
                    'Snacks and Biscuits'          => 1.60,
                    'Household Items'              => 1.45,
                    'Bakery Products'              => 1.35,
                    'Beverages'                    => 1.35,
                ],
                'top_products' => [
                    [
                        'name' => 'Beer Cases & Premium Spirits (San Miguel Pale Pilsen, Red Horse, Alfonso I, Fundador)',
                        'category' => 'Beverages',
                        'keywords' => ['beer', 'red horse', 'san miguel', 'alfonso', 'fundador', 'whisky'],
                        'demand_lift' => '+70%',
                        'multiplier' => 1.70,
                        'reason' => 'Oktoberfest promotions and weekend street parties peak across all major urban centers.',
                        'priority' => 'critical'
                    ],
                    [
                        'name' => 'Halloween Candies & Sweet Treats (Chupa Chups, Gummy Bears, M&Ms, Flat Tops)',
                        'category' => 'Snacks and Biscuits',
                        'keywords' => ['candy', 'chocolate', 'flat tops', 'chupa', 'sweet', 'gummy'],
                        'demand_lift' => '+60%',
                        'multiplier' => 1.60,
                        'reason' => 'Subdivisions, condominiums, and schools host trick-or-treat community events.',
                        'priority' => 'critical'
                    ],
                    [
                        'name' => 'Candles, Matches & Cleaning Supplies (Undas Cemetery Prep)',
                        'category' => 'Household Items',
                        'keywords' => ['candle', 'match', 'cleaner', 'bleach', 'detergent'],
                        'demand_lift' => '+45%',
                        'multiplier' => 1.45,
                        'reason' => 'Households prepare grave-cleaning tools and bulk candles before cemetery visits on Nov 1.',
                        'priority' => 'high'
                    ],
                    [
                        'name' => 'Bakery Loaves, Empanadas & Snack Pies (Gardenia, Goldilocks)',
                        'category' => 'Bakery Products',
                        'keywords' => ['bread', 'gardenia', 'pie', 'empanada', 'cake'],
                        'demand_lift' => '+35%',
                        'multiplier' => 1.35,
                        'reason' => 'Travelers pack easy-to-carry snacks for long cemetery stays during Undas weekend.',
                        'priority' => 'medium'
                    ],
                    [
                        'name' => 'Party Soft Drinks & Chilled Juices 1.5L',
                        'category' => 'Beverages',
                        'keywords' => ['coke', 'sprite', 'royal', 'juice'],
                        'demand_lift' => '+35%',
                        'multiplier' => 1.35,
                        'reason' => 'Family gatherings and holiday party planning.',
                        'priority' => 'medium'
                    ]
                ]
            ],
            11 => [
                'month_num'    => 11,
                'month_name'   => 'November',
                'short_name'   => 'Nov',
                'tagline'      => 'Undas, 13th Month Pay & Early Christmas Rush',
                'season_badge' => 'Undas & 13th Month Pay',
                'season_icon'  => '🎁',
                'weather_ph'   => 'Amihan (Crisp, cooler mornings & evenings)',
                'cultural_drivers' => [
                    'Undas (All Saints\' & All Souls\' Day) nationwide family reunions (Nov 1-2)',
                    'Release of 13th Month Pay and Year-End bonuses begins mid-November',
                    'Black Friday, 11.11 Megasales & Corporate Christmas gift buying',
                    'Advance bulk purchasing of Christmas Hams, Queso de Bola, and Gift Baskets'
                ],
                'in_demand_categories' => [
                    'Dairy Products'               => 1.85,
                    'Dry Goods'                    => 1.75,
                    'Condiments and Sauces'        => 1.65,
                    'Frozen Foods'                 => 1.60,
                    'Snacks and Biscuits'          => 1.50,
                ],
                'top_products' => [
                    [
                        'name' => 'All-Purpose Cream & Condensed Milk (Nestlé Cream, Alaska Crema, Carnation)',
                        'category' => 'Dairy Products',
                        'keywords' => ['cream', 'nestle', 'crema', 'condensed', 'alaska', 'carnation'],
                        'demand_lift' => '+85%',
                        'multiplier' => 1.85,
                        'reason' => 'Massive demand for mango float, fruit salad, macaroni salad, and holiday baked desserts.',
                        'priority' => 'critical'
                    ],
                    [
                        'name' => 'Queso de Bola & Cheddar Cheese (Magnolia, Eden, Kraft, Marca Piña)',
                        'category' => 'Dairy Products',
                        'keywords' => ['cheese', 'eden', 'magnolia', 'queso de bola', 'kraft'],
                        'demand_lift' => '+75%',
                        'multiplier' => 1.75,
                        'reason' => 'Mandatory centerpiece for the traditional Filipino Noche Buena table setting.',
                        'priority' => 'critical'
                    ],
                    [
                        'name' => 'Gourmet Pasta & Spaghetti Sets (San Remo, Royal, Del Monte Bundles)',
                        'category' => 'Dry Goods',
                        'keywords' => ['pasta', 'spaghetti', 'san remo', 'royal', 'del monte', 'macaroni'],
                        'demand_lift' => '+70%',
                        'multiplier' => 1.70,
                        'reason' => 'Corporate Christmas basket packing and household pantry holiday stocking.',
                        'priority' => 'critical'
                    ],
                    [
                        'name' => 'Christmas Hams & Cured Meats (Purefoods Fiesta Ham, CDO Holiday Ham)',
                        'category' => 'Frozen Foods',
                        'keywords' => ['ham', 'fiesta ham', 'purefoods', 'cdo', 'holiday ham', 'bacon'],
                        'demand_lift' => '+65%',
                        'multiplier' => 1.65,
                        'reason' => 'Companies and households begin claiming pre-ordered holiday hams for early celebrations.',
                        'priority' => 'high'
                    ],
                    [
                        'name' => 'Graham Crackers & Gelatin Powder (M.Y. San Grahams, Alsa Gelatin)',
                        'category' => 'Snacks and Biscuits',
                        'keywords' => ['graham', 'm.y. san', 'gelatin', 'gulaman', 'florence'],
                        'demand_lift' => '+55%',
                        'multiplier' => 1.55,
                        'reason' => 'Essential crust base for Mango Graham Float, the #1 dessert during Philippine festivities.',
                        'priority' => 'high'
                    ]
                ]
            ],
            12 => [
                'month_num'    => 12,
                'month_name'   => 'December',
                'short_name'   => 'Dec',
                'tagline'      => 'Pasko, Noche Buena, Simbang Gabi & Media Noche',
                'season_badge' => 'Pasko / Grand Noche Buena Peak',
                'season_icon'  => '⭐',
                'weather_ph'   => 'Peak Amihan (Coldest breeze of the year, holiday vibe)',
                'cultural_drivers' => [
                    'Pasko (Christmas Day) & Grand Noche Buena feast (Dec 24 midnight)',
                    'Simbang Gabi (9-day dawn novena masses with bibingka & puto bumbong)',
                    'Media Noche (New Year\'s Eve feast with round fruits & lechon)',
                    '100% bonus liquidity, corporate gift giving, and peak consumer spending'
                ],
                'in_demand_categories' => [
                    'Dry Goods'                    => 2.50, // Super Peak (150% above average)
                    'Dairy Products'               => 2.40,
                    'Frozen Foods'                 => 2.20,
                    'Condiments and Sauces'        => 2.10,
                    'Alcohol and Spirits'          => 2.30,
                    'Snacks and Biscuits'          => 1.90,
                    'Beverages'                    => 2.00,
                ],
                'top_products' => [
                    [
                        'name' => 'Fiesta Ham & Premium Smoked Ham (Purefoods Fiesta Ham, CDO Holiday Ham 1kg+)',
                        'category' => 'Frozen Foods',
                        'keywords' => ['ham', 'fiesta ham', 'cdo', 'purefoods', 'cured'],
                        'demand_lift' => '+150%',
                        'multiplier' => 2.50,
                        'reason' => 'The absolute crowning centerpiece of every Filipino Christmas Noche Buena feast.',
                        'priority' => 'critical'
                    ],
                    [
                        'name' => 'All-Purpose Cream, Condensed Milk & Fruit Cocktail (Nestlé, Alaska, Del Monte)',
                        'category' => 'Dairy Products',
                        'keywords' => ['cream', 'nestle', 'condensed', 'fruit cocktail', 'alaska', 'carnation'],
                        'demand_lift' => '+140%',
                        'multiplier' => 2.40,
                        'reason' => 'Fruit Salad and Mango Float are prepared in virtually 100% of Filipino households on Dec 24 and Dec 31.',
                        'priority' => 'critical'
                    ],
                    [
                        'name' => 'Queso de Bola & Premium Cheeses (Marca Piña, Magnolia, Eden)',
                        'category' => 'Dairy Products',
                        'keywords' => ['queso de bola', 'cheese', 'eden', 'magnolia', 'marca'],
                        'demand_lift' => '+130%',
                        'multiplier' => 2.30,
                        'reason' => 'Red wax ball cheese paired with Christmas ham and pan de sal is a timeless Filipino tradition.',
                        'priority' => 'critical'
                    ],
                    [
                        'name' => 'Sweet-Style Spaghetti, Elbow Macaroni & Sauces (Royal, Del Monte, UFC)',
                        'category' => 'Dry Goods',
                        'keywords' => ['spaghetti', 'macaroni', 'pasta', 'sauce', 'del monte', 'royal'],
                        'demand_lift' => '+120%',
                        'multiplier' => 2.20,
                        'reason' => 'Massive volume consumed during Simbang Gabi breakfasts, party potlucks, and Noche Buena.',
                        'priority' => 'critical'
                    ],
                    [
                        'name' => 'Wines, Whisky, Rum & Party Soda (Carlo Rossi, Johnnie Walker, Tanduay Select, Coke 1.5L)',
                        'category' => 'Beverages',
                        'keywords' => ['wine', 'whisky', 'rum', 'beer', 'coke', 'royal', 'sprite', 'tanduay'],
                        'demand_lift' => '+110%',
                        'multiplier' => 2.10,
                        'reason' => 'Year-end company banquets, Christmas Eve toasts, and New Year\'s Eve Media Noche countdowns.',
                        'priority' => 'critical'
                    ]
                ]
            ]
        ];
    }

    private static ?array $cachedAllProducts = null;
    private static ?array $cachedMlPredictions = null;
    private static array $monthCache = [];

    /**
     * Clear the static cache (useful if inventory updates dynamically).
     */
    public static function clearCache(): void
    {
        self::$cachedAllProducts = null;
        self::$cachedMlPredictions = null;
        self::$monthCache = [];
    }

    /**
     * Cross-reference the Philippine in-demand product list for a specific month
     * against actual active inventory records in the database.
     */
    public static function matchInventoryForMonth(?mysqli $conn, int $month): array
    {
        if (isset(self::$monthCache[$month])) {
            return self::$monthCache[$month];
        }

        $profile = self::getMonthProfile($month);
        $topProducts = $profile['top_products'];
        $results = [];

        // Pre-fetch all active inventory products ONCE for all months in this request
        if (self::$cachedAllProducts === null && $conn !== null) {
            $productSql = "
                SELECT 
                    p.product_id,
                    p.product_name,
                    p.category AS category_name,
                    p.quantity AS current_stock,
                    p.reorder_point,
                    COALESCE(t.monthly_sales, 0) AS baseline_monthly_sales
                FROM products p
                LEFT JOIN (
                    SELECT product_id, SUM(quantity) AS monthly_sales
                    FROM transactions
                    WHERE transaction_type = 'stock_out'
                      AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    GROUP BY product_id
                ) t ON p.product_id = t.product_id
                WHERE p.archived = 0
                ORDER BY p.quantity ASC
            ";
            $productRes = $conn->query($productSql);
            self::$cachedAllProducts = $productRes ? $productRes->fetch_all(MYSQLI_ASSOC) : [];
        }
        $allProducts = self::$cachedAllProducts ?? [];

        // Pre-fetch all ML predictions grouped by month ONCE for all months
        if (self::$cachedMlPredictions === null && $conn !== null) {
            self::$cachedMlPredictions = [];
            $mlSql = "
                SELECT product_id, MONTH(forecast_date) as f_month, SUM(predicted_quantity) as total_predicted
                FROM forecast_results
                WHERE product_id > 0
                GROUP BY product_id, MONTH(forecast_date)
            ";
            $mlRes = @$conn->query($mlSql);
            if ($mlRes && $mlRes->num_rows > 0) {
                while ($r = $mlRes->fetch_assoc()) {
                    $mNum = (int)$r['f_month'];
                    $pId  = (int)$r['product_id'];
                    self::$cachedMlPredictions[$mNum][$pId] = (float)$r['total_predicted'];
                }
            }
        }
        $mlPredictions = self::$cachedMlPredictions[$month] ?? [];

        $matchedProductIds = [];

        foreach ($topProducts as $item) {
            $keywords = $item['keywords'] ?? [];
            $cat = $item['category'] ?? '';
            $multiplier = (float)($item['multiplier'] ?? 1.25);
            $matched = null;

            // 1. Try to find matched product in inventory by keywords
            foreach ($allProducts as $p) {
                $pName = strtolower($p['product_name']);
                $pCat = strtolower($p['category_name'] ?? '');
                $matchFound = false;

                foreach ($keywords as $kw) {
                    if (stripos($pName, strtolower($kw)) !== false) {
                        $matchFound = true;
                        break;
                    }
                }

                if (!$matchFound && !empty($cat) && stripos($pCat, strtolower($cat)) !== false) {
                    // Match by category if not already matched
                    if (!isset($matchedProductIds[(int)$p['product_id']])) {
                        $matchFound = true;
                    }
                }

                if ($matchFound && !isset($matchedProductIds[(int)$p['product_id']])) {
                    $matched = $p;
                    $matchedProductIds[(int)$p['product_id']] = true;
                    break;
                }
            }

            if ($matched) {
                $stock = (int)$matched['current_stock'];
                $reorder = (int)$matched['reorder_point'];
                $baseSales = (float)$matched['baseline_monthly_sales'];

                // If base sales is zero, estimate from reorder point or category baseline
                if ($baseSales <= 0) {
                    $baseSales = max(10, $reorder > 0 ? (float)$reorder : 15.0);
                }

                $pid = (int)$matched['product_id'];
                if (isset($mlPredictions[$pid]) && $mlPredictions[$pid] > 0) {
                    $projectedDemand = round($mlPredictions[$pid]);
                } else {
                    $projectedDemand = (int)round($baseSales * $multiplier);
                }

                $deficit = max(0, $projectedDemand - $stock);
                $coveragePct = ($projectedDemand > 0) ? round(($stock / $projectedDemand) * 100, 1) : 100;
                $dailyRate = $projectedDemand / 30.0;
                $daysOfSupply = $dailyRate > 0 ? round($stock / $dailyRate, 1) : 999;

                // Health Classification
                if ($stock <= 0) {
                    $status = 'Critical Stockout';
                    $statusType = 'critical';
                    $recOrder = max(20, (int)round($projectedDemand * 1.2));
                    $action = "Immediate Restock: Order at least <strong>" . number_format($recOrder) . " units</strong> to capture " . $profile['month_name'] . " Philippine seasonal demand peak.";
                } elseif ($stock < $projectedDemand * 0.4 || ($reorder > 0 && $stock <= $reorder)) {
                    $status = 'High Risk of Stockout';
                    $statusType = 'high_risk';
                    $recOrder = max($deficit, (int)round($projectedDemand * 0.6));
                    $action = "Stock is critically low (only {$daysOfSupply} days of supply). Place order for <strong>" . number_format($recOrder) . " units</strong> before demand peaks.";
                } elseif ($stock < $projectedDemand) {
                    $status = 'Moderate Buffer';
                    $statusType = 'moderate';
                    $recOrder = max(5, $deficit);
                    $action = "Buffer stock will last approx. {$daysOfSupply} days. Prepare replenishment of <strong>" . number_format($recOrder) . " units</strong> with supplier.";
                } else {
                    $status = 'Well Stocked';
                    $statusType = 'optimal';
                    $action = "Comfortable stock coverage ({$daysOfSupply} days of supply). Maintain regular replenishment.";
                }

                $results[] = [
                    'product_id'        => $matched['product_id'],
                    'product_name'      => $matched['product_name'],
                    'category_name'     => $matched['category_name'],
                    'current_stock'     => $stock,
                    'reorder_point'     => $reorder,
                    'baseline_sales'    => $baseSales,
                    'projected_demand'  => $projectedDemand,
                    'demand_lift'       => $item['demand_lift'],
                    'multiplier'        => $multiplier,
                    'reason'            => $item['reason'],
                    'status'            => $status,
                    'status_type'       => $statusType,
                    'coverage_pct'      => $coveragePct,
                    'days_of_supply'    => $daysOfSupply,
                    'recommended_order' => $deficit > 0 ? max($deficit, 10) : 0,
                    'action_text'       => $action,
                    'matched_in_stock'  => true
                ];
            } else {
                // Item not found in current inventory catalog — market opportunity suggestion
                $estDemand = (int)round(30 * $multiplier);
                $results[] = [
                    'product_id'        => null,
                    'product_name'      => $item['name'],
                    'category_name'     => $item['category'],
                    'current_stock'     => 0,
                    'reorder_point'     => 15,
                    'baseline_sales'    => 0,
                    'projected_demand'  => $estDemand,
                    'demand_lift'       => $item['demand_lift'],
                    'multiplier'        => $multiplier,
                    'reason'            => $item['reason'],
                    'status'            => 'Not in Catalog / 0 Stock',
                    'status_type'       => 'market_opportunity',
                    'coverage_pct'      => 0,
                    'days_of_supply'    => 0,
                    'recommended_order' => $estDemand,
                    'action_text'       => "Market Opportunity: In high demand in the Philippines during {$profile['month_name']}. Consider onboarding this product line to capture surge.",
                    'matched_in_stock'  => false
                ];
            }
        }

        $monthResult = [
            'month_profile' => $profile,
            'items'         => $results
        ];
        self::$monthCache[$month] = $monthResult;
        return $monthResult;
    }

    /**
     * Get 4-quarter retail profiles for the Philippines.
     */
    public static function getAllQuarterProfiles(): array
    {
        return [
            1 => [
                'quarter_num'  => 1,
                'quarter_name' => 'Quarter 1 (Jan - Mar)',
                'short_name'   => 'Q1',
                'tagline'      => 'Post-Holiday Reset, Chinese New Year & Summer Kickoff',
                'season_badge' => 'Q1: Post-Holiday & Summer Kickoff',
                'season_icon'  => 'Q1',
                'weather_ph'   => 'Amihan (Cool Northeast Monsoon) Transitioning to Dry Summer',
                'months'       => [1, 2, 3],
                'cultural_drivers' => [
                    'Post-holiday budget consolidation & staple pantry restocking',
                    'Chinese New Year celebrations & Valentine\'s Day gifting',
                    'Early planning for Holy Week (Semana Santa) & summer travel',
                    'Gradual rise in temperatures driving early cold beverage consumption'
                ],
                'in_demand_categories' => [
                    'Beverages'      => 1.35,
                    'Dry Goods'      => 1.30,
                    'Canned Goods'   => 1.30,
                    'Chocolates & Gifts' => 1.40,
                    'Personal Care'  => 1.15
                ]
            ],
            2 => [
                'quarter_num'  => 2,
                'quarter_name' => 'Quarter 2 (Apr - Jun)',
                'short_name'   => 'Q2',
                'tagline'      => 'Peak Summer, Town Fiestas & Balik-Eskwela School Prep',
                'season_badge' => 'Q2: Peak Summer & School Reopening',
                'season_icon'  => 'Q2',
                'weather_ph'   => 'Tag-araw (Peak Hot & Dry) Transitioning to Early Monsoon',
                'months'       => [4, 5, 6],
                'cultural_drivers' => [
                    'Nationwide summer vacation, beach travel, and resort getaways',
                    'Flores de Mayo, Santa Cruzan & barangay patron saint fiestas',
                    'Intense hydration & cooling demand (halo-halo, cold juices, ice)',
                    'Balik-Eskwela shopping rush for school supplies, uniforms, bags & tech'
                ],
                'in_demand_categories' => [
                    'Beverages & Refreshments' => 1.65,
                    'School Supplies'         => 1.70,
                    'Sun Protection & Skincare' => 1.50,
                    'Party Supplies & Groceries' => 1.35
                ]
            ],
            3 => [
                'quarter_num'  => 3,
                'quarter_name' => 'Quarter 3 (Jul - Sep)',
                'short_name'   => 'Q3',
                'tagline'      => 'Habagat Monsoon, Rainy Season & Early Ber-Months Retail',
                'season_badge' => 'Q3: Monsoon Season & Early Ber-Months',
                'season_icon'  => 'Q3',
                'weather_ph'   => 'Habagat (Southwest Monsoon) & Typhoon Peak',
                'months'       => [7, 8, 9],
                'cultural_drivers' => [
                    'Frequent tropical typhoons, rainy weather, and urban flooding',
                    'Emergency preparedness & pantry stockpiling (instant noodles, canned items)',
                    'Ghost Month cautious commercial spending followed by 9.9 Mega Online Sales',
                    'Official kickoff of the iconic Philippine 4-month Christmas countdown (\'Ber Months\')'
                ],
                'in_demand_categories' => [
                    'Canned & Ready Foods'   => 1.45,
                    'Rainwear & Emergency'   => 1.60,
                    'Hot Beverages & Soups'  => 1.35,
                    'Early Holiday Gifting'  => 1.25
                ]
            ],
            4 => [
                'quarter_num'  => 4,
                'quarter_name' => 'Quarter 4 (Oct - Dec)',
                'short_name'   => 'Q4',
                'tagline'      => 'Undas, 13th-Month Bonuses & Grand Christmas Peak',
                'season_badge' => 'Q4: Grand Holiday & Year-End Peak',
                'season_icon'  => 'Q4',
                'weather_ph'   => 'Amihan (Cool Northeast Monsoon) & Festive Peak',
                'months'       => [10, 11, 12],
                'cultural_drivers' => [
                    'Undas (All Saints\' / All Souls\' Day) cemetery visits and reunions',
                    '10.10, 11.11, and 12.12 Mega e-commerce shopping festivals',
                    '13th-month pay releases, employee bonuses, and Christmas parties',
                    'Grand Noche Buena, Simbang Gabi, corporate gifting, and New Year feasts'
                ],
                'in_demand_categories' => [
                    'Holiday Foods & Ham'    => 1.95,
                    'Confectionery & Sweets' => 1.60,
                    'Beverages & Liquors'    => 1.75,
                    'Toys & Gifting Goods'   => 1.70,
                    'Party & Disposable Ware' => 1.50
                ]
            ]
        ];
    }

    /**
     * Get quarter profile.
     */
    public static function getQuarterProfile(int $quarter): array
    {
        $profiles = self::getAllQuarterProfiles();
        $q = max(1, min(4, $quarter));
        return $profiles[$q] ?? $profiles[1];
    }

    /**
     * Get 2-semester retail profiles for the Philippines.
     */
    public static function getAllSemesterProfiles(): array
    {
        return [
            1 => [
                'semester_num'  => 1,
                'semester_name' => '1st Semester (Jan - Jun)',
                'short_name'    => 'H1',
                'tagline'       => 'Summer Dry Season, Town Fiestas & Back-to-School Readiness',
                'season_badge'  => 'H1: Summer & School Readiness',
                'season_icon'   => 'H1',
                'weather_ph'    => 'Amihan Cool to Peak Tag-araw (Dry Period)',
                'months'        => [1, 2, 3, 4, 5, 6],
                'cultural_drivers' => [
                    'Post-holiday budget resets transitioning to high summer recreation spending',
                    'Peak summer vacation trips, resort outings, and local fiestas',
                    'Flores de Mayo celebrations and nationwide school opening preparations'
                ],
                'in_demand_categories' => [
                    'Beverages & Refreshments' => 1.50,
                    'School & Office Supplies' => 1.45,
                    'Dry Goods & Staples'     => 1.25,
                    'Personal Care & Sun'      => 1.35
                ]
            ],
            2 => [
                'semester_num'  => 2,
                'semester_name' => '2nd Semester (Jul - Dec)',
                'short_name'    => 'H2',
                'tagline'       => 'Habagat Monsoon Rains, Mega Sales & Grand Christmas Surge',
                'season_badge'  => 'H2: Monsoon & Christmas Surge',
                'season_icon'   => 'H2',
                'weather_ph'    => 'Habagat (Monsoon) & Amihan (Festive Cool)',
                'months'        => [7, 8, 9, 10, 11, 12],
                'cultural_drivers' => [
                    'Rainy season typhoon preparedness and home comfort essentials',
                    'Kickoff of 4-month \'Ber Months\' festive countdown and layaway purchases',
                    'Mega online sale events (9.9 to 12.12) & 13th-month bonus payout frenzy',
                    'Noche Buena banquets, family reunions, and year-end inventory clearances'
                ],
                'in_demand_categories' => [
                    'Holiday Foods & Deli'   => 1.70,
                    'Canned & Ready Meals'   => 1.40,
                    'Beverages & Party'      => 1.55,
                    'Emergency & Rainwear'   => 1.35,
                    'Gifting & Toys'         => 1.45
                ]
            ]
        ];
    }

    /**
     * Get semester profile.
     */
    public static function getSemesterProfile(int $semester): array
    {
        $profiles = self::getAllSemesterProfiles();
        $s = max(1, min(2, $semester));
        return $profiles[$s] ?? $profiles[1];
    }

    /**
     * Match inventory for a Quarter.
     */
    public static function matchInventoryForQuarter($conn, int $quarter): array
    {
        $profile = self::getQuarterProfile($quarter);
        $months = $profile['months'];
        $seen = [];
        $items = [];

        foreach ($months as $m) {
            $monthData = self::matchInventoryForMonth($conn, $m);
            foreach ($monthData['items'] as $item) {
                $key = ($item['product_id'] ? 'id_' . $item['product_id'] : 'name_' . strtolower(trim($item['product_name'])));
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $items[] = $item;
                }
            }
        }

        return [
            'quarter_profile' => $profile,
            'items'           => $items
        ];
    }

    /**
     * Match inventory for a Semester.
     */
    public static function matchInventoryForSemester($conn, int $semester): array
    {
        $profile = self::getSemesterProfile($semester);
        $months = $profile['months'];
        $seen = [];
        $items = [];

        foreach ($months as $m) {
            $monthData = self::matchInventoryForMonth($conn, $m);
            foreach ($monthData['items'] as $item) {
                $key = ($item['product_id'] ? 'id_' . $item['product_id'] : 'name_' . strtolower(trim($item['product_name'])));
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $items[] = $item;
                }
            }
        }

        return [
            'semester_profile' => $profile,
            'items'            => $items
        ];
    }
}

