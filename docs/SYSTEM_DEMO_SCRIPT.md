# IntelliWare: Comprehensive System Demonstration & Walkthrough Script
**A Data-Driven Inventory Management System Using Data Analytics and Rule-Based Classification for Decision Support**  
**Client / Case Study:** Shea and Nicole's General Merchandise Store (Pineda, Pasig City)  
**Target Roles:** Administrator (Store Owner), Store Manager, Warehouse Staff  

---

## Document Overview & Presenter Guidelines

### 1. Demonstration Purpose
This comprehensive script serves as the master walkthrough and presentation guide for demonstrating the **IntelliWare Inventory Management System (IMS)**. It provides:
- **Exact Step-by-Step Navigation:** How to move seamlessly through each module without getting lost.
- **Button-by-Button & Field-by-Field Explanations:** What each interface component is, what it controls, how it behaves, and what happens behind the scenes.
- **Role-Based Demonstration Flow:** Clear separation of permissions and duties across **Staff**, **Manager**, and **Administrator**.
- **Presenter Dialogue ("Say:") & Visual Actions ("Do:"):** Ready-to-deliver script lines paired with click-by-click instructions.
- **Technical & Business Rationale ("Explain:"):** The capstone/thesis justification explaining the "why" behind every feature (e.g., Rule-Based Classification, Python ML Demand Forecasting, Philippine Seasonal Market Pulse, Computer Vision Packaging Recognition).

---

### 2. Pre-Demonstration Setup & System Preparation

Before commencing the presentation in front of panelists, evaluators, or clients:

1. **Start Server Environment:**
   - Launch **XAMPP Control Panel**.
   - Start **Apache** and **MySQL** services (both indicators should turn green).
2. **Verify Database:**
   - Ensure the database `warehouse_system` is active in `phpMyAdmin` (`http://localhost/phpmyadmin/`).
3. **Verify Webcam / Camera Permissions:**
   - Ensure your browser (Chrome or Edge) has granted camera access permissions to `http://localhost/` for the AI Camera Scanner.
4. **Prepare Demonstration Accounts:**
   - **Administrator Account:** `Username: admin` | `Password: admin123` (Full system access)
   - **Manager Account:** `Username: manager` | `Password: manager123` (Operational & analytics access)
   - **Staff Account:** `Username: staff` | `Password: staff123` (Frontline floor transactions)
5. **Display & Theme Preparation:**
   - IntelliWare features an automatic **Philippine Time-Based Theme Engine** (Light mode from 6:00 AM to 5:59 PM PHT; Dark mode from 6:00 PM to 5:59 AM PHT). Ensure the browser zoom is set to 100% for optimal viewing.

---

## System Architecture & Role Matrix

| Module / Page | Staff | Manager | Administrator | Core Features & Purpose |
| :--- | :---: | :---: | :---: | :--- |
| **Sign In (`signin.php`)** | Yes | Yes | Yes | RBAC authentication, password recovery with OTP, auto-routing |
| **Staff Dashboard (`dash_staff.php`)** | Yes | - | - | Fast operational shortcuts, live stock counters, recent transaction feed |
| **Manager Dashboard (`dash_manager.php`)**| - | Yes | - | Restock alerts, demand metrics, category distribution, operational health |
| **Admin Dashboard (`dash_admin.php`)** | - | - | Yes | Full executive overview, stock valuation, critical risk alert, demand chart |
| **Inventory Catalog (`products.php`)** | View/Add | Full CRUD | Full CRUD | Master catalog, AI Vision Scanner, OCR auto-fill, card/table view |
| **Receive Inventory (`receive.php`)** | Yes | Yes | Yes | Inbound POS terminal, camera barcode/label scan, batch/expiry logging |
| **Release Inventory (`release.php`)** | Yes | Yes | Yes | Outbound POS terminal, fast dispensing, stock depletion tracking |
| **Transaction History (`inventory_history.php`)**| Yes | Yes | Yes | Chronological ledger, stock before/after audit numbers, date filters |
| **System Audit Trail (`transaction_logs.php`)** | - | Yes | Yes | Security events, login audits, user actions, full activity modal |
| **Insights & Analytics (`insights.php`)** | - | Yes | Yes | 5 analytic tabs, ML demand forecast, Philippine seasonal pulse |
| **Reports (`reports.php`)** | - | Yes | Yes | 3-column explorer, tabular audit view, PDF export, Excel download, print |
| **User Management (`users.php`)** | - | - | Yes | User CRUD, role assignment, password resets, account security |
| **System Settings (`settings.php`)** | Profile | General/Alerts | Full Admin | Facility settings, SMTP email, SMS, AI vision thresholds, ML training, SQL backups |

---

# PART 1: System Entry & Authentication

## Page: Login Portal (`signin.php`)

### Visual Layout & Interface Elements
- **Branding:** Centered glowing IntelliWare logo with animated atmospheric background mesh.
- **Authentication Card (`.signinbox`):**
  - **Username Input Field:** Accepts alphanumeric usernames.
  - **Password Input Field:** Features an interactive eye icon on the right to toggle password visibility (plain text vs masked bullets).
  - **"Forgot password?" Link:** Styled in subtle text, routing directly to the 3-step recovery flow.
  - **"Login" Button (`.btnlogin`):** Primary green button (`#51D55A`) with smooth hover expansion.
- **Footer:** System copyright and information matching store branding.

### Presenter Walkthrough

#### Step 1.1: Explaining the Gateway & Role-Based Access Control (RBAC)
> **Action (Do):** Open the browser to `http://localhost/IntelliWare/` or click the sign-in URL.
>
> **Dialogue (Say):**  
> *"Good day, respected members of the panel. Welcome to the formal system demonstration of IntelliWare: A Data-Driven Inventory Management System with Data Analytics and Rule-Based Classification, developed specifically for Shea and Nicole's General Merchandise Store in Pineda, Pasig City.*  
>  
> *We begin at the secure gateway of the system—the Sign-In portal. In a 24-hour retail general merchandise store like Shea & Nicole's, multiple employees handle stock at different shifts. Therefore, security and accountability begin at login. IntelliWare implements strict Role-Based Access Control (RBAC). The system inspects encrypted credentials upon entry and immediately routes each user to an interface specifically tailored to their operational responsibilities: frontline Staff, Store Managers, or the Store Owner and Administrator."*

