# IntelliWare - System Demo Script

This document provides a step-by-step guide for demonstrating the IntelliWare Warehouse Inventory Management System. It covers how to launch the application, navigate through the main modules, and explain the key features on each page.

---

## 1. Starting the System (How to Operate & Run)

**Preparation:**
1. Open the **XAMPP Control Panel** on your laptop.
2. Start the **Apache** and **MySQL** modules. Ensure both are highlighted in green, indicating they are running successfully.

**Accessing the Application:**
1. Open your preferred web browser (e.g., Google Chrome, Microsoft Edge).
2. In the address bar, type the following path and press Enter:
   `http://localhost/IntelliWare/`
   *(Note: This will automatically redirect to the login page.)*

---

## 2. The Login Page (`signin.php`)

**What is this page?**
This is the secure entry point of the IntelliWare system. It ensures that only authorized personnel can access the application.

**Content & Talking Points:**
*   **Role-Based Access Control (RBAC):** Explain that the system recognizes who is logging in based on their credentials. It will redirect users to different dashboards tailored to their specific roles (Admin, Manager, Staff).
*   **Action:** Log in using a sample account to proceed with the demo. (It is recommended to start the demo with an **Admin** or **Manager** account to show full capabilities).

---

## 3. The Dashboard (Home Page)

**What is this page?**
The Dashboard is the central hub. Its content changes dynamically based on the user's role.

*   **Admin Dashboard (`dash_admin.php`):**
    *   **Content:** Provides a high-level overview of the entire system. You will see summary metrics like total products, total users, low stock alerts, and recent system activities.
    *   **Talking Point:** "As an Admin, I have a bird's-eye view of both our inventory health and system usage."
*   **Manager Dashboard (`dash_manager.php`):**
    *   **Content:** Focuses on operational metrics. It highlights pending tasks, inventory status, and quick links to reports.
    *   **Talking Point:** "Managers can quickly spot bottlenecks or items needing immediate restocking."
*   **Staff Dashboard (`dash_staff.php`):**
    *   **Content:** Streamlined for daily operations. Features quick actions for receiving or releasing stock.
    *   **Talking Point:** "The interface for staff is designed for efficiency, prioritizing day-to-day transactions."

---

## 4. Inventory Management (`inventory_home.php` & `products.php`)

**What is this page?**
This is the core module where all physical goods are tracked and managed.

**Content & Talking Points:**
*   **Product List:** Show the master list of all items in the warehouse. Explain the columns (e.g., SKU/Barcode, Name, Category, Quantity, Status).
*   **Search & Filter:** Demonstrate how easy it is to find a specific product using the search bar or category filters.
*   **Stock Status:** Highlight how the system uses color-coded statuses (e.g., In Stock, Low Stock, Out of Stock) so users can visually identify inventory levels at a glance.
*   **Adding/Editing:** (Optional during demo) Show the form used to add a new product or update existing details.

---

## 5. Transactions & History (`transactions.php` & `transaction_logs.php`)

**What is this page?**
This section records every movement of goods in and out of the warehouse, ensuring complete traceability.

**Content & Talking Points:**
*   **Receiving/Releasing:** Explain the process of logging incoming shipments (Receiving) and outgoing orders (Releasing).
*   **Audit Trail:** Navigate to the Transaction Logs. Emphasize that the system records *who* moved *what*, *when*, and *how much*. This is crucial for accountability and auditing.

---

## 6. Data Insights & Analytics (`insights.php` & `data_mining_insights.php`)

**What is this page?**
This is the advanced analytical engine of IntelliWare. It goes beyond simple tracking to provide intelligent business insights.

**Content & Talking Points:**
*   **Data Mining Engine:** Explain that the system analyzes historical transaction data.
*   **Visualizations:** Show the charts and graphs. Point out trends, such as fast-moving vs. slow-moving items, or peak transaction periods.
*   **Predictive Value:** "By leveraging these insights, management can make informed decisions on purchasing, reduce overstocking, and prevent stockouts."

---

## 7. Reports (`reports.php`)

**What is this page?**
A dedicated module for generating structured data exports for management or external stakeholders.

**Content & Talking Points:**
*   **Customization:** Show how users can select date ranges or specific categories to generate customized reports (e.g., Monthly Inventory Valuation, Daily Movement Report).
*   **Exporting:** Mention that these reports can typically be printed or exported (if applicable) for record-keeping.

---

## 8. System Administration (`users.php` & `settings.php`)

**What is this page?**
*(Admin only)* This is where the system is configured and user access is managed.

**Content & Talking Points:**
*   **User Management (`users.php`):** Show how an Admin can add new employees, assign roles (Staff, Manager), or deactivate accounts.
*   **System Settings (`settings.php`):** Briefly mention that global parameters, such as the low-stock threshold or company details, can be adjusted here to tailor the system to the business's evolving needs.

---

## Recommended Demo Flow (Scenario)

To ensure a smooth presentation, follow this sequence:

1. **Start as Admin:** Log in (`signin.php`) using an Administrator account to show the full capability of the system.
2. **Dashboard Overview:** Land on `dash_admin.php`. Briefly point out the key metrics and alerts (e.g., low stock).
3. **Inventory Check:** Navigate to `inventory_home.php` or `products.php`. Show the product list, use the search bar to find a specific item, and point out its current stock level.
4. **Simulate a Transaction:** 
   - Go to `transactions.php`.
   - Perform a "Receive" action to add stock to the item you just searched for.
   - Perform a "Release" action to simulate an order going out.
5. **Verify the Action:** Navigate to `transaction_logs.php` to show that the receiving and releasing actions were instantly recorded with the user's name and timestamp.
6. **Show Insights:** Go to `insights.php`. Show how the recent transactions contribute to the data visualizations and trends.
7. **Generate a Report:** End the core demo by going to `reports.php` and generating a sample report of the day's activities.

---

## Demo Closing

**Summary:** Conclude by highlighting the main value propositions of IntelliWare:
1.  **Ease of Use:** Intuitive, role-based interfaces.
2.  **Accuracy:** Real-time tracking and comprehensive audit logs.
3.  **Intelligence:** Advanced analytics to drive smarter business decisions.
