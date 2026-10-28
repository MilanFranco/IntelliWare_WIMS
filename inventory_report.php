<?php
/**
 * inventory_report.php
 * Official Inventory Report Document for IntelliWare IMS.
 *
 * Access control: Admin-only, active session required.
 * Produces the official document layout matching the existing Reports page document layout.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── 1. Auth Guard (Admin-Only, same session & permission check pattern as products.php) ──
if (!isset($_SESSION['Username'])) {
    header('Location: signin.php');
    exit();
}

$role = $_SESSION['Role'] ?? 'staff';
if (!in_array($role, ['admin'])) {
    header('Location: dash_staff.php');
    exit();
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/report_document.php';

// ── 2. Parameter Extraction & Sanitisation ───────────────────────────
$q             = trim((string)($_GET['q']        ?? ($_GET['search']   ?? '')));
$categoryParam = trim((string)($_GET['category'] ?? ''));
$statusFilter  = strtolower(trim((string)($_GET['filter'] ?? ($_GET['status'] ?? ''))));
$isCsvExport   = (isset($_GET['export']) && $_GET['export'] === 'csv');

// ── 3. Query Construction with Prepared Statements ────────────────────
$whereConditions = ['p.archived = 0'];
$params = [];
$types  = '';

if ($q !== '') {
    $whereConditions[] = '(p.product_name LIKE ? OR p.supplier LIKE ? OR CONCAT(\'P-\', LPAD(p.product_id, 4, \'0\')) LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types   .= 'sss';
}

if ($categoryParam !== '') {
    $whereConditions[] = 'p.category = ?';
    $params[] = $categoryParam;
    $types   .= 's';
}

if ($statusFilter !== '') {
    if (in_array($statusFilter, ['low', 'low_stock'])) {
        $whereConditions[] = 'p.quantity > 0 AND p.quantity <= p.reorder_point';
    } elseif (in_array($statusFilter, ['out', 'out_of_stock'])) {
        $whereConditions[] = 'p.quantity <= 0';
    } elseif (in_array($statusFilter, ['good', 'in_stock'])) {
        $whereConditions[] = 'p.quantity > p.reorder_point';
    } elseif ($statusFilter === 'attention') {
        $whereConditions[] = 'p.quantity <= p.reorder_point';
    }
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Fetch all matching products across all pages, sorted by SKU (product_id ASC)
$sql = "
    SELECT
        CONCAT('P-', LPAD(p.product_id, 4, '0'))                            AS sku,
        p.product_name                                                       AS name,
        COALESCE(p.category, '')                                             AS category,
        COALESCE(NULLIF(TRIM(p.supplier), ''), 'No supplier')               AS supplier,
        COALESCE(NULLIF(TRIM(p.location), ''), '—')                          AS location,
        COALESCE(p.quantity, 0)                                              AS qty,
        COALESCE(NULLIF(TRIM(p.unit), ''), 'piece')                          AS uom,
        COALESCE(p.reorder_point, 10)                                        AS reorder_point,
        COALESCE(p.price, 0.00)                                              AS price,
        (SELECT MIN(b.expiry_date)
         FROM product_batches b
         WHERE b.product_id = p.product_id AND b.qty > 0)                   AS next_expiry,
        (SELECT COUNT(*)
         FROM product_batches b
         WHERE b.product_id = p.product_id AND b.qty > 0)                   AS batch_count
    FROM products p
    {$whereClause}
    ORDER BY p.product_id ASC
";

$items = [];
if ($types !== '') {
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
        }
        $stmt->close();
    }
} else {
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
    }
}

// ── 4. Calculations (Same as Inventory & Dashboard pages) ─────────────
$totalProducts   = count($items);
$totalUnits      = 0;
$totalStockValue = 0.0;
$lowStockCount   = 0;
$outOfStockCount = 0;
$goodStockCount  = 0;

foreach ($items as &$it) {
    $qVal       = (int)($it['qty'] ?? 0);
    $rpVal      = (int)($it['reorder_point'] ?? 10);
    $priceVal   = (float)($it['price'] ?? 0.0);

    $totalUnits      += $qVal;
    $totalStockValue += ($qVal * $priceVal);

    if ($qVal <= 0) {
        $it['status']       = 'out';
        $it['status_label'] = 'OUT OF STOCK';
        $outOfStockCount++;
    } elseif ($qVal <= $rpVal) {
        $it['status']       = 'low';
        $it['status_label'] = 'LOW STOCK';
        $lowStockCount++;
    } else {
        $it['status']       = 'good';
        $it['status_label'] = 'GOOD';
        $goodStockCount++;
    }
}
unset($it);

// ── 5. CSV Export Endpoint (When ?export=csv is requested) ─────────────
if ($isCsvExport) {
    function csvEscape(?string $val): string {
        if ($val === null) return '';
        if (in_array(mb_substr($val, 0, 1), ['=', '+', '-', '@'])) {
            return "'" . $val;
        }
        return $val;
    }

    $filename = 'IntelliWare-IMS-Inventory-Report-' . date('Y-m-d-His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM

    // Exact requested columns in exact order
    fputcsv($out, [
        'SKU', 'Name', 'Category', 'Supplier', 'Location',
        'Total Stock', 'Batches / Expiry', 'Price', 'Unit', 'Reorder', 'Status'
    ]);

    foreach ($items as $r) {
        $rQty   = (int)$r['qty'];
        $rRp    = (int)$r['reorder_point'];
        $bCount = (int)$r['batch_count'];
        $exp    = $r['next_expiry'];

        if ($bCount > 0) {
            $batchExpStr = $bCount . ' batch' . ($bCount > 1 ? 'es' : '') . ' (Nearest Exp: ' . ($exp && $exp !== '0000-00-00' ? $exp : 'No Expiry') . ')';
        } else {
            $batchExpStr = 'No batches / No Expiry';
        }

        fputcsv($out, [
            csvEscape($r['sku']),
            csvEscape($r['name']),
            csvEscape($r['category']),
            csvEscape($r['supplier']),
            csvEscape($r['location']),
            $rQty,
            csvEscape($batchExpStr),
            number_format((float)$r['price'], 2, '.', ''),
            csvEscape($r['uom']),
            $rRp,
            $r['status_label']
        ]);
    }

    fclose($out);
    exit();
}

// ── 6. HTML Presentation Setup ────────────────────────────────────────
$statusLabels = [
    'low'          => 'Low Stock',
    'low_stock'    => 'Low Stock',
    'out'          => 'Out of Stock',
    'out_of_stock' => 'Out of Stock',
    'good'         => 'Good Stock',
    'in_stock'     => 'In Stock',
    'attention'    => 'Attention Needed (Low/Out)'
];
$statusFilterLabel = $statusFilter !== '' ? ($statusLabels[$statusFilter] ?? ucfirst($statusFilter)) : 'All Stock';

$categoryDisplay   = $categoryParam !== '' ? $categoryParam : 'All Categories';
$searchDisplay     = $q !== '' ? ' &bull; Filtered by "' . htmlspecialchars($q) . '"' : '';

$generatedBy       = $_SESSION['Username'] ?? 'Admin';
$generatedRole     = ucfirst($role);
$generatedAt       = date('F j, Y · g:i A');
$refCode           = 'IW-INV-' . date('Ymd-His');

$csvUrl = 'inventory_report.php?' . http_build_query(array_filter([
    'export'   => 'csv',
    'q'        => $q,
    'category' => $categoryParam,
    'status'   => $statusFilter
]));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Report — IntelliWare IMS</title>
    <link rel="icon" type="image/svg+xml" href="img/systemlogo.svg">
    <link rel="stylesheet" href="assets/fonts.css">
    <script>
        // Synchronise active theme from local storage or parent window
        (function() {
            try {
                const savedTheme = localStorage.getItem('theme');
                if (savedTheme) {
                    document.documentElement.setAttribute('data-theme', savedTheme);
                } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) {
                    document.documentElement.setAttribute('data-theme', 'light');
                }
            } catch(e) {}
        })();
    </script>
    <?php renderReportDocumentStyles(true); ?>
</head>
<body class="report-view-body">

    <!-- Screen Toolbar (Hidden When Printing) -->
    <header class="report-toolbar no-print">
        <div class="report-toolbar-inner">
            <div class="report-toolbar-left">
                <a href="products.php" class="report-toolbar-btn btn-toolbar-back" title="Return to Inventory List">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <span>Back to Inventory</span>
                </a>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 13px; font-weight: 700; color: #51D55A;">Inventory Report</span>
                    <span style="font-size: 12px; color: #9aa6d1;">
                        &bull; <?php echo number_format($totalProducts); ?> items (<?php echo htmlspecialchars($statusFilterLabel); ?>)<?php echo $searchDisplay; ?>
                    </span>
                </div>
            </div>

            <div class="report-toolbar-right">
                <button type="button" onclick="window.print()" class="report-toolbar-btn btn-toolbar-primary" title="Print document or save as clean PDF">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2m-12 0v4h12v-4M8 14h.01M12 14h.01M16 14h.01"/>
                    </svg>
                    <span>Print / Save as PDF</span>
                </button>

                <a href="<?php echo htmlspecialchars($csvUrl); ?>" class="report-toolbar-btn btn-toolbar-secondary" title="Export this report to CSV">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span>Download CSV</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Document Canvas -->
    <main class="report-canvas-wrapper">
        <?php
        renderReportDocument([
            'title'          => 'Inventory Report',
            'subtitle'       => 'Inventory Management System',
            'tag'            => 'IMS',
            'badge'          => 'Active Inventory',
            'badge_bg'       => '#f0fdf4',
            'badge_border'   => '#bbf7d0',
            'badge_color'    => '#16a34a',
            'ref'            => $refCode,
            'classification' => 'Classification: Internal Warehouse Audit',
            'generated_by'   => $generatedBy,
            'generated_role' => $generatedRole,
            'generated_at'   => $generatedAt,
            'prep_head'      => 'Report Generated By',
            'scope_line1'    => '<strong>Category:</strong> ' . htmlspecialchars($categoryDisplay),
            'scope_line2'    => '<strong>Stock:</strong> ' . htmlspecialchars($statusFilterLabel),
            'recon_head'     => 'Scope &amp; Parameters',
            'recon_line1'    => '<strong>Archived:</strong> Excluded' . ($q !== '' ? ' &nbsp;|&nbsp; <strong>Search:</strong> ' . htmlspecialchars($q) : ''),
            'recon_line2'    => '<strong>Reconciliation:</strong> ' . number_format($totalProducts) . ' SKUs &bull; ' . number_format($totalUnits) . ' Units',
            'kpi_tiles'      => [
                [
                    'label'     => 'Total Products',
                    'value'     => number_format($totalProducts),
                    'sub'       => 'Catalog SKUs',
                    'bg'        => '#f1f5f9',
                    'border'    => '#cbd5e1',
                    'val_color' => '#0f172a',
                    'lbl_color' => '#475569',
                    'sub_color' => '#64748b'
                ],
                [
                    'label'     => 'Total Units',
                    'value'     => number_format($totalUnits),
                    'sub'       => 'Warehouse Stock',
                    'bg'        => '#ecfdf5',
                    'border'    => '#a7f3d0',
                    'val_color' => '#059669',
                    'lbl_color' => '#059669',
                    'sub_color' => '#047857'
                ],
                [
                    'label'     => 'Stock Value',
                    'value'     => '₱' . number_format($totalStockValue, 2),
                    'sub'       => 'Total Valuation',
                    'bg'        => '#f0fdf4',
                    'border'    => '#bbf7d0',
                    'val_color' => '#16a34a',
                    'lbl_color' => '#16a34a',
                    'sub_color' => '#15803d'
                ],
                [
                    'label'     => 'Low Stock',
                    'value'     => number_format($lowStockCount),
                    'sub'       => 'Reorder Soon',
                    'bg'        => '#fef3c7',
                    'border'    => '#fde68a',
                    'val_color' => '#d97706',
                    'lbl_color' => '#b45309',
                    'sub_color' => '#b45309'
                ],
                [
                    'label'     => 'Out of Stock',
                    'value'     => number_format($outOfStockCount),
                    'sub'       => 'Fully Depleted',
                    'bg'        => '#fef2f2',
                    'border'    => '#fecaca',
                    'val_color' => '#dc2626',
                    'lbl_color' => '#dc2626',
                    'sub_color' => '#b91c1c'
                ],
            ],
            'items'           => $items,
            'table_type'      => 'inventory', // 11-column exact layout
            'is_print_only'   => false,      // Visible on screen inside report-document-sheet
            'footer_text'     => 'IntelliWare IMS · Inventory Management System · Confidential',
            'show_signatures' => true
        ]);
        ?>
    </main>

</body>
</html>