#### Step 1.2: Password Recovery Flow (`forgot_password.php`)
> **Action (Do):** Click the **"Forgot password?"** link beneath the password input.
>
> **Dialogue (Say):**  
> *"Should an employee or administrator forget their credentials, IntelliWare incorporates a secure 3-Step Password Recovery flow powered by PHPMailer and automated security tokens:*  
> *1. **Step 1 (Identification):** The user enters their registered username or corporate email.*  
> *2. **Step 2 (Verification):** The system generates a time-sensitive, cryptographically secure 6-digit One-Time Password (OTP) sent directly to their verified email address.*  
> *3. **Step 3 (Password Reset):** Upon typing the correct code, the user is authorized to establish a new password with immediate database hashing using BCRYPT.*  
>  
> *Let us now return to the login page and authenticate as our frontline **Staff** member to demonstrate warehouse floor operations."*

---

# PART 2: Frontline Operations (Staff Role)

## 2.1 Staff Dashboard (`dash_staff.php`)

### Visual Layout & Interface Elements
- **Header:**
  - **Hamburger Toggle Icon:** Expands or collapses the navigation sidebar.
  - **Page Title:** Displays `Dashboard`.
  - **Notification Bell (`#bellBtn`):** Displays real-time unread alert count with a pulsing badge.
  - **Philippine Time Theme Toggle (`#themeToggleBtn`):** Shows sun/moon icon. Toggles Light and Dark modes.
  - **User Profile Pill:** Staff avatar initials, display name, and `Staff` role badge.
- **Hero Card:**
  - Dynamic Philippine time greeting: *"Good morning/afternoon/evening, [Staff Name]"*.
  - Role pill labeled `STAFF`.
  - Warehouse status summary: *"All X SKUs across Y categories are well stocked."* or low stock alerts.
- **Operational Metric Cards (4 Grid Cards):**
  - **Stock Value:** Displays total peso valuation (₱) of store inventory.
  - **Total Products:** Total active catalog SKUs.
  - **Low Stock:** Amber alert card highlighting items needing replenishment.
  - **Out of Stock:** Red warning card highlighting zero-stock items.
- **Operational Grid (2x2 Layout):**
  - **Quick Access Card:** High-contrast shortcut buttons for **Receive Inventory** (Inbound) and **Release Inventory** (Outbound).
  - **Recent Activity Feed:** Real-time log of the last 5 transactions with green down-arrows for Restocked items and red up-arrows for Dispensed items.
  - **Critical Stock Levels:** Real-time list of products breaching safety reorder points.
  - **Inventory Distribution Chart:** Interactive bar chart visualizing stock distribution across store product categories (Beverages, Canned Goods, Dry Goods, Personal Care, Household Items, etc.).

### Presenter Walkthrough

#### Step 2.1: Logging in as Staff
> **Action (Do):** Type `staff` into Username, `staff123` into Password, and click **Login**.
>
> **Dialogue (Say):**  
> *"We are now logged in as a store staff member. Notice the system instantly recognized the account role and brought us to `dash_staff.php`. Frontline employees do not need to be overwhelmed with complex executive financial reports or system administration menus. Their dashboard is purpose-built for speed and clarity.*  
>  
> *At the very top, notice the Header. It contains our global navigation controls:*  
> *1. The **Sidebar Drawer Toggle**, which collapses the menu to maximize screen workspace on smaller mobile tablets or barcode stations.*  
> *2. The **Notification Bell**, which alerts staff whenever any item drops below its defined Reorder Point.*  
> *3. The **Philippine Time Theme Toggle**, which switches between dark and light modes. By default, IntelliWare automatically synchronizes with Philippine Standard Time (PHT), switching to Light Mode during daytime shifts and Dark Mode during nighttime shifts.*  
>  
> *On the main screen, staff immediately see the 4 operational metric cards, quick-action tiles for stock movement, and a live category distribution chart. Let us now demonstrate the two primary daily warehouse workflows: Receiving and Releasing stock."*

---

## 2.2 Inbound Stock Receiving (`receive.php`)

### Visual Layout & Interface Elements
- **Layout Architecture:** POS-style split layout (`pos-layout`):
  - **Left Panel (Transaction Workspace & Cart):**
    - **Product Search Bar (`#inv-product-search`):** Real-time asynchronous search input with automatic clear button (`&times;`).
    - **Camera Scanner Button (`#ai-capture-btn` / `#inv-camera-scanner-wrap`):** Activates camera viewfinder for hands-free scanning.
    - **Transaction Cart Table (`#inv-cart-table`):** Table showing Item Thumbnail, Product Name & SKU, Current On-Hand Stock, Quantity Adjuster (`-` and `+` buttons), Unit Price, Total Computed Price, and Remove (`Trash`) icon.
    - **Transaction Summary:** Total Products count and Total Units count.
    - **Remarks Field (`#inv-remarks-input`):** Free-text box for Delivery Receipt (DR) number, Supplier Invoice, or Batch Notes.
    - **Action Buttons:** **"Clear Transaction"** (secondary gray button) and **"Confirm Receive"** (primary vibrant green button).
  - **Right Panel (Product Information Card):**
    - Dynamic product card that updates when an item is selected.
    - Displays high-resolution reference photo, SKU badge, Category, and Storage Location / Shelf.
    - **Batch Metadata Inputs (Inbound Only):**
      - **Batch Number Field:** For delivery lot tracking.
      - **Expiry Date Picker:** Required for perishable goods.
      - **Manufacturing Date Picker:** For shelf-life calculation.

