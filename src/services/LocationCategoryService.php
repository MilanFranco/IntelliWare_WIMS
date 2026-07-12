<?php
/**
 * Keeps product location and category in sync using warehouse zone conventions.
 * Category is the canonical driver; location prefixes map back to category.
 */
class LocationCategoryService
{
    /** @var array<string, string> category name => default location prefix */
    private static array $defaultCategoryLocations = [
        'Dairy Products' => 'Shelf A',
        'Beverages' => 'Shelf B',
        'Canned Goods' => 'Shelf C',
        'Dry Goods' => 'Shelf D',
        'Frozen Foods' => 'Freezer F',
        'Bakery Products' => 'Shelf E',
        'Personal Care' => 'Shelf P',
        'Household Items' => 'Shelf H',
        'Pet Supplies' => 'Shelf S',
        'Stationery and Office Supplies' => 'Shelf O',
    ];

    /** @var array<string, string> location prefix => category name */
    private static array $defaultLocationCategories = [
        'Shelf A' => 'Dairy Products',
        'Shelf B' => 'Beverages',
        'Shelf C' => 'Canned Goods',
        'Shelf D' => 'Dry Goods',
        'Freezer F' => 'Frozen Foods',
        'Shelf E' => 'Bakery Products',
        'Shelf P' => 'Personal Care',
        'Shelf H' => 'Household Items',
        'Shelf S' => 'Pet Supplies',
        'Shelf O' => 'Stationery and Office Supplies',
    ];

    public static function buildMap(mysqli $conn): array
    {
        $categoryToLocation = self::$defaultCategoryLocations;
        $locationToCategory = self::$defaultLocationCategories;

        $result = $conn->query(
            "SELECT category, location, COUNT(*) AS usage_count
             FROM products
             WHERE category IS NOT NULL AND category != ''
               AND location IS NOT NULL AND location != ''
             GROUP BY category, location
             ORDER BY category ASC, usage_count DESC"
        );

        if ($result) {
            $seenCategories = [];
            while ($row = $result->fetch_assoc()) {
                $category = trim((string)$row['category']);
                $location = trim((string)$row['location']);
                if ($category === '' || $location === '') {
                    continue;
                }

                if (!isset($seenCategories[$category])) {
                    $categoryToLocation[$category] = $location;
                    $seenCategories[$category] = true;
                }

                $prefix = self::extractLocationPrefix($location);
                if ($prefix !== '' && !isset($locationToCategory[$prefix])) {
                    $locationToCategory[$prefix] = $category;
                }
            }
        }

        $categories = [];
        $catResult = $conn->query('SELECT name FROM categories ORDER BY name ASC');
        if ($catResult) {
            while ($row = $catResult->fetch_assoc()) {
                $name = trim((string)$row['name']);
                if ($name !== '') {
                    $categories[] = $name;
                    if (!isset($categoryToLocation[$name]) && isset(self::$defaultCategoryLocations[$name])) {
                        $categoryToLocation[$name] = self::$defaultCategoryLocations[$name];
                    }
                }
            }
        }

        foreach ($categoryToLocation as $category => $location) {
            $prefix = self::extractLocationPrefix($location);
            if ($prefix !== '' && !isset($locationToCategory[$prefix])) {
                $locationToCategory[$prefix] = $category;
            }
        }

        return [
            'category_to_location' => $categoryToLocation,
            'location_to_category' => $locationToCategory,
            'categories' => array_values(array_unique(array_merge($categories, array_keys($categoryToLocation)))),
            'driver' => 'category',
        ];
    }

    public static function extractLocationPrefix(string $location): string
    {
        $location = trim($location);
        if ($location === '') {
            return '';
        }

        if (preg_match('/^(Shelf [A-Z]|Freezer [A-Z])/i', $location, $matches)) {
            return ucwords(strtolower($matches[1]));
        }

        if (preg_match('/^([A-Za-z]+(?:\s+[A-Za-z]+)?)\s+\d*/', $location, $matches)) {
            return trim($matches[1]);
        }

        return $location;
    }

    public static function resolveLocationForCategory(string $category, mysqli $conn): string
    {
        $map = self::buildMap($conn);
        $category = trim($category);
        if ($category === '') {
            return '';
        }

        return $map['category_to_location'][$category] ?? '';
    }

    public static function resolveCategoryForLocation(string $location, mysqli $conn): string
    {
        $map = self::buildMap($conn);
        $prefix = self::extractLocationPrefix($location);
        if ($prefix === '') {
            return '';
        }

        return $map['location_to_category'][$prefix] ?? '';
    }

    /**
     * Enforce a consistent category/location pair.
     *
     * @return array{category:string, location:string}
     */
    public static function synchronize(string $category, string $location, string $changedField, mysqli $conn): array
    {
        $category = trim($category);
        $location = trim($location);
        $map = self::buildMap($conn);

        if ($changedField === 'location' && $location !== '') {
            $prefix = self::extractLocationPrefix($location);
            $resolvedCategory = $map['location_to_category'][$prefix] ?? '';
            if ($resolvedCategory !== '') {
                return ['category' => $resolvedCategory, 'location' => $location];
            }
        }

        if ($category !== '') {
            $resolvedLocation = $map['category_to_location'][$category] ?? '';
            if ($resolvedLocation !== '') {
                if ($location === '' || self::locationsConflict($location, $resolvedLocation)) {
                    $location = $resolvedLocation;
                }
            }
        }

        if ($location !== '' && $category !== '') {
            $prefix = self::extractLocationPrefix($location);
            $expectedCategory = $map['location_to_category'][$prefix] ?? '';
            if ($expectedCategory !== '' && strcasecmp($category, $expectedCategory) !== 0) {
                throw new InvalidArgumentException(
                    'Location "' . $location . '" belongs to category "' . $expectedCategory . '", not "' . $category . '".'
                );
            }
        }

        return ['category' => $category, 'location' => $location];
    }

    private static function locationsConflict(string $location, string $expectedPrefix): bool
    {
        $prefix = self::extractLocationPrefix($location);
        return $prefix !== '' && strcasecmp($prefix, self::extractLocationPrefix($expectedPrefix)) !== 0;
    }
}
