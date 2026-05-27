# Mr. Tarpz Printing Shop Management System

A customized and simple-beginner, web-based Point of Sale (POS), Inventory Control, Order Tracking, Payments Ledger, and Expense Management system designed specifically for tarpaulin, sticker, and layout digital printing establishments.

---

## 🚀 Key Modules & Capabilities

- **Unified Dashboard Analytics:** Real-time visual cards tracking total product counts, low-stock alerts, daily gross sales revenue (`CURDATE()`), and historical 6-month transaction timelines rendered dynamically with Chart.js.
- **Dynamic Order Queue Pipeline:** Seamless workflow managing walk-in, online, or regular customer profiles. Tracks order statuses (`pending`, `in_progress`, `completed`, `delivered`, `cancelled`) linked synchronously with individual payment checkpoints (`paid`, `unpaid`, `partial`).
- **Comprehensive Stock Control System:** Dual-type catalog tracking covering **Raw Materials** (e.g., tarpaulin rolls, ink refills) and **Finished Products**. Features smart reorder-level alerts that trigger automatically inside the control panel.
- **Expense Logging & Ledger Accountancy:** Tracks operating cost lines, overhead expenditures, material refills, and hardware repairs with decoupled asynchronous AJAX handlers.
- **Automated Financial Reporting (Python Hybrid Engine):** Generates analytical financial statements compiled on-demand. The PHP tier exports database summaries into an intermediate data wrapper (`temp_data.json`), then hands rendering tasks over to an internal Python workbook thread. The results are beautifully formatted, auto-fitted Excel spreadsheets (`.xlsx`) that include automatic calculation formulas.
- **Integrated Payments Ledger:** Tracks historical customer balances, references, and payment instruments (💵 Cash, 📱 GCash, 🏦 Bank Transfer, 💳 Credit).

---

## 🛠️ Tech Stack & Dependencies

- **Frontend Core:** HTML5, CSS3 Modern Design System (with custom `:root` utility color variables), Native JavaScript (ES6+), FontAwesome Icons v6.0.
- **Asynchronous Engine:** jQuery v3.6.0 (powering all CRUD background pipelines).
- **Visualization Library:** Chart.js (via CDN).
- **Backend Architecture:** PHP 8.x (Object-Oriented + Native MySQLi extensions).
- **Relational Database Management:** MySQL / MariaDB (InnoDB Engines with transactional Foreign Key constraints).
- **Auxiliary Document Compiler:** Python 3.x (utilizing the `openpyxl` library).

---

## 📂 System File Architecture

```text
├── css/
│   └── style.css             # Unified UI layout engine, modern CSS variables, and fluid animations
├── js/
│   ├── dashboard.js          # Chart controls, async metrics, and recent order tickers
│   ├── products.js           # AJAX operations for product creation, filtering, and editing
│   ├── inventory.js          # Dynamic inventory ledger updates, transactions, and filters
│   ├── orders.js             # Interactive item builder and core order validation logic
│   ├── expenses.js           # Asynchronous expenditure logs and date queries
│   └── payments.js           # Transaction ledger and reference code filters
├── config.php                # Session configs, DB constants, input sanitization, and currency variables
├── index.php                 # Secure login gateway, authorization loops, and register modals
├── register.php              # Secure backend user initialization and username availability validation
├── logout.php                # Complete session destruction and secure redirect sequence
├── dashboard.php             # Core landing view containing analytics and live status charts
├── customers.php             # Customer relation directory and CRM user management listing
├── products.php              # Catalog database manager (handles Finished vs. Raw materials metadata)
├── inventory.php             # Raw materials storage, stock transactions, and adjustments controller
├── orders.php                # Core point-of-sale layout and order creation pipeline
├── payments.php              # Customer financial transaction log and balance tracking records
├── expenses.php              # Corporate business cost registers and overhead logs
├── reports.php               # Spreadsheet pipeline bridge & physical asset log compiler
├── settings.php              # Configuration dashboard for shop information and metadata
├── sidebar.php               # Reusable, auto-highlighting DOM-state navigation shell template
├── convert_to_excel.py       # Python script handling document generation, auto-fitment, and styling
└── mrtarpz_printing.sql      # Database schema blueprint, indexing rules, constraints, and seed records