### Presenter Walkthrough

#### Step 2.2: Demonstrating the Inbound Stock Movement (Manual & Camera)
> **Action (Do):** Click **"Receive Inventory"** from the sidebar or dashboard tile.
>
> **Dialogue (Say):**  
> *"When a delivery truck arrives from a supplier at Shea & Nicole's General Merchandise Store, staff navigate to the Receive Inventory module. This interface is structured like an industrial Point-of-Sale (POS) terminal, minimizing manual typing and preventing input errors.*  
>  
> *Staff have two ways to add delivered items to this receiving transaction:*  
> *1. **Manual Search:** They can type the product name or category into the search bar. Watch as I type 'Century Tuna'—the dropdown displays instant matches complete with current stock levels and packaging thumbnails.*  
> *2. **AI Camera Scanner:** If staff are operating on a phone, tablet, or web terminal with a webcam, they can click the Camera Scanner button. The camera activates, displaying a guided viewfinder reticle. When an item packaging is held up to the lens, our embedded computer vision model classifies the product and automatically adds it to the transaction list without a single keystroke!*  
>  
> *Let us select **Century Tuna Flakes in Oil 155g**. Notice how the Right Panel immediately updates:*  
> *- It confirms the shelf location: **Shelf B2 - Canned Goods**.*  
> *- It displays the current on-hand stock and reorder point.*  
> *- For receiving goods, it provides dedicated fields for **Batch Number** and **Expiry Date**, ensuring perishable goods can later be tracked under First-Expiry, First-Out (FEFO) warehouse management.*  
>  
> *Let us adjust the delivered quantity to 24 units using the plus button or direct input. In the Remarks field, we log: 'Supplier Delivery - DR #84920'. Now, we click the green **'Confirm Receive'** button."*
>
> **Action (Do):** Click **"Confirm Receive"**. Watch the green success toast appear: *"1 product(s) received — 24 total unit(s). Stock updated successfully."*
>
> **Dialogue (Say):**  
> *"Instantly, IntelliWare performs an ACID-compliant database transaction: the warehouse quantity is incremented, the batch information is recorded, and an audit trail log is generated in real time."*

---

## 2.3 Outbound Stock Releasing & Sales Dispensing (`release.php`)

### Visual Layout & Interface Elements
- **Layout Architecture:** Dedicated Outbound POS-terminal (`pos-accent-release`):
  - **Red Thematic Accent:** Visual indicator signifying inventory deduction / outbound movement.
  - **Search & Cart:** Similar POS structure to prevent staff retraining.
  - **Safety Stock Limiters:** Automatically enforces that staff cannot release more units than what currently exists in warehouse inventory.
  - **"Confirm Release" Button:** Red primary action button with alert styling.

### Presenter Walkthrough

#### Step 2.3: Demonstrating Stock Outflow
> **Action (Do):** Click **"Release Inventory"** from the sidebar menu under *Transactions*.
>
> **Dialogue (Say):**  
> *"Now let us demonstrate the reverse flow—releasing inventory for customer sales, shelf restocking, or wholesale orders. Notice the interface adopts a distinct red accent to visually alert the operator that inventory is leaving the warehouse.*  
>  
> *Let us search for the same product, **Century Tuna Flakes in Oil 155g**. It is added to our release cart. Notice that if an operator attempts to release more items than are physically on hand, the system immediately disables confirmation and highlights the field in red, completely eliminating negative stock balances.*  
>  
> *Let us record a release of 6 units for daily retail counter sales. We enter 'Counter Sales Dispense' in the Remarks, and click **'Confirm Release'**."*
>
> **Action (Do):** Click **"Confirm Release"**. A green toast confirms stock deduction.

---

## 2.4 Transaction History Ledger (`inventory_history.php`)

### Visual Layout & Interface Elements
- **Filter Toolbar:**
  - Search box: Matches Product Name or Transaction ID.
  - Type Filter Dropdown: `All Types`, `Stock In (Receiving)`, `Stock Out (Releasing)`.
  - Date Range Pickers: `Date From` and `Date To`.
  - Reset & Search Buttons.
- **Ledger Table (`.premium-table`):**
  - **Transaction ID:** Unique reference code (e.g., `#TXN-1042`).
  - **Type Badge:** High-visibility green badge for `Stock In` vs red badge for `Stock Out`.
  - **Product Name & SKU:** Item description.
  - **Quantity Delta:** Displayed with clear directional signs (`+24` in green or `-6` in red).
  - **Audit Balance:** Displays **Previous Stock** $\rightarrow$ **New Stock** (e.g., $10 \rightarrow 34 \rightarrow 28$).
  - **Operator / Staff:** Account username of who processed the entry.
  - **Timestamp:** Exact date and time (PHT).
  - **Remarks:** Notes entered during transaction.

### Presenter Walkthrough

#### Step 2.4: Verifying Complete Traceability
> **Action (Do):** Click **"History"** under the *Transactions* group in the sidebar.
>
> **Dialogue (Say):**  
> *"To ensure complete transparency, every physical movement of goods is recorded in the Inventory History ledger. As you can see on screen, the exact transactions we just performed are logged at the very top:*  
> *- First, the delivery of 24 units, showing previous stock of 10 moving to 34.*  
> *- Second, the release of 6 units, showing stock adjusting from 34 down to 28.*  
>  
> *Every entry records the exact operator, timestamp, and transaction ID. Staff can filter by date range or transaction type whenever conducting physical cycle counts.*  
>  
> *Now, let us log out of the Staff account and log in as the **Store Manager** to explore managerial oversight and the System Audit Trail."*
>
> **Action (Do):** Click user profile dropdown $\rightarrow$ click **Logout**.

---

# PART 3: Store Manager Role

## 3.1 Manager Dashboard (`dash_manager.php`)

