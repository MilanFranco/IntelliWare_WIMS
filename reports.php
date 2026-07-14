<?php
session_start();
if (!isset($_SESSION['Username'])) { header('Location: signin.php'); exit(); }
$role = $_SESSION['Role'] ?? 'staff';
if (!in_array($role, ['admin','manager'])) { header('Location: dash_staff.php'); exit(); }

require_once 'src/layout.php';
require_once 'config/db.php';

// --- 1. DATA FETCHING LOGIC ---

$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$statusFilter = $_GET['status'] ?? ''; // New Filter

$mainWhereConditions = [];
$mainParams = [];
$mainTypes = '';

// Optimize Date Filter (Avoid DATE() function for index performance)
if ($dateFrom) {
    $mainWhereConditions[] = "created_at >= ?";
    $mainParams[] = $dateFrom . " 00:00:00";
    $mainTypes .= 's';
}
if ($dateTo) {
    $mainWhereConditions[] = "created_at <= ?";
    $mainParams[] = $dateTo . " 23:59:59";
    $mainTypes .= 's';
}

if ($categoryFilter) {
    $mainWhereConditions[] = "category = ?";
    $mainParams[] = $categoryFilter;
    $mainTypes .= 's';
}

// Logic for Stock Status Filter
if ($statusFilter) {
    if ($statusFilter === 'out') {
        $mainWhereConditions[] = "quantity <= 0";
    } elseif ($statusFilter === 'low') {
        // Low stock but not zero
        $mainWhereConditions[] = "quantity <= reorder_point AND quantity > 0";
    } elseif ($statusFilter === 'attention') {
        // Both low and out
        $mainWhereConditions[] = "quantity <= reorder_point";
    } elseif ($statusFilter === 'good') {
        $mainWhereConditions[] = "quantity > reorder_point";
    }
}

$mainWhereClause = '';
if (count($mainWhereConditions) > 0) {
    $mainWhereClause = 'WHERE ' . implode(' AND ', $mainWhereConditions);
}

// Fetch products
$items = [];
$productQuery = "SELECT 
    product_id as id, 
    CONCAT('P-', LPAD(product_id, 4, '0')) as sku, 
    product_name as name, 
    barcode,
    category, 
    quantity as qty, 
    unit as uom, 
    reorder_point,
    created_at
FROM products 
$mainWhereClause 
ORDER BY category, product_name";

if ($stmt = $conn->prepare($productQuery)) {
    if (count($mainParams) > 0) {
        $stmt->bind_param($mainTypes, ...$mainParams); 
    }
    $stmt->execute();
    $productResult = $stmt->get_result();
    if ($productResult) {
        while ($row = $productResult->fetch_assoc()) {
            $items[] = $row;
        }
    }
    $stmt->close();
}

// Fetch unique categories
$categoriesQuery = "SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category";
$allCategories = [];
$resultCat = $conn->query($categoriesQuery);
if ($resultCat) {
    while ($row = $resultCat->fetch_assoc()) {
        $allCategories[] = $row['category'];
    }
}

// --- 2. DATA PROCESSING ---

$categoryGroups = [];
$lowStockCount = 0;
$totalItemsFiltered = count($items);

foreach ($items as $item) {
    $cat = $item['category'] ?: 'Uncategorized';
    
    if (!isset($categoryGroups[$cat])) {
        $categoryGroups[$cat] = [
            'items' => [],
            'total_qty' => 0
        ];
    }
    $categoryGroups[$cat]['items'][] = $item;
    $qty = (int)($item['qty'] ?? 0);
    $categoryGroups[$cat]['total_qty'] += $qty;
    
    $reorder = (int)($item['reorder_point'] ?? 10);
    if ($qty <= $reorder) {
        $lowStockCount++;
    }
}

$conn->close();

renderLayoutStart('Reports', $role, $_SESSION['Username'], $lowStockCount);
?>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>

