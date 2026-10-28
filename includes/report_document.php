<?php
/**
 * includes/report_document.php
 * Shared Universal Report Document Layout & Print Styles for IntelliWare IMS.
 *
 * Used by:
 *   - reports.php (General Inventory Audit & Reorder Report print view)
 *   - inventory_report.php (Inventory Report document view & print view)
 */

if (!function_exists('renderReportDocumentStyles')) {
    /**
     * Renders print and document screen styling.
     *
     * @param bool $isDedicatedView When true, applies standalone screen styling for inventory_report.php.
     */
    function renderReportDocumentStyles(bool $isDedicatedView = false): void {
?>
<style id="sharedReportDocumentStyles">
    /* ── Universal Document Stylesheet (Shared Partial) ─── */
    .print-only {
        display: none !important;
    }

    @page {
        size: A4 landscape;
        margin: 10mm;
    }

    @media print {
        @page {
            size: A4 landscape;
            margin: 10mm;
        }

        /* Hide interface chrome, navigation, headers, toolbars */
        header, nav, aside, .sidebar, #sidebar, .iw-sidebar,
        .premium-sidebar, #toast-container, form,
        .no-print, button, .action-buttons,
        .browser-btn, #itemList, #statusList, #reportCatChart,
        #itemPanelMeta, #statusPanelMeta, .premium-card:has(form),
        .premium-card:has(#reportCatChart),
        .page-hero-card, #defaultSummaryCards, #reorderSummaryCards,
        #stockExplorerTitle, #stockExplorerSubtitle, .report-view-toggle-wrap,
        #reportExplorerView, #reportTableView, #reportReorderView,
        .stock-explorer-header, .pagination-container, .mobile-scroll-hint,
        .premium-card, .report-toolbar {
            display: none !important;
        }

        body, .main-content, .layout-container, main {
            background: #ffffff !important;
            color: #0f172a !important;
            padding: 0 !important;
            margin: 0 !important;
            font-family: 'Onest', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        /* Show the universal printable report container */
        #printableReportContainer {
            display: block !important;
            background: #ffffff !important;
            color: #0f172a !important;
            padding: 0 !important;
            margin: 0 !important;
            border: none !important;
            box-shadow: none !important;
            max-width: 100% !important;
            width: 100% !important;
        }

        .printable-report-table {
            width: 100% !important;
            border-collapse: collapse !important;
            margin-top: 15px !important;
            font-size: 10px !important;
        }

        .printable-report-table thead {
            display: table-header-group !important;
        }

        .printable-report-table tr {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }

        .printable-report-table th {
            background-color: #13275B !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            text-align: left !important;
            padding: 6px 8px !important;
            border: 1px solid #0f172a !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .printable-report-table td {
            padding: 5px 8px !important;
            border: 1px solid #cbd5e1 !important;
            color: #0f172a !important;
        }

        .printable-report-table tr:nth-child(even) td {
            background-color: #f8fafc !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .print-badge {
            display: inline-block !important;
            padding: 2px 6px !important;
            border-radius: 4px !important;
            font-size: 8.5px !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .print-badge.out {
            background: #fee2e2 !important;
            color: #b91c1c !important;
            border: 1px solid #f87171 !important;
        }

        .print-badge.low {
            background: #fef3c7 !important;
            color: #b45309 !important;
            border: 1px solid #fbbf24 !important;
        }

        .print-badge.good {
            background: #d1fae5 !important;
            color: #047857 !important;
            border: 1px solid #34d399 !important;
        }

        .print-only {
            display: block !important;
        }

        .screen-only {
            display: none !important;
        }

        /* Page numbering in standard print engines */
        .print-doc-footer {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }
    }

    <?php if ($isDedicatedView): ?>
    /* ── Standalone Dedicated Report Document Screen Styles (inventory_report.php) ── */
    body.report-view-body {
        margin: 0;
        padding: 0;
        font-family: 'Onest', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background-color: #0b112c;
        color: #e2e8f0;
        min-height: 100vh;
    }

    [data-theme="light"] body.report-view-body {
        background-color: #f1f5f9;
        color: #0f172a;
    }

    /* Fixed/Sticky Top Toolbar */
    .report-toolbar {
        position: sticky;
        top: 0;
        z-index: 100;
        background: rgba(13, 22, 56, 0.92);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border-bottom: 1px solid rgba(42, 68, 132, 0.45);
        padding: 12px 24px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
    }

    [data-theme="light"] .report-toolbar {
        background: rgba(255, 255, 255, 0.95);
        border-bottom-color: #e2e8f0;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
    }

    .report-toolbar-inner {
        max-width: 1400px;
        margin: 0 auto;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .report-toolbar-left, .report-toolbar-right {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .report-toolbar-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.2s ease;
        border: 1px solid transparent;
        font-family: 'Onest', sans-serif;
    }

    .btn-toolbar-back {
        background: rgba(255, 255, 255, 0.05);
        color: #9aa6d1;
        border-color: rgba(42, 68, 132, 0.4);
    }

    .btn-toolbar-back:hover {
        background: rgba(255, 255, 255, 0.1);
        color: #ffffff;
        border-color: #51D55A;
    }

    [data-theme="light"] .btn-toolbar-back {
        background: #f8fafc;
        color: #475569;
        border-color: #cbd5e1;
    }

    [data-theme="light"] .btn-toolbar-back:hover {
        background: #f1f5f9;
        color: #0f172a;
        border-color: #94a3b8;
    }

    .btn-toolbar-primary {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
    }

    .btn-toolbar-primary:hover {
        background: linear-gradient(135deg, #059669 0%, #047857 100%);
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(16, 185, 129, 0.35);
    }

    .btn-toolbar-secondary {
        background: rgba(42, 68, 132, 0.25);
        color: #c8d4f5;
        border-color: rgba(42, 68, 132, 0.5);
    }

    .btn-toolbar-secondary:hover {
        background: rgba(42, 68, 132, 0.45);
        color: #ffffff;
        border-color: #60a5fa;
    }

    [data-theme="light"] .btn-toolbar-secondary {
        background: #f1f5f9;
        color: #1e293b;
        border-color: #cbd5e1;
    }

    [data-theme="light"] .btn-toolbar-secondary:hover {
        background: #e2e8f0;
        color: #0f172a;
    }

    /* Screen Document Sheet Container */
    .report-canvas-wrapper {
        max-width: 1360px;
        margin: 28px auto;
        padding: 0 20px 40px;
    }

    .report-document-sheet {
        background: #ffffff;
        color: #0f172a;
        border-radius: 16px;
        box-shadow: 0 16px 48px rgba(0, 0, 0, 0.35), 0 0 0 1px rgba(255, 255, 255, 0.08);
        padding: 36px 42px;
        overflow-x: auto;
    }

    [data-theme="light"] .report-document-sheet {
        background: #ffffff;
        box-shadow: 0 12px 36px rgba(0, 0, 0, 0.08), 0 0 0 1px #e2e8f0;
    }

    /* Long product names word-break */
    .printable-report-table td:nth-child(2) {
        max-width: 280px;
        word-break: break-word;
        overflow-wrap: break-word;
    }

    .stock-unit {
        color: #64748b;
    }
    <?php endif; ?>
</style>
<?php
    }
}

if (!function_exists('renderUniversalReportTableHeader')) {
    /**
     * Renders standard 11-column table header in exact requested order:
     * SKU, Name, Category, Supplier, Location, Total Stock, Batches / Expiry, Price, Unit, Reorder, Status.
     */
    function renderUniversalReportTableHeader(): void {
?>
    <thead>
        <tr>
            <th style="width: 70px;">SKU</th>
            <th class="rr-col-name" style="min-width: 200px;">Name</th>
            <th>Category</th>
            <th>Supplier</th>
            <th>Location</th>
            <th>Total Stock</th>
            <th style="min-width: 140px;">Batches / Expiry</th>
            <th>Price</th>
            <th>Unit</th>
            <th style="text-align: right;">Reorder</th>
            <th style="text-align: center;">Status</th>
        </tr>
    </thead>
<?php
    }
}

if (!function_exists('renderReportDocument')) {
    /**
     * Renders the universal report document container (#printableReportContainer).
     *
     * @param array $config Configuration array for report header, filters, KPI tiles, and items.
     */
    function renderReportDocument(array $config): void {
        $title          = $config['title'] ?? 'OFFICIAL INVENTORY & AUDIT REPORT';
        $subtitle       = $config['subtitle'] ?? 'Inventory Management System';
        $tag            = $config['tag'] ?? 'IMS';
        $badge          = $config['badge'] ?? 'Active Audit';
        $badgeBg        = $config['badge_bg'] ?? '#f0fdf4';
        $badgeBorder    = $config['badge_border'] ?? '#bbf7d0';
        $badgeColor     = $config['badge_color'] ?? '#16a34a';
        $ref            = $config['ref'] ?? ('IW-RPT-' . date('Ymd-His'));
        $classification = $config['classification'] ?? 'Classification: Internal Warehouse Audit';
        $generatedBy    = $config['generated_by'] ?? 'Staff';
        $generatedRole  = $config['generated_role'] ?? 'Staff';
        $generatedAt    = $config['generated_at'] ?? date('F j, Y · g:i A');

        $prepHead       = $config['prep_head'] ?? 'Audit Generated By';
        $scopeLine1     = $config['scope_line1'] ?? '<strong>Category:</strong> All Categories';
        $scopeLine2     = $config['scope_line2'] ?? '<strong>Status:</strong> All Statuses';
        $reconHead      = $config['recon_head'] ?? 'Audit Reconciliation';
        $reconLine1     = $config['recon_line1'] ?? '';
        $reconLine2     = $config['recon_line2'] ?? '';

        $kpiTiles       = $config['kpi_tiles'] ?? [];
        $items          = $config['items'] ?? [];
        $tableType      = $config['table_type'] ?? 'inventory'; // 'inventory' (11 cols) or 'reports_audit' (7 cols)
        $isPrintOnly    = !empty($config['is_print_only']);
        $containerClass = $isPrintOnly ? 'print-only' : 'report-document-sheet';
        $footerText     = $config['footer_text'] ?? 'IntelliWare IMS · Inventory Management System · Confidential';
        $showSignatures = $config['show_signatures'] ?? true;
?>
    <!-- Universal Document Container (#printableReportContainer) -->
    <div id="printableReportContainer" class="<?php echo htmlspecialchars($containerClass); ?>" style="<?php echo $isPrintOnly ? 'padding: 20px 0;' : ''; ?>">
        <!-- Document Header Strip -->
        <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #13275B; padding-bottom: 12px; margin-bottom: 12px;">
            <div style="display: flex; align-items: center; gap: 14px;">
                <img src="img/systemlogo.svg" alt="Logo" style="width: 52px; height: 52px;" />
                <div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 19px; font-weight: 800; color: #13275B; letter-spacing: 0.5px;">INTELLIWARE</span>
                        <span style="font-size: 10px; font-weight: 800; background: #13275B; color: #ffffff; padding: 2px 7px; border-radius: 4px; letter-spacing: 0.5px; display: inline-block; vertical-align: middle;"><?php echo htmlspecialchars($tag); ?></span>
                    </div>
                    <div style="font-size: 10px; color: #64748b;"><?php echo htmlspecialchars($subtitle); ?></div>
                    <div id="printDocTitle" style="font-size: 13px; font-weight: 700; color: #0f172a; margin-top: 2px; text-transform: uppercase;"><?php echo htmlspecialchars($title); ?></div>
                </div>
            </div>
            <div style="text-align: right; font-size: 10px; color: #64748b;">
                <div id="printDocBadge" style="display: inline-block; background: <?php echo htmlspecialchars($badgeBg); ?>; border: 1px solid <?php echo htmlspecialchars($badgeBorder); ?>; color: <?php echo htmlspecialchars($badgeColor); ?>; font-weight: 700; font-size: 8.5px; padding: 2px 8px; border-radius: 4px; text-transform: uppercase; margin-bottom: 4px;">
                    <?php echo htmlspecialchars($badge); ?>
                </div>
                <div><strong>REF:</strong> <span id="printDocRef"><?php echo htmlspecialchars($ref); ?></span></div>
                <div id="printDocClass"><?php echo htmlspecialchars($classification); ?></div>
            </div>
        </div>

        <!-- Scope & Details Box -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 14px; margin-bottom: 12px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; font-size: 11px;">
            <div>
                <div id="printPrepHead" style="color: #64748b; font-size: 8.5px; font-weight: bold; text-transform: uppercase;"><?php echo htmlspecialchars($prepHead); ?></div>
                <div style="font-weight: bold; color: #0f172a; margin-top: 2px;"><?php echo htmlspecialchars($generatedBy); ?> (<?php echo htmlspecialchars($generatedRole); ?>)</div>
                <div style="color: #64748b; font-size: 10px; margin-top: 2px;"><?php echo htmlspecialchars($generatedAt); ?></div>
            </div>
            <div>
                <div style="color: #64748b; font-size: 8.5px; font-weight: bold; text-transform: uppercase;">Report Scope &amp; Parameters</div>
                <div id="printScopeLine1" style="margin-top: 2px;"><?php echo $scopeLine1; ?></div>
                <div id="printScopeLine2" style="margin-top: 2px;"><?php echo $scopeLine2; ?></div>
            </div>
            <div>
                <div id="printReconHead" style="color: #64748b; font-size: 8.5px; font-weight: bold; text-transform: uppercase;"><?php echo htmlspecialchars($reconHead); ?></div>
                <div id="printReconLine1" style="margin-top: 2px;"><?php echo $reconLine1; ?></div>
                <div id="printReconLine2" style="margin-top: 2px;"><?php echo $reconLine2; ?></div>
            </div>
        </div>

        <!-- KPI Summary Cards on Paper / Sheet -->
        <?php if (!empty($kpiTiles)): ?>
        <div id="printKpiTiles" style="display: grid; grid-template-columns: repeat(<?php echo count($kpiTiles); ?>, 1fr); gap: 8px; margin-bottom: 14px;">
            <?php foreach ($kpiTiles as $tile): ?>
            <div style="background: <?php echo $tile['bg'] ?? '#f1f5f9'; ?>; border: 1px solid <?php echo $tile['border'] ?? '#cbd5e1'; ?>; border-radius: 6px; padding: 8px; text-align: center;">
                <div style="font-size: 8px; font-weight: bold; color: <?php echo $tile['lbl_color'] ?? '#475569'; ?>; text-transform: uppercase;">
                    <?php echo htmlspecialchars($tile['label']); ?>
                </div>
                <div style="font-size: 14px; font-weight: 800; color: <?php echo $tile['val_color'] ?? '#0f172a'; ?>; margin-top: 2px;">
                    <?php echo htmlspecialchars($tile['value']); ?>
                </div>
                <div style="font-size: 7.5px; color: <?php echo $tile['sub_color'] ?? '#64748b'; ?>;">
                    <?php echo htmlspecialchars($tile['sub']); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Printable Document Table -->
        <table class="printable-report-table" id="mainPrintableTable">
            <thead id="printableTableHead">
                <?php if ($tableType === 'reports_audit'): ?>
                <tr>
                    <th style="width: 12%;">SKU</th>
                    <th style="width: 32%;">Product Name</th>
                    <th style="width: 20%;">Category</th>
                    <th style="width: 10%; text-align: right;">Stock Qty</th>
                    <th style="width: 8%; text-align: center;">Unit</th>
                    <th style="width: 8%; text-align: right;">Reorder</th>
                    <th style="width: 10%; text-align: center;">Status</th>
                </tr>
                <?php else: ?>
                <!-- Exact 11-column order: SKU, Name, Category, Supplier, Location, Total Stock, Batches / Expiry, Price, Unit, Reorder, Status -->
                <tr>
                    <th style="width: 8%;">SKU</th>
                    <th style="width: 20%;">Name</th>
                    <th style="width: 11%;">Category</th>
                    <th style="width: 12%;">Supplier</th>
                    <th style="width: 8%;">Location</th>
                    <th style="width: 9%; text-align: right;">Total Stock</th>
                    <th style="width: 12%;">Batches / Expiry</th>
                    <th style="width: 8%; text-align: right;">Price</th>
                    <th style="width: 5%; text-align: center;">Unit</th>
                    <th style="width: 5%; text-align: right;">Reorder</th>
                    <th style="width: 8%; text-align: center;">Status</th>
                </tr>
                <?php endif; ?>
            </thead>
            <tbody id="printableTableBody">
                <?php if (empty($items)): ?>
                <tr>
                    <td colspan="<?php echo $tableType === 'reports_audit' ? 7 : 11; ?>" style="text-align: center; padding: 24px; color: #64748b;">
                        No items found matching the specified criteria.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($items as $pi): ?>
                        <?php if ($tableType === 'reports_audit'): ?>
                            <!-- reports.php 7-column layout -->
                            <tr>
                                <td style="font-family: monospace; font-weight: bold;"><?php echo htmlspecialchars($pi['sku']); ?></td>
                                <td style="font-weight: 600;"><?php echo htmlspecialchars($pi['name']); ?></td>
                                <td><?php echo htmlspecialchars($pi['category'] ?: 'Uncategorized'); ?></td>
                                <td style="text-align: right; font-weight: bold;"><?php echo number_format((int)$pi['qty']); ?></td>
                                <td style="text-align: center; color: #64748b;"><?php echo htmlspecialchars($pi['uom'] ?: 'pcs'); ?></td>
                                <td style="text-align: right; color: #64748b;"><?php echo number_format((int)($pi['reorder_point'] ?: 10)); ?></td>
                                <td style="text-align: center;">
                                    <span class="print-badge <?php echo htmlspecialchars($pi['status']); ?>">
                                        <?php echo htmlspecialchars($pi['status_label']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php else: ?>
                            <!-- Exact 11-column Inventory Report layout -->
                            <?php
                                $qty        = (int)($pi['qty'] ?? 0);
                                $reorder    = (int)($pi['reorder_point'] ?? 10);
                                $price      = (float)($pi['price'] ?? 0.0);
                                $batchCount = (int)($pi['batch_count'] ?? 0);
                                $nearExp    = $pi['next_expiry'] ?? ($pi['nearest_expiry'] ?? null);

                                if ($qty <= 0) {
                                    $statusClass = 'out';
                                    $statusLabel = 'OUT OF STOCK';
                                } elseif ($qty <= $reorder) {
                                    $statusClass = 'low';
                                    $statusLabel = 'LOW STOCK';
                                } else {
                                    $statusClass = 'good';
                                    $statusLabel = 'GOOD';
                                }

                                if ($batchCount > 0) {
                                    $batchText = $batchCount . ' batch' . ($batchCount > 1 ? 'es' : '');
                                    $expText   = ($nearExp && $nearExp !== '0000-00-00') ? 'Exp: ' . date('M j, Y', strtotime($nearExp)) : 'No Expiry';
                                    $batchExpMarkup = '<div>' . htmlspecialchars($batchText) . '</div><div style="font-size: 8px; color: #64748b;">' . htmlspecialchars($expText) . '</div>';
                                } else {
                                    $batchExpMarkup = '<span style="color: #64748b; font-size: 9px;">No batches / No Expiry</span>';
                                }
                            ?>
                            <tr>
                                <td style="font-family: monospace; font-weight: bold;"><?php echo htmlspecialchars($pi['sku']); ?></td>
                                <td style="font-weight: 600;"><?php echo htmlspecialchars($pi['name']); ?></td>
                                <td><?php echo htmlspecialchars($pi['category'] ?: 'Uncategorized'); ?></td>
                                <td><?php echo htmlspecialchars($pi['supplier'] ?: 'No supplier'); ?></td>
                                <td><?php echo htmlspecialchars($pi['location'] ?: '—'); ?></td>
                                <td style="text-align: right; font-weight: bold;">
                                    <div style="display: flex; align-items: baseline; justify-content: flex-end; gap: 4px;">
                                        <span class="stock-num"><?php echo number_format($qty); ?></span>
                                        <span style="font-size: 8.5px; font-weight: normal; color: #64748b;"><?php echo htmlspecialchars($pi['uom'] ?: 'piece'); ?></span>
                                    </div>
                                </td>
                                <td style="font-size: 9px;"><?php echo $batchExpMarkup; ?></td>
                                <td style="text-align: right; font-family: monospace;">&#8369;<?php echo number_format($price, 2); ?></td>
                                <td style="text-align: center; color: #64748b;"><?php echo htmlspecialchars($pi['uom'] ?: 'piece'); ?></td>
                                <td style="text-align: right; color: #64748b; font-family: monospace;"><?php echo number_format($reorder); ?></td>
                                <td style="text-align: center;">
                                    <span class="print-badge <?php echo $statusClass; ?>">
                                        <?php echo $statusLabel; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Sign-off / Signature section -->
        <?php if ($showSignatures): ?>
        <div style="margin-top: 28px; display: grid; grid-template-columns: 1fr 1fr; gap: 40px; page-break-inside: avoid;">
            <div>
                <div id="printPrepByTitle" style="font-size: 8.5px; font-weight: bold; color: #64748b; text-transform: uppercase;">Audit Prepared By</div>
                <div style="font-size: 11px; font-weight: 700; color: #0f172a; margin-top: 2px;"><?php echo htmlspecialchars($generatedBy); ?> (<?php echo htmlspecialchars($generatedRole); ?>)</div>
                <div style="margin-top: 28px; border-bottom: 1px solid #cbd5e1; width: 85%;"></div>
                <div style="font-size: 8px; color: #94a3b8; margin-top: 3px;">Authorized Signature &amp; Stamp</div>
            </div>
            <div>
                <div id="printApprByTitle" style="font-size: 8.5px; font-weight: bold; color: #64748b; text-transform: uppercase;">Verified &amp; Approved By</div>
                <div id="printApprByRole" style="font-size: 11px; font-weight: 700; color: #0f172a; margin-top: 2px;">Warehouse Supervisor / Operations Control</div>
                <div style="margin-top: 28px; border-bottom: 1px solid #cbd5e1; width: 85%;"></div>
                <div style="font-size: 8px; color: #94a3b8; margin-top: 3px;">Authorized Signature &amp; Date</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer Strip -->
        <div class="print-doc-footer" style="margin-top: 22px; border-top: 1px solid #e2e8f0; padding-top: 8px; display: flex; justify-content: space-between; font-size: 8.5px; color: #94a3b8;">
            <div id="printFooterDocText"><?php echo htmlspecialchars($footerText); ?></div>
            <div>
                Generated on <?php echo date('Y-m-d H:i:s'); ?>
                <span class="page-counter-wrap" style="margin-left: 10px;">Page <span class="page-current">1</span></span>
            </div>
        </div>
    </div>
<?php
    }
}