### Visual Layout & Interface Elements
- **Hero Banner:** Manager greeting, `MANAGER` role badge, and automated store stock health summary.
- **Top Metric Cards:** Stock Value (₱), Active SKUs, Low Stock alerts, and Depleted Out of Stock count.
- **Managerial Widgets:**
  - **Operational Quick Access:** Direct access to Inbound and Outbound processing.
  - **Critical Stock Levels Widget:** Direct restock management links for items below ROP.
  - **Category Stock & Demand Distribution:** Visual representation of category performance.
  - **Recent Movements:** Real-time stream of warehouse floor activities.

### Presenter Walkthrough

#### Step 3.1: Logging in as Manager
> **Action (Do):** Log in with `Username: manager` and `Password: manager123`.
>
> **Dialogue (Say):**  
> *"We are now logged in as the Store Manager. Notice that while staff only had access to daily transaction menus, the Manager's sidebar expands to include **Insights**, **Audit Log**, and **Reports**.*  
>  
> *The Manager Dashboard focuses on operational control. Managers need to immediately identify stock bottlenecks, potential stockouts, and pending replenishments so customer orders are never disrupted.*  
>  
> *Notice the **Critical Stock Levels** card on the right: it immediately alerts management to items requiring vendor purchase orders. Let us now examine the **Audit Log**, an exclusive managerial and administrative accountability feature."*

---

## 3.2 System Audit Trail (`transaction_logs.php`)

### Visual Layout & Interface Elements
- **Page Hero Header:** System Audit Trail status and active administrator/manager indicator.
- **Two Distinct Audit Streams:**
  1. **Stock Movement Audit:** Master paginated log of every inventory adjustment, including automated entries versus manual overrides.
  2. **System Security Events Log (`logs` table):** Records non-inventory administrative events, such as user logins, failed authentications, role adjustments, password modifications, and settings changes.
- **Interactive System Activity Modal (`#sysActivityModal`):** Accessible via *"View all activity"* to inspect timestamped logs with searching and filtering.

### Presenter Walkthrough

#### Step 3.2: Demonstrating Governance & Accountability
> **Action (Do):** Click **"Audit log"** in the sidebar.
>
> **Dialogue (Say):**  
> *"In retail inventory management, shrinkage and undocumented discrepancies are major risks. IntelliWare solves this with an unalterable System Audit Trail.*  
>  
> *Here, management can inspect:*  
> *1. Complete chronological stock adjustments across all categories.*  
> *2. Crucial system security events: every time an employee signs in, changes a setting, or updates product details, the event is permanently logged with user ID, action description, and server timestamp.*  
>  
> *Let us now log out and enter as the **Administrator / Store Owner** to review the full power of IntelliWare, including our Master Catalog, AI Camera Training, Data Mining Analytics, and Executive Reports."*
>
> **Action (Do):** Log out of Manager account.

---

# PART 4: System Administrator & Store Owner Role

## 4.1 Executive Admin Dashboard (`dash_admin.php`)

### Visual Layout & Interface Elements
- **Executive Hero Card (`.dashboard-hero-banner`):**
  - High-level greeting with `ADMIN` role badge.
  - Primary Action Button: **"Add Product"** (`#addProductBtn`) with quick modal trigger.
  - **Dynamic Priority AI Insight Box (`.hero-product-card`):**  
    - Dynamically queries historical sales and forecasts the highest-priority stock risk for the coming month.
    - Displays product name, category, risk badge (e.g., `Critical Outage`, `High Risk`, `Low Cover`, `Slow Moving`), and natural language recommendation (e.g., *"I noticed you have 0 stock against a demand of 45 units. I strongly advise you to restock to avoid missed sales."*).
    - Clicking the card navigates directly into that product's deep insights!
- **Executive KPI Metric Cards (4 Cards):**
  - **Stock Value:** Computed dynamically as $\sum (\text{Quantity} \times \text{Price})$, formatted in Philippine Pesos (e.g., `₱184,250.00`).
  - **Total Products:** Total non-archived catalog items.
  - **Low Stock:** Total items currently $\le$ Reorder Point.
  - **Out of Stock:** Total items with 0 stock on hand.
- **Executive Analytics Widgets:**
  - **Quick Access Tiles:** Fast routing to Receive and Release.
  - **Recent Activity Ledger:** Live transactional updates.
  - **Critical Stock Levels:** Top 7 most critical products needing purchase orders.
  - **Top Demand Bar Chart (`#demandChart`):** Horizontal bar chart powered by Chart.js showing top products by sales volume in the past 90 days. Clicking any bar instantly filters the Insights page for that product!

### Presenter Walkthrough

#### Step 4.1: Demonstrating Executive Oversight
> **Action (Do):** Log in with `Username: admin` and `Password: admin123`.
>
> **Dialogue (Say):**  
> *"We now log in as the Administrator, representing the store owner, Ms. Novy D. Espiritu. The Administrator has complete oversight over the business.*  
>  
> *Look at the Executive Hero Banner. Beyond standard metrics, IntelliWare features an automated Priority Risk Insight banner. The system proactively scanned all transaction history and determined that our highest-risk item this month is highlighted right here on the home page, explaining expected sales demand versus current warehouse holdings.*  
>  
> *Notice our 4 executive KPI cards, specifically the **Stock Value** card. Instead of manual spreadsheet counting at the end of every month, IntelliWare dynamically evaluates total on-hand inventory valuation down to the centavo.*  
>  
> *At the bottom right is our **Top Demand** chart. It displays the fastest-moving items over the last 90 days. Because our charts are fully interactive, clicking any bar—for example, 'Barefaced Matte Sunscreen'—transfers us directly to its detailed forecasting curve!*  
>  
> *Now, let us examine the master catalog in **Inventory Management**."*

---

## 4.2 Master Inventory Catalog (`products.php`)