<style>
    /* Button Styles */
    .browser-btn {
        text-align: left;
        transition: all 0.2s ease;
        position: relative;
        overflow: hidden;
    }
    .browser-btn.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: #51D55A;
    }
    .browser-btn:hover, .browser-btn.active {
        background: rgba(81, 213, 90, 0.1);
        border-color: rgba(81, 213, 90, 0.4);
    }
    .browser-btn.active .btn-title { color: #51D55A; }
    
    /* Scroll Behavior */
    .premium-scrollbar::-webkit-scrollbar { width: 6px; }
    .premium-scrollbar::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); }
    .premium-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
    .premium-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

    /* Toast Styles */
    #toast-container {
        visibility: hidden;
        position: fixed;
        bottom: 30px;
        left: 50%;
        transform: translateX(-50%) translateY(20px);
        padding: 12px 24px;
        border-radius: 12px;
        font-family: 'Onest', sans-serif;
        font-size: 14px;
        font-weight: 500;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        z-index: 2000;
        display: flex;
        align-items: center;
        gap: 12px;
        opacity: 0;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        border: 1px solid rgba(255,255,255,0.1);
        backdrop-filter: blur(10px);
    }
    #toast-container.show { visibility: visible; opacity: 1; transform: translateX(-50%) translateY(0); }
    #toast-container.success { background-color: rgba(6, 95, 70, 0.9); border-color: #34d399; color: #d1fae5; }
    #toast-container.error { background-color: rgba(127, 29, 29, 0.9); border-color: #f87171; color: #fee2e2; }
    
    .premium-input {
        background: rgba(19, 39, 91, 0.8);
        color: #E3E3E3;
        border: 1px solid rgba(48, 67, 116, 0.6);
        border-radius: 12px;
        padding: 0 16px;
        font-size: 14px;
        font-family: 'Onest', sans-serif;
        transition: all 0.3s;
    }
    .premium-input:focus {
        border-color: #51D55A;
        outline: none;
        box-shadow: 0 0 0 3px rgba(81, 213, 90, 0.1);
    }
    
    /* Status Badge Styles */
    .status-badge {
        font-size: 10px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 4px;
        text-transform: uppercase;
        display: inline-flex;
        align-items: center;
    }
    .status-badge.low { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
    .status-badge.out { background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.4); }
    .status-badge.good { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }

</style>

<div id="toast-container">
    <span id="toast-icon"></span>
    <span id="toast-message"></span>
</div>

