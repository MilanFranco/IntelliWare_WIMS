# IntelliWare UI Mockups (Wireframes)

This document outlines the mockups for IntelliWare: A Warehouse Inventory Management System Utilizing Data Mining and Rule-Based Classification. These mockups illustrate role-based dashboards and core interfaces. All screens share a unified blue/gray/white theme and consistent layout patterns.

## 1. Login Screen
- Fields: Username, Password
- Actions: Login, Clear
- Branding: System title and logo
- Behavior: On submit, validate credentials and route to role dashboard; display inline error for invalid credentials.

## 2. Dashboards (Role-Based)
- Common: Top nav (Home, Inventory, Reports, Alerts, Settings), KPI strip (Total Stock, Active Alerts, Classifications), charts area (stock trends, movements)
- a) Warehouse Staff Dashboard: Stock summaries, quick actions for recording inventory, low/overstock notifications
- b) Manager Dashboard: Real-time analytics, charts, summary reports from data mining/classification
- c) Administrator Dashboard: Configuration, user activity, classification rule management

## 3. Inventory Management
- Data table: ID, Name, Category, Quantity, Status, Last Update
- Actions: Add, Edit, Delete, Update quantity
- Utilities: Search and filter controls

## 4. Stock Entry Form
- Fields: Item Name, Description, Quantity, Category, Date, Supplier, Status (Available/Low Stock/Overstock)
- Actions: Save, Cancel
- Validation: Required fields and type checks

## 5. Reports and Analytics
- Controls: Date range, Report type (daily/weekly/monthly)
- Outputs: Summary table, line/bar charts (turnover, demand frequency, category distribution)
- Export: Print/PDF

## 6. Alert Notifications
- List grouped by category (low stock, overstock, expiring)
- Indicators: Red (critical), Yellow (warning), Green (normal)
- Actions: Acknowledge, Resolve

## 7. Admin Panel
- Functions: User accounts, access control, rule parameters (reorder levels, thresholds)
- Tabs: System logs, backup/restore

## 8. Design Principles
- Consistent blue/gray/white palette, sans-serif typography
- Responsive layouts, consistent components (buttons, tables, nav)
- Clear validation prompts and visual feedback to reduce errors

## HTML Wireframes
Static HTML mockups are available in the `mockups/` directory:

- `mockups/index.html` – entry linking all screens
- `mockups/login.html`
- `mockups/dashboard_staff.html`
- `mockups/dashboard_manager.html`
- `mockups/dashboard_admin.html`
- `mockups/inventory.html`
- `mockups/stock_entry.html`
- `mockups/reports.html`
- `mockups/alerts.html`
- `mockups/admin_panel.html`

All mockups use a shared stylesheet at `mockups/styles.css` for a unified visual language.