### Visual Layout & Interface Elements
- **Master Toolbar:**
  - **Search Input (`#product-search-input`):** Live keyword filtering by product name, category, SKU, or supplier.
  - **Search & Reset Filter Buttons:** One-click search submission and filter clearing.
  - **Category Dropdown Filter:** Filters list by any store category.
  - **Stock Status Filter:** `All Stock`, `Low Stock`, `Out of Stock`, `In Stock`.
  - **View Mode Switcher:**  
    - **Grid Card View (`#btn-view-grid`):** Modern eCommerce-style cards with packaging images, status badges, price, stock meter, and quick edit actions.
    - **Table View (`#btn-view-table`):** High-density tabular view with full sorting and multi-column metadata.
  - **"Add Product" Button (`openAddModal()`):** Launches the master product creation modal.
  - **"View Archived" Button:** Toggles between active catalog items and archived products (soft-delete).
- **Interactive Summary Cards (4 Cards):**
  - Clicking any summary card (e.g., Low Stock or Out of Stock) immediately applies that filter to the inventory grid!

### Presenter Walkthrough

#### Step 4.2: Demonstrating Catalog Exploration & Filtering
> **Action (Do):** Click **"Inventory"** in the sidebar navigation.
>
> **Dialogue (Say):**  
> *"Welcome to the master Inventory Management module. Here, the store owner maintains the complete catalog of physical goods.*  
>  
> *Notice the flexible design: users can toggle between a visual **Card Grid View**, which displays product images and visual badges ideal for touchscreens, and a high-density **Table View**, which presents tabular data suited for office audits.*  
>  
> *Let us demonstrate our interactive filtering:*  
> *- If I click the amber **Low Stock** card at the top, the catalog instantly filters to only show products requiring reorders.*  
> *- If I select a category like **'Beverages'**, the table displays drinks.*  
> *- If I search for 'Sunscreen', it isolates that item instantly.*  
>  
> *Now, let us demonstrate one of the most innovative features of IntelliWare: Adding a new product using our **AI Camera Packaging Scanner**!"*

---

## 4.3 AI Camera Packaging Scanner & Product Creation Modal

### Visual Layout & Interface Elements
- **Master Modal (`#add-modal-overlay`):**
  - **Section 1: AI Vision & Packaging Identification Zone (`#ai-capture-block`):**
    - **Status Indicator Dot & Text:** Live status (e.g., *"Take photo or upload packaging — auto-fills product details"*).
    - **"Upload Image" Button (`#ai-upload-btn`):** Allows selecting a packaging photo from disk.
    - **"Take Photo" Button (`#ai-capture-btn`):** Opens live device camera.
    - **Camera Viewfinder (`#ai-camera-area`):** Live video feed with visual targeting reticle (`.tm-viewfinder-ring`).
    - **Shutter Bar (`#ai-camera-bottom-bar`):** Features **"Capture & Auto-fill"** (`#ai-capture-snap-btn`) and **"Cancel"**.
    - **OCR Text Detection Box (`#add-ocr-raw-wrap`):** Accordion revealing exact text recognized from the product packaging via Tesseract OCR.
    - **Smart Suggestion Chips (`#add-product-name-chips`):** Clickable pill tags suggesting detected product names.
  - **Section 2: Form Input Hierarchies:**
    - **1. General Details:** Product Name (required), Category dropdown, Supplier / Brand.
    - **2. Stock & Storage:** Opening Stock Quantity (required), Unit of Measure (UoM: pieces, packs, boxes, cans, bottles, bars, etc.), Storage Location / Shelf (e.g., `Shelf C1 - Cosmetics`).
    - **3. Pricing & Inventory Control:** Unit Price in ₱ (required), Reorder Point (with smart auto-suggest based on category).
    - **4. Perishability & Expiry Control:** Perishable toggle checkbox, Shelf Life in days.
  - **Modal Footer:** Cancel button and **"Save Product"** button.

### Presenter Walkthrough

#### Step 4.3: Demonstrating AI Product Auto-Filling
> **Action (Do):** Click the green **"Add Product"** button. The modal opens.
>
> **Dialogue (Say):**  
> *"In traditional inventory software, registering a new product requires tedious manual typing of names, suppliers, units, and categories. At Shea & Nicole's General Merchandise Store, store staff often register dozens of items during supplier deliveries.*  
>  
> *To eliminate encoding fatigue and spelling errors, IntelliWare integrates an embedded **AI Vision & OCR Scanner** directly in the browser:*  
> *1. When staff click **'Take Photo'**, the browser activates the camera.*  
> *2. Staff hold the product packaging up to the lens and click **'Capture & Auto-fill'** (or upload a packaging photo).*  
> *3. Under the hood, IntelliWare executes a dual-pipeline AI process:*  
> *   - **Google Teachable Machine / TensorFlow.js** classifies the visual appearance and packaging shape.*  
> *   - **Tesseract OCR Engine** scans the text on the box or label, extracting brand names, product variants, and package net weights.*  
> *4. Watch the form fields below: The **Product Name**, **Supplier Brand**, **Category**, and **Storage Shelf** are automatically populated! Suggestion chips appear so the user can refine the exact variant with one click.*  
> *5. Furthermore, based on the selected Category, IntelliWare's rule-based classification automatically suggests a recommended **Reorder Point Threshold**!*  
>  
> *Let us verify the fields: Product Name is set to 'Barefaced Sunkissed Tinted Sunscreen 50g', Category is 'Personal Care', Shelf is 'Shelf C3', Unit Price is ₱299.00, Opening Quantity is 50 units, and Reorder Point is set to 15. We click **'Save Product'**."*
>
> **Action (Do):** Click **"Save Product"**. The modal closes, and the green toast confirms: *"Product created successfully."* The new product appears immediately in the catalog!

---

## 4.4 Data Insights, Analytics & Philippine Seasonal Engine (`insights.php`)