<div class="premium-card mb-6" style="overflow:visible;">
    <div class="p-5 border-b border-[#2A4484]/50 flex justify-between items-center bg-[#13275B]/30">
        <h3 class="font-onest font-semibold text-lg text-white flex items-center gap-2">
            <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
            Generate Report
        </h3>
    </div>
    
    <div class="p-5">
        <form method="GET" action="reports.php" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            <div>
                <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2">Date Added (From)</label>
                <input type="date" name="date_from" class="premium-input w-full h-[46px]" value="<?php echo htmlspecialchars($dateFrom); ?>" />
            </div>
            <div>
                <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2">Date Added (To)</label>
                <input type="date" name="date_to" class="premium-input w-full h-[46px]" value="<?php echo htmlspecialchars($dateTo); ?>" />
            </div>
            <div>
                <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2">Category</label>
                <select name="category" class="premium-input inventory-select w-full h-[46px] cursor-pointer">
                    <option value="">All Categories</option>
                    <?php foreach ($allCategories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $categoryFilter === $cat ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-[#9aa6d1] text-xs font-onest font-semibold mb-2">Stock Status</label>
                <select name="status" class="premium-input inventory-select w-full h-[46px] cursor-pointer">
                    <option value="">All Status</option>
                    <option value="good" <?php echo $statusFilter === 'good' ? 'selected' : ''; ?>>Good Stock</option>
                    <option value="attention" <?php echo $statusFilter === 'attention' ? 'selected' : ''; ?>>Attention Needed (Low/Out)</option>
                    <option value="low" <?php echo $statusFilter === 'low' ? 'selected' : ''; ?>>Low Stock</option>
                    <option value="out" <?php echo $statusFilter === 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                </select>
            </div>

            <div class="flex gap-2">
                <button class="premium-button flex-1 h-[46px] flex items-center justify-center gap-2" type="submit">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    Search
                </button>
                <?php if ($dateFrom || $dateTo || $categoryFilter || $statusFilter): ?>
                    <a href="reports.php" title="Clear Filters" class="h-[46px] px-4 rounded-xl border border-[#2A4484] bg-[#13275B] text-[#9aa6d1] hover:text-white flex items-center justify-center transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4l5 5 5 15 5-5 5-15H4z"></path></svg>
                        <span class="ml-1 text-xs">Reset</span>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if (empty($items)): ?>
    <div class="premium-card p-12 text-center flex flex-col items-center justify-center">
        <div class="w-16 h-16 bg-[#13275B] rounded-full flex items-center justify-center mb-4 border border-[#2A4484]">
            <svg class="w-8 h-8 text-[#9aa6d1]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        </div>
        <h3 class="text-xl font-semibold text-white mb-2 font-onest">No Data Found</h3>
        <p class="text-[#9aa6d1] max-w-sm">No items matched your filters. Try clearing the date filters to see older stock.</p>
        <?php if ($dateFrom || $dateTo): ?>
            <a href="reports.php" class="mt-4 text-[#51D55A] text-sm hover:underline">Clear Date Filters</a>
        <?php endif; ?>
    </div>
<?php else: ?>

    <div class="premium-card mb-6">
        <div class="p-5 border-b border-[#2A4484]/50 flex flex-col md:flex-row justify-between items-center gap-4 bg-[#13275B]/30">
            <div>
                <h3 class="font-onest font-semibold text-lg text-white">Stock Explorer</h3>
                <p class="text-xs text-[#9aa6d1] mt-1">Showing <?php echo $totalItemsFiltered; ?> items based on current filters.</p>
            </div>
            <div class="flex gap-2">
                <button onclick="downloadPDF()" class="premium-button py-2 px-4 text-xs h-auto flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    PDF
                </button>
                <button onclick="downloadExcel()" class="premium-button py-2 px-4 text-xs h-auto flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Excel
                </button>
            </div>
        </div>

        <div class="p-6 grid grid-cols-1 lg:grid-cols-12 gap-6 h-auto lg:h-[600px]">
            
            <div class="lg:col-span-3 flex flex-col lg:h-full lg:border-r border-[#2A4484]/30 lg:pr-4">
                <div class="text-xs font-semibold text-[#9aa6d1] uppercase tracking-wider mb-3 px-1">Categories</div>
                
                <div class="overflow-y-auto premium-scrollbar pr-2 space-y-2 h-[350px] lg:h-[520px] min-h-0">
                    <?php foreach ($categoryGroups as $categoryName => $group):
                        $productCount = count($group['items']);
                        $totalQty = $group['total_qty'];
                        $catKeyAttr = htmlspecialchars(json_encode($categoryName), ENT_QUOTES);
                    ?>
                    <button class="browser-btn w-full p-3 rounded-xl border border-transparent bg-white/5 hover:bg-white/10 group flex-shrink-0" onclick="toggleCategory(<?php echo $catKeyAttr; ?>, this)">
                        <div class="flex justify-between items-center mb-1">
                            <span class="btn-title font-semibold text-[#E3E3E3] text-sm group-hover:text-white text-left truncate w-[85%]"><?php echo htmlspecialchars($categoryName); ?></span>
                            <svg class="w-4 h-4 text-[#9aa6d1] opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </div>
                        <div class="flex justify-between text-[10px] text-[#9aa6d1]">
                            <span><?php echo $productCount; ?> Items</span>
                            <span><?php echo number_format($totalQty); ?> Units</span>
                        </div>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="lg:col-span-5 flex flex-col lg:h-full lg:border-r border-[#2A4484]/30 lg:px-4 mt-6 lg:mt-0">
                <div class="flex justify-between items-center mb-3 px-1">
                    <div class="text-xs font-semibold text-[#9aa6d1] uppercase tracking-wider">Products</div>
                    <div id="itemPanelMeta" class="text-[10px] text-[#51D55A]">Select a category</div>
                </div>
                
                <div class="overflow-y-auto premium-scrollbar pr-2 bg-[#0d1638]/50 rounded-xl border border-[#2A4484]/30 p-2 h-[350px] lg:h-[520px] min-h-0" id="itemList">
                    <div class="flex flex-col items-center justify-center h-full text-[#9aa6d1] opacity-60 min-h-[150px]">
                        <svg class="w-10 h-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                        <span class="text-xs">Select a category on the left</span>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-4 flex flex-col lg:h-full lg:pl-2 mt-6 lg:mt-0">
                <div class="flex justify-between items-center mb-3 px-1">
                    <div class="text-xs font-semibold text-[#9aa6d1] uppercase tracking-wider">Stock Health</div>
                    <div id="statusPanelMeta" class="text-[10px] text-[#9aa6d1]">Overview</div>
                </div>
                
                <div class="overflow-y-auto premium-scrollbar pr-2 bg-[#0d1638]/50 rounded-xl border border-[#2A4484]/30 p-2 h-[350px] lg:h-[520px] min-h-0" id="statusList">
                    <div class="flex flex-col items-center justify-center h-full text-[#9aa6d1] opacity-60 min-h-[150px]">
                        <svg class="w-10 h-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span class="text-xs">Status details will appear here</span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <div class="premium-card">
        <div class="p-5 border-b border-[#2A4484]/50 flex justify-between items-center bg-[#13275B]/30">
            <h3 class="font-onest font-semibold text-lg text-white">Inventory Distribution</h3>
        </div>
        <div class="p-5 h-[300px] relative">
            <canvas id="reportCatChart"></canvas>
        </div>
    </div>

<?php endif; ?>

<script>
  const items = <?php echo json_encode($items); ?>;
  const categoryData = <?php echo json_encode($categoryGroups); ?>;
  
  // Chart Init
  (function(){
    if (items.length === 0) return;
    const catTotals = {};
    items.forEach(i=>{ 
      const c=(i.category||'Uncategorized'); 
      catTotals[c]=(catTotals[c]||0)+parseInt(i.qty||0,10);
    });
    
    const ctx = document.getElementById('reportCatChart');
    if(ctx){
      new Chart(ctx, {
        type: 'bar',
        data: { 
          labels: Object.keys(catTotals), 
          datasets:[{ 
            label:'Quantity', 
            data: Object.values(catTotals), 
            backgroundColor: '#51D55A',
            hoverBackgroundColor: '#4BC052',
            borderRadius: 6,
            barPercentage: 0.6
          }] 
        },
        options: { 
          responsive: true,
          maintainAspectRatio: false,
          plugins:{ legend:{display:false} },
          scales:{
            x: { ticks: { color: '#9aa6d1', font: { family: 'Onest' } }, grid: { display:false } },
            y: { ticks: { color: '#9aa6d1', font: { family: 'Onest' } }, grid: { color: 'rgba(255,255,255,0.05)' } }
          }
        }
      });
    }
  })();

  // Interaction Logic
  let activeCategory = null;

  function toggleCategory(categoryKey, buttonEl) {
    const itemList = document.getElementById('itemList');
    const statusList = document.getElementById('statusList');
    const itemMeta = document.getElementById('itemPanelMeta');
    const statusMeta = document.getElementById('statusPanelMeta');

    document.querySelectorAll('.browser-btn').forEach(b => b.classList.remove('active'));

    if (activeCategory === categoryKey) {
        activeCategory = null;
        const placeholder = `
            <div class="flex flex-col items-center justify-center h-full text-[#9aa6d1] opacity-60">
                <svg class="w-10 h-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                <span class="text-xs">Select a category</span>
            </div>`;
        itemList.innerHTML = placeholder;
        statusList.innerHTML = placeholder;
        itemMeta.innerText = 'Select a category';
        statusMeta.innerText = 'Overview';
        return;
    }

    activeCategory = categoryKey;
    buttonEl.classList.add('active');

    const group = categoryData[categoryKey];
    const products = group.items || [];
    
    itemMeta.innerText = `${products.length} Products Found`;
    statusMeta.innerText = `Total Stock: ${group.total_qty}`;

    // Render Lists
    itemList.innerHTML = products.map((p, index) => `
        <div class="flex justify-between items-center p-3 mb-2 rounded-lg bg-[#1D387B]/40 border border-[#2A4484]/30 hover:border-[#51D55A]/30 transition-colors group h-[60px]" onmouseover="syncHover(${index}, true)" onmouseout="syncHover(${index}, false)" id="prod-${index}">
            <div class="w-3/4">
                <div class="font-semibold text-[#E3E3E3] text-sm group-hover:text-white truncate">${p.name}</div>
                <div class="text-[10px] text-[#9aa6d1] font-mono">${p.sku}${p.barcode ? ' · ' + p.barcode : ''}</div>
            </div>
            <div class="font-mono text-[#51D55A] font-bold text-sm">${p.qty}</div>
        </div>
    `).join('');

    statusList.innerHTML = products.map((p, index) => {
        const qty = parseInt(p.qty);
        const reorder = parseInt(p.reorder_point || 10);
        let badgeClass = 'good';
        let badgeText = 'GOOD';
        
        if (qty <= 0) {
             badgeClass = 'out';
             badgeText = 'OUT OF STOCK';
        } else if (qty <= reorder) {
             badgeClass = 'low';
             badgeText = 'LOW STOCK';
        }

        return `
        <div class="flex justify-between items-center p-3 mb-2 rounded-lg bg-[#1D387B]/40 border border-[#2A4484]/30 h-[60px]" id="stat-${index}">
            <div class="text-xs text-[#E3E3E3] truncate pr-2 w-1/2">${p.name}</div>
            <span class="status-badge ${badgeClass}">
                ${badgeText}
            </span>
        </div>
    `}).join('');
  }

  // --- SCROLL SYNCHRONIZATION ---
  const itemList = document.getElementById('itemList');
  const statusList = document.getElementById('statusList');

  if(itemList && statusList) {
      itemList.addEventListener('scroll', function() {
          statusList.scrollTop = itemList.scrollTop;
      });
      statusList.addEventListener('scroll', function() {
          itemList.scrollTop = statusList.scrollTop;
      });
  }

  // Visual Sync on Hover
  function syncHover(index, isHovering) {
      const prodRow = document.getElementById(`prod-${index}`);
      const statRow = document.getElementById(`stat-${index}`);
      if(prodRow && statRow) {
          if(isHovering) {
              statRow.style.borderColor = 'rgba(81, 213, 90, 0.3)';
              statRow.style.backgroundColor = 'rgba(29, 56, 123, 0.6)';
          } else {
              statRow.style.borderColor = 'rgba(42, 68, 132, 0.3)';
              statRow.style.backgroundColor = 'rgba(29, 56, 123, 0.4)'; // Reset to original bg/border
          }
      }
  }

  function downloadPDF() {
    if (items.length === 0) { showToast("No data to export", "error"); return; }
    showToast("Generating PDF...", "success");
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    doc.setFontSize(18);
    doc.setTextColor(29, 56, 123);
    doc.text("Inventory Report", 14, 20);
    doc.setFontSize(10);
    doc.setTextColor(100);
    doc.text(`Generated: ${new Date().toLocaleDateString()}`, 14, 28);
    doc.text(`Total Products: ${items.length}`, 14, 33);
    const tableData = items.map(item => [item.sku, item.name, item.category, item.qty, item.uom, item.reorder_point]);
    doc.autoTable({
        head: [['SKU', 'Name', 'Category', 'Qty', 'UoM', 'Reorder Pt']],
        body: tableData,
        startY: 40,
        theme: 'grid',
        headStyles: { fillColor: [29, 56, 123] },
        styles: { fontSize: 9 }
    });
    doc.save(`Inventory_Report_${new Date().toISOString().slice(0,10)}.pdf`);
  }

  function downloadExcel() {
    if (items.length === 0) { showToast("No data to export", "error"); return; }
    showToast("Exporting Excel...", "success");
    const dataToExport = items.map(item => ({
      'SKU': item.sku,
      'Name': item.name,
      'Category': item.category,
      'Quantity': parseInt(item.qty),
      'Unit': item.uom,
      'Reorder Point': parseInt(item.reorder_point || 10)
    }));
    const ws = XLSX.utils.json_to_sheet(dataToExport);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Inventory');
    XLSX.writeFile(wb, `Inventory_Export.xlsx`);
  }

  function showToast(msg, type) {
      const toast = document.getElementById("toast-container");
      const messageSpan = document.getElementById("toast-message");
      const iconSpan = document.getElementById("toast-icon");
      messageSpan.innerText = msg;
      iconSpan.innerHTML = type === 'success' 
        ? '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>'
        : '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg>';
      toast.className = 'show ' + type;
      setTimeout(() => { toast.className = ''; }, 3000);
  }
</script>

<?php renderLayoutEnd(); ?>