### Visual Layout & Interface Elements
- **Hero Card:** Active status, AI-powered engine badge, and system description.
- **Top Metric Cards (4 Filter Cards):**
  - **Total Products:** Active catalog SKUs.
  - **Low & Out of Stock:** Immediate restock candidates.
  - **Fast Moving:** High turnover products.
  - **Expiring Soon:** Batches nearing expiration within 30 days.
- **5 Analytics Tabs (`.tab-container`):**
  1. **Product Movement Analysis:** Complete table calculating **Monthly Sales**, **Daily Sales Velocity** ($\text{Sales} / 30$), **Days of Supply (DOS)** ($\text{Current Stock} / \text{Velocity}$), **Movement Classification** (`Fast Moving`, `Medium Moving`, `Slow Moving`, `Non Moving`), and **Stock Status** (`Low Stock`, `Normal`, `Overstocked`).
  2. **Category Performance:** Product counts, total stock, average stock, and Chart.js Sales Distribution chart.
  3. **Sales Trends:** Historical sales volume across days lookback and sales velocity chart.
  4. **Supplier Analysis:** Supplier reliability scorecard, delivery counts, low-stock frequency, and vendor status (`Good`, `Needs Attention`, `Poor`).
  5. **Expiry Risk Analysis:** Tracks perishable goods, days until expiration, and risk classification (`Critical`, `High`, `Medium`, `Low`).
- **Demand Forecasting & Smart Restock Widget (`#forecastWidget`):**
  - **Sync Status Pill (`#forecastSyncStatusPill`):** Displays live connection to warehouse inventory.
  - **"Refresh Forecast" Button (`#btnRefreshSmartRestock`):** Executes live reconciliation using Python/ML analytics.
  - **Prescriptive Replenishment Table:**
    - **Top In-Demand Product / Category:** Displays item details and stock pill.
    - **Three-Tier Insight Hierarchy:**
      - **Context (Descriptive Analytics):** Explains current inventory and recent sales patterns.
      - **Forecast (Predictive Analytics):** Projects next month's sales demand and expected volume lift.
      - **Action (Prescriptive Analytics):** Delivers clear instructions: *"Restock X units immediately"*, *"Current stock optimal"*, or *"Pause reorders"*.
    - **"Check Stock" Action Button:** Automatically transfers product data into a new purchase/receiving order!
  - **Charts:** 30-Day Sales Forecast curve, Demand Heatmap, Seasonal Demand Multipliers, and Forecast Accuracy Percentage gauge.
- **Philippine Seasonal Market Pulse Floating Action Button (`#phSeasonalFab`):**
  - Glowing bottom-right glassmorphic button with Philippine sun icon.
  - Launches the master **Philippine Seasonal Recommendations Modal** (`ph_seasonal_modal.php`).

### Presenter Walkthrough

#### Step 4.4A: Demonstrating Data Mining & Rule-Based Classification
> **Action (Do):** Click **"Insights"** in the sidebar navigation.
>
> **Dialogue (Say):**  
> *"Now we arrive at the intellectual core of the IntelliWare Capstone research: the Smart Insights & Analytics module.*  
>  
> *Small general merchandise retailers fail because they rely on guesswork. IntelliWare replaces guesswork with data analytics across three progressive analytical tiers: Descriptive, Predictive, and Prescriptive.*  
>  
> *Look at Tab 1: **Product Movement Analysis**. For every single SKU in Shea & Nicole's store, the system automatically computes:*  
> *1. **Daily Sales Velocity:** The average number of units sold per day.*  
> *2. **Days of Supply (DOS):** Exactly how many days our current physical stock will last before a complete stockout occurs.*  
> *3. **Rule-Based Classification:** Products are automatically sorted into four standard retail movement classes: **Fast-Moving**, **Medium-Moving**, **Slow-Moving**, and **Non-Moving**.*  
>  
> *If an item's Days of Supply drops below the lead time required by suppliers, the system triggers automated replenishment warnings. Conversely, if an item has over 90 Days of Supply with zero sales, it is flagged as **Overstocked**, alerting management to stop ordering and prevent tied-up capital!*  
>  
> *Now let us look at the other tabs: **Category Performance** shows which departments generate the bulk of revenue; **Supplier Analysis** scores our vendors on stock fulfillment; and **Expiry Risk Alerts** highlights items about to expire so staff can discount them before loss occurs."*

#### Step 4.4B: Demonstrating Machine Learning Demand Forecasting
> **Action (Do):** Scroll down to the **Demand Forecasting & Smart Restock** section.
>
> **Dialogue (Say):**  
> *"Further down, we see our **Demand Forecasting & Smart Restock Engine**. Here, historical sales data is processed through machine learning time-series models to predict consumer demand for the upcoming month.*  
>  
> *Notice our structured insight cards:*  
> *- **Context (Descriptive):** Tells us what happened.*  
> *- **Forecast (Predictive):** Tells us what will happen—for instance, projecting an 85% surge in beverage demand.*  
> *- **Action (Prescriptive):** Gives management the exact decision support required—for example: 'Order 40 additional cases before the 1st of the month'.*  
>  
> *Notice the **'Check Stock'** button next to each recommendation. If management agrees with the AI prescription, clicking 'Check Stock' immediately bridges the gap between analytics and action, opening the transaction screen pre-filtered with that item!"*

#### Step 4.4C: Demonstrating Philippine Seasonal Market Pulse
> **Action (Do):** Click the glowing **Philippine Seasonal FAB** at the bottom-right of the screen (`#phSeasonalFab`). The master modal opens.
>
> **Dialogue (Say):**  
> *"A unique innovation in IntelliWare is our **Philippine Seasonal & Cultural Market Engine**, tailored specifically to retail dynamics in the Philippines.*  
>  
> *Retail purchasing habits in Pasig City are heavily driven by cultural seasons, climate shifts, and local calendar events. In this modal, management can explore seasonal demand profiles across Month, Quarter, Semester, and Full Year:*  
> *- **Summer Months (March–May):** Temperatures rise; demand for cold beverages, ice, and powdered drinks surges.*  
> *- **Holy Week (March/April):** Meat consumption drops; demand for canned tuna and sardines peaks.*  
> *- **Back-to-School (June):** School supplies, notebooks, pens, and snack packs experience massive turnover.*  
> *- **Rainy & Typhoon Season (July–August):** Instant noodles, canned meats, coffee, and emergency supplies surge.*  
> *- **Undas / All Saints' Day (October–November):** Candles, matches, canned goods, and bakery items increase.*  
> *- **The 'Ber' Months & Christmas (September–December):** The longest holiday season in the world; party snacks, spaghetti pasta, canned ham, condensed milk, and all-purpose cream experience peak annual volume.*  
> *- **Payday Weekends (15th and 30th):** Bulk household staple restocking.*  
>  
> *IntelliWare cross-references Shea & Nicole's live inventory against these Philippine seasonal drivers, ensuring the store builds safety stock weeks BEFORE supplier shortages occur!"*
>
> **Action (Do):** Close the seasonal modal by clicking the `&times;` close button.

---

## 4.5 Executive Reports Generation (`reports.php`)

### Visual Layout & Interface Elements
- **Page Header & Controls:**
  - Date Range pickers (`Date From` and `Date To`).
  - Category filter dropdown.
  - Stock Status filter cards: `All Products`, `Well Stocked`, `Low Stock`, `Out of Stock`.
  - **Export Action Buttons:**
    - **"Download PDF" Button (`downloadPDF()`):** Generates a client-ready, multi-page vector PDF report via jsPDF & AutoTable.
    - **"Download Excel" Button (`downloadExcel()`):** Exports raw data into Microsoft Excel `.csv`/`.xlsx` format.
    - **"Print" Feature:** Browser-native print stylesheet removing UI sidebars and rendering formal print headers.
- **Two Report View Tabs:**
  - **Interactive 3-Column Explorer (`#tabExplorerBtn`):**
    - Column 1: Store Categories list with product counts.
    - Column 2: Products belonging to the selected category.
    - Column 3: Live Product Detail card showing stock, reorder point, unit price, total value, and recent movements.
  - **Detailed Audit Table View (`#tabTableBtn`):** Full paginated master inventory valuation table.

### Presenter Walkthrough

#### Step 4.5: Generating Formal Reports
> **Action (Do):** Click **"Reports"** in the sidebar.
>
> **Dialogue (Say):**  
> *"For formal business meetings, bank audits, tax documentation, or supplier reconciliations, management requires structured documentation. The Reports module provides two viewing formats and instant digital exports.*  
>  
> *Notice our **Interactive 3-Column Explorer**: On the left are our categories; selecting 'Canned Goods' populates the middle column with all canned items; selecting an item opens the right column with its complete stock history and valuation.*  
>  
> *If we switch to the **Audit Table View**, we see our complete warehouse master inventory sheet.*  
>  
> *Now, let us demonstrate report generation:*  
> *- Clicking the **'Download PDF'** button immediately compiles the filtered data into a formal, branded PDF document complete with store credentials, timestamps, and formatted tables.*  
> *- Clicking **'Download Excel'** exports the data for spreadsheet accounting.*  
> *- Furthermore, pressing Ctrl+P triggers our custom print stylesheet, stripping away web navigation and generating a clean physical report ready for signing.*  
>  
> *Now, let us examine System Administration: **User Management**."*

---

## 4.6 System Administration: User Management (`users.php`)

### Visual Layout & Interface Elements
- **User KPI Cards:** Active Accounts, Administrators, Managers, and Staff members.
- **"Add User" Button:** Launches the user creation modal.
- **Active Accounts Table:**
  - Avatar initials badge.
  - Full Name, Username (`@username`), and Email Address.
  - Role Badge: `Admin` (Purple), `Manager` (Blue), `Staff` (Green).
  - Email Notification toggle status.
  - Action Buttons:
    - **Edit User (Pencil):** Modify full name, email, or role.
    - **Reset Password (Key):** Administrator-override password update.
    - **Delete / Deactivate User (Trash):** Safeguarded account deletion with safety checks preventing self-deletion.

### Presenter Walkthrough

#### Step 4.6: Demonstrating User Account Administration
> **Action (Do):** Click **"Users"** in the sidebar.
>
> **Dialogue (Say):**  
> *"As an Administrator, managing staff permissions is straightforward. In the User Management module, the store owner controls employee access.*  
>  
> *When a new warehouse clerk is hired:*  
> *1. The Admin clicks **'Add User'**.*  
> *2. Enters their full name, desired username, email, and assigns their role: Staff, Manager, or Admin.*  
> *3. Enters an initial password.*  
>  
> *Notice our safety controls: If an administrator attempts to delete their own currently active account, the system rejects the operation to prevent administrative lockout. If an employee forgets their password, the Admin can click the Key icon to execute an immediate security reset."*

---

## 4.7 System Administration: Settings & Maintenance (`settings.php`)

### Visual Layout & Interface Elements
- **Comprehensive Tabbed Configuration Panel:**
  1. **Profile Tab (`#profile`):** Update personal display name, username, email, and password. (Accessible by all roles).
  2. **General Facility Tab (`#general`):** Store Name (*Shea & Nicole's General Merchandise Store*), Currency Symbol (`₱` / `PHP`), VAT Rate (`12%`), Date Format, and Default Warehouse Low Stock / Overstock thresholds.
  3. **Notification Channels Tab (`#notifications`):**
     - **Email Notifications (SMTP):** SMTP Host, Port, Username, Password, Sender Name, and "Send Test Email" diagnostic trigger.
     - **SMS Notifications:** SMS Gateway API integration (Twilio / PhilSMS) for sending SMS alerts directly to the owner's mobile phone when items hit critical zero.
     - **In-App Thresholds:** Toggles for on-screen notification bells.
  4. **AI Vision & Packaging Scanner Tab (`#vision`):** Confidence threshold sliders, model status indicators, and Tesseract OCR language configurations.
  5. **Demand Forecasting & ML Engine Tab (`#forecasting`):** Status of trained Python machine learning models, training hyperparameters, and the **"Train Machine Learning Model"** execution button.
  6. **Database Maintenance & SQL Backup Tab (`#maintenance`):**
     - **"Download Database Backup" Button (`backup_trigger`):** Generates and downloads a complete, uncorrupted `.sql` database backup dump.
     - **Database Restore:** SQL dump upload utility.
     - **System Cache Cleanup:** Temporary file clearing.

### Presenter Walkthrough

#### Step 4.7: Demonstrating System Configuration & Database Backup
> **Action (Do):** Click **"Settings"** in the sidebar.
>
> **Dialogue (Say):**  
> *"Finally, we explore System Settings & Maintenance. This is the control room of IntelliWare:*  
> *- In the **General Tab**, we configure store parameters such as our low-stock warning threshold, store currency, and VAT rates.*  
> *- In the **Notification Channels Tab**, the owner configures email and SMS alerts. Whenever high-priority items hit critical levels, IntelliWare automatically dispatches an alert to Ms. Espiritu's phone and email.*  
> *- In the **AI Vision Tab**, we adjust scanner sensitivity.*  
> *- In the **Demand Forecasting Tab**, we can re-train our Python machine learning models as new transaction records accumulate over time.*  
>  
> *Crucially, let us look at the **Database Maintenance Tab**. Data safety is essential for any enterprise. By clicking **'Download Database Backup'**, watch as IntelliWare instantly compiles all catalog tables, transaction ledgers, user accounts, and audit histories into a timestamped `.sql` file, downloaded directly to the local machine.*  
>  
> *Should hardware fail, the entire system can be restored in seconds with zero data loss."*
>
> **Action (Do):** Click the **"Download Database Backup"** button to show the downloaded `.sql` file.

---

# PART 5: Demonstration Conclusion & Summary

### Presenter Closing Remarks

> **Action (Do):** Return to the main Admin Dashboard (`dash_admin.php`).
>
> **Dialogue (Say):**  
> *"To conclude our demonstration, let us summarize how IntelliWare successfully addresses each research objective established for Shea and Nicole's General Merchandise Store:*  
>  
> *1. **Elimination of Manual Recording Errors:** By replacing handwritten logbooks with automated Inbound/Outbound POS terminals and AI camera packaging recognition, inventory recording is now instantaneous and error-free.*  
> *2. **Real-Time Inventory Visibility:** Store staff and management always know exact on-hand stock levels, valuation, and shelf locations without physical stock counting.*  
> *3. **Intelligent Decision Support:** Through Rule-Based Classification and Machine Learning Demand Forecasting, the store owner knows precisely what products are fast-moving, when stockouts will occur, and how many units to reorder.*  
> *4. **Localized Cultural Intelligence:** The Philippine Seasonal Engine ensures Shea & Nicole's store anticipates holiday, weather, and payday surges ahead of time.*  
> *5. **Robust Security & Governance:** Role-Based Access Control and a System Audit Trail protect the business against shrinkage, unauthorized adjustments, and data loss.*  
>  
> *IntelliWare transforms a traditional neighborhood retail store into an intelligent, data-driven enterprise. Thank you very much, and we are now ready to address your questions and feedback."*

---

# PART 6: Panelist Q&A Preparation & Technical Defense

To assist the presenters during the thesis or capstone defense, here are the most likely panel questions paired with model answers:

#### Q1: "Why did you implement Rule-Based Classification instead of relying strictly on Machine Learning for stock status?"
> **Model Answer:**  
> *"Retail stock control thresholds such as 'Low Stock' or 'Critical Stock' are fundamentally defined by deterministic business rules—specifically, whether Current Stock is less than or equal to the Reorder Point ($\text{Stock} \le \text{ROP}$). Machine learning is probabilistic and requires millions of training iterations to predict continuous values; using it for binary thresholds would introduce unnecessary uncertainty. Therefore, we use **Rule-Based Classification** for fixed inventory conditions (Low Stock, Overstock, DOS limits) where 100% mathematical consistency is required, and we utilize **Machine Learning (Time-Series & Regression)** where it excels: forecasting future customer demand based on historical trends."*

#### Q2: "How does the AI Camera Packaging Scanner work without specialized barcode hardware?"
> **Model Answer:**  
> *"Dedicated laser barcode scanners require expensive hardware and fail if barcodes are smudged, torn, or missing. IntelliWare leverages web-native computer vision using two complementary client-side libraries: **TensorFlow.js** with a model trained on Google Teachable Machine for visual product recognition, and **Tesseract.js OCR** for optical character recognition of package text. This allows any standard smartphone, tablet, or web camera to identify products directly through the browser, democratizing automation for small retail enterprises."*

#### Q3: "What prevents transaction race conditions when multiple staff members receive and release stock simultaneously?"
> **Model Answer:**  
> *"All stock movement executions in our `StockMovementService` utilize MySQL InnoDB transactional safeguards with ACID compliance. Stock updates are executed using atomic SQL queries and database transactions (`BEGIN TRANSACTION ... COMMIT`), locking the affected row until the operation finishes. If an operation fails, the entire transaction rolls back, preventing negative balances or orphaned records."*

#### Q4: "How does the Philippine Seasonal Market Pulse integrate with store inventory?"
> **Model Answer:**  
> *"Our `PhilippineSeasonalService` maps specific calendar months and quarters to documented cultural drivers in the Philippines (e.g., Holy Week canned tuna surge, summer beverage peak, 'Ber' months Noche Buena demand). It queries active store products in corresponding categories and compares current on-hand stock with projected seasonal demand multipliers, generating prescriptive replenishment alerts directly on the manager's dashboard."*
