# Smart Stock Management System (MRP II) - Final Design Document

**Version:** 5.9
**Date:** 2026-01-08
**Status:** Production Ready Design
**System Type:** Material Requirements Planning II (MRP II) - Modular Architecture

---

## 📋 Table of Contents

1. [System Overview](#1-system-overview)
2. [Modular Architecture](#2-modular-architecture)
3. [Technology Stack](#3-technology-stack)
4. [Key Features](#4-key-features)
5. [Database Architecture](#5-database-architecture)
6. [Core Business Models](#6-core-business-models)
7. [Internationalization](#7-internationalization)
8. [Support Systems](#8-support-systems)
9. [Search & Performance](#9-search--performance)
10. [API Structure](#10-api-structure)
11. [Security & Authorization](#11-security--authorization)
12. [Architecture Best Practices](#12-architecture-best-practices)
13. [Implementation Phases](#13-implementation-phases)
14. [External Integrations](#14-external-integrations)

---

## 1. System Overview

### 1.1 Purpose
An enterprise-grade **Manufacturing Resource Planning (MRP II)** system with comprehensive inventory management, production planning, procurement, sales order management, capacity planning, and real-time analytics.

**MRP II Components (Implemented):**
- **Material Requirements Planning (MRP)**: Automated calculation of material needs based on demand, inventory levels, and production schedules
- **Capacity Requirements Planning (CRP)**: Capacity analysis and planning for work centers and production resources
- **Master Production Schedule (MPS)**: Production planning through Work Orders and BOM management
- **Shop Floor Control**: Work Order management, operation tracking, and material consumption
- **Inventory Management**: Real-time stock tracking, movements, reservations, and negative stock handling
- **Procurement Planning**: Purchase order recommendations and supplier management
- **Sales & Operations Planning**: Integration of sales orders with production planning

**MRP II Components (Not Implemented - Future Roadmap):**
- **Financial Planning & Cost Management**: Comprehensive cost accounting, financial planning, and budget management
  - **Note**: This is a conscious design decision. The current focus is on planning and inventory management rather than full financial integration. Basic cost tracking (standard cost, average cost, work center costs) exists for operational purposes, but comprehensive financial planning is planned for future releases.

### 1.2 System Characteristics
- **Multi-tenant SaaS Architecture**: Built with multi-tenancy in mind, ready for SaaS deployment
- **Multi-language UI**: Complete interface translation via frontend i18n
- **Multi-currency**: Support for multiple currencies with exchange rates
- **Flexible Architecture**: Dynamic product attributes based on product types
- **Scalable**: Designed for growth from small business to enterprise
- **Modern Stack**: Laravel 12, PostgreSQL, Redis, Elasticsearch
- **Data Isolation**: Automatic company-level data scoping ensures complete tenant isolation

### 1.3 Key Differentiators
- ✅ **Multi-language UI**: Frontend translations (react-i18next / vue-i18n)
- ✅ **Single Language Data**: User-entered data stored in user's language
- ✅ **Multi-currency Pricing**: Automatic currency conversion, tiered pricing
- ✅ **Dynamic Attributes**: Product type-specific attributes with validation
- ✅ **MRP II Logic**: Complete Manufacturing Resource Planning with MRP, CRP, and Shop Floor Control
- ✅ **BOM Management**: Multi-level product structures
- ✅ **Advanced Search**: Elasticsearch with fuzzy matching
- ✅ **Real-time Performance**: Redis caching layer
- ✅ **Traceability**: Full lot/batch/serial tracking

---

## 2. Modular Architecture

### 2.1 Architecture Overview

SmartStockManagement uses a **modular MRP II architecture** with feature flags for optional modules:

```
┌─────────────────────────────────────────────────────────────────────┐
│                        SmartStockManagement                          │
│                         (MRP II System)                              │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌──────────────┐   ┌──────────────────┐   ┌──────────────────┐    │
│  │  CORE        │   │  PROCUREMENT     │   │  MANUFACTURING   │    │
│  │  (Mandatory) │   │  (Optional)      │   │  (Optional)      │    │
│  ├──────────────┤   ├──────────────────┤   ├──────────────────┤    │
│  │ - Stock      │   │ - Suppliers      │   │ - BOM            │    │
│  │ - Products   │   │ - PurchaseOrders │   │ - WorkOrders     │    │
│  │ - Categories │   │ - GRN            │   │ - Routings       │    │
│  │ - Warehouses │   │ - Receiving      │   │ - Work Centers   │    │
│  │ - Attributes │   │ - Receiving QC   │   │ - MRP Engine     │    │
│  │ - UoM        │   │   (only)         │   │ - CRP Engine     │    │
│  │ - Currencies │   │                  │   │ - Production     │    │
│  │              │   │                  │   │ - Production QC  │    │
│  │              │   │                  │   │   (future)       │    │
│  └──────────────┘   └──────────────────┘   └──────────────────┘    │
│                                                                      │
│  ┌──────────────────┐   ┌──────────────────┐                        │
│  │  SALES          │   │  QUALITY CONTROL │                        │
│  │  (Optional)      │   │  (Optional)       │                        │
│  ├──────────────────┤   ├──────────────────┤                        │
│  │ - Customers     │   │ - Acceptance     │                        │
│  │ - Customer      │   │   Rules          │                        │
│  │   Groups        │   │ - Inspections    │                        │
│  │ - Sales Orders  │   │ - NCR Reports    │                        │
│  │ - Delivery      │   │ - Quality        │                        │
│  │   Notes         │   │   Statistics     │                        │
│  │ - Stock         │   │                  │                        │
│  │   Reservation   │   │                  │                        │
│  └──────────────────┘   └──────────────────┘                        │
│                                                                      │
├─────────────────────────────────────────────────────────────────────┤
│                       INTEGRATION LAYER                              │
│  ┌──────────────────────────────────────────────────────────────┐   │
│  │  Webhook API for External Systems (Finance, ERP, etc.)       │   │
│  │  - Stock reservation webhooks                                 │   │
│  │  - Stock movement notifications                               │   │
│  │  - Inventory level alerts                                     │   │
│  │  - Sales order status updates                                 │   │
│  └──────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
                              │
                              │ Sync HTTP (Phase 1)
                              │ Async Redis Queue (Future)
                              ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     Python Prediction Service                        │
│  ┌──────────────────────────────────────────────────────────────┐   │
│  │  - Demand Forecasting (time series analysis)                  │   │
│  │  - Reorder Point Optimization                                 │   │
│  │  - Production Planning Suggestions                            │   │
│  │  - Safety Stock Calculations                                  │   │
│  │  Stateless service - no own database, queries Laravel API     │   │
│  └──────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
```

### 2.2 Module Configuration

Modules are controlled via `config/modules.php` and environment variables:

```php
// config/modules.php
return [
    'core' => [
        'enabled' => true, // Always enabled
        'features' => [
            'stock_tracking' => true,
            'multi_warehouse' => true,
            'lot_tracking' => true,
            'serial_tracking' => true,
        ],
    ],
    'procurement' => [
        'enabled' => env('MODULE_PROCUREMENT_ENABLED', true),
        'features' => [
            'suppliers' => true,
            'purchase_orders' => true,
            'receiving' => true,
            'quality_control' => env('MODULE_PROCUREMENT_QC_ENABLED', true),
        ],
    ],
    'manufacturing' => [
        'enabled' => env('MODULE_MANUFACTURING_ENABLED', false),
        'features' => [
            'bom' => true,
            'work_orders' => true,
            'production' => true,
            'quality_control' => env('MODULE_MANUFACTURING_QC_ENABLED', true),
        ],
    ],
];
```

### 2.3 Module Middleware

Routes are protected by module middleware:
```php
// Routes protected by module middleware
Route::middleware('module:procurement')->group(function () {
    // Supplier routes
    // Purchase order routes
    // GRN routes
});
```

### 2.4 Key Design Decisions

1. **Logical Modules, Not Physical**: Module separation via config and middleware, not folder restructuring
2. **Sales Module (Optional)**: Customer management, Sales Orders, and Delivery Notes - can be enabled via `MODULE_SALES_ENABLED=true`. External systems can also integrate via webhooks.
3. **Standard QC (Procurement Only)**: Acceptance rules, receiving inspections, NCR - currently implemented for procurement/receiving only. Manufacturing QC (production quality control, in-process inspection) is planned for future releases. No CAPA, SPC (can be added later)
4. **Focus on Planning & Inventory**: System prioritizes material planning, capacity planning, and inventory management over financial integration
5. **Financial Planning Deferred**: Comprehensive financial planning and cost accounting are not implemented - basic cost tracking exists for operational purposes only. This is a conscious design decision to focus on core planning capabilities first.
6. **Stateless Python Service**: Prediction service has no database - queries Laravel API for data
5. **Sync First, Async Later**: Start with HTTP for simplicity - add Redis Queue when needed
6. **Graceful Degradation**: If Python service is down, Laravel continues to work

### 2.5 Quality Control (Standard Level)

The system includes a standard-level QC module. **Current Implementation**: QC is currently implemented for **Procurement (Receiving)** only. **Manufacturing QC** (production quality control, in-process inspection, work order inspection) is planned for future releases.

**Implemented (Procurement QC):**

```
┌─────────────────────────────────────────────────────────────────┐
│                    QUALITY CONTROL (Standard)                    │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────────┐   ┌──────────────────┐   ┌─────────────┐ │
│  │ ACCEPTANCE RULES │   │ INSPECTIONS      │   │ NCR         │ │
│  ├──────────────────┤   ├──────────────────┤   ├─────────────┤ │
│  │ - By Product     │   │ - Per GRN Item   │   │ - From      │ │
│  │ - By Category    │   │ - Pass/Fail/     │   │   Inspection│ │
│  │ - By Supplier    │   │   Partial        │   │ - Workflow  │ │
│  │ - Sampling (AQL) │   │ - Disposition    │   │ - Severity  │ │
│  │ - Criteria JSON  │   │ - Approval Flow  │   │ - Closure   │ │
│  └──────────────────┘   └──────────────────┘   └─────────────┘ │
│                                                                  │
│  Tables: acceptance_rules, receiving_inspections,               │
│          non_conformance_reports                                │
│                                                                  │
│  Current Scope: Procurement/Receiving QC only                    │
│  Future Expansion: Manufacturing QC, CAPA, Supplier Ratings, SPC│
└─────────────────────────────────────────────────────────────────┘
```

**Procurement QC Workflow (Current Implementation):**
1. GRN created → Inspections auto-created per item
2. Inspector records results (pass/fail quantities)
3. Failed items → NCR created
4. NCR workflow: Open → Review → Disposition → Close
5. Dispositions: Accept, Reject, Rework, Return to Supplier, Use As-Is
6. Stock quality status updated automatically based on disposition

**Manufacturing QC (Not Implemented - Future Roadmap):**
- **Status**: Not implemented - planned for future releases
- **Planned Features**:
  - In-process inspection during production
  - Work Order operation inspection
  - Finished goods quality control
  - Production NCR tracking
  - Quality gates in routing operations
  - First Article Inspection (FAI)
  - Statistical Process Control (SPC) for production
- **Design Philosophy**: Current focus is on receiving quality control. Manufacturing QC will be added in subsequent phases to ensure production quality standards.

**Stock Quality Status Tracking:**
```
┌──────────────────────────────────────────────────────────────────┐
│                    STOCK QUALITY STATUS                           │
├──────────────────────────────────────────────────────────────────┤
│                                                                   │
│  Status                 │ Transfer │ Sale │ Production │ Bundle  │
│  ─────────────────────────────────────────────────────────────── │
│  available              │    ✓     │  ✓   │     ✓      │   ✓    │
│  pending_inspection     │    ✓*    │  ✗   │     ✗      │   ✗    │
│  on_hold                │    ✗     │  ✗   │     ✗      │   ✗    │
│  conditional            │    ✓     │  ✗   │     ✓**    │   ✗    │
│  rejected               │    ✓*    │  ✗   │     ✗      │   ✗    │
│  quarantine             │    ✓*    │  ✗   │     ✗      │   ✗    │
│                                                                   │
│  * Only to QC zones (quarantine/rejection warehouses)            │
│  ** With restrictions defined in quality_restrictions JSON       │
│                                                                   │
├──────────────────────────────────────────────────────────────────┤
│  Fields on stock table:                                          │
│  - quality_status (enum)                                         │
│  - hold_reason (text) - Why the stock is on hold                │
│  - hold_until (timestamp) - Temporary holds expire               │
│  - quality_restrictions (JSON) - Conditional use restrictions    │
│  - quality_hold_by (FK users) - Who placed the hold             │
│  - quality_hold_at (timestamp) - When hold was placed           │
│  - quality_reference_type/id - Link to Inspection/NCR           │
└──────────────────────────────────────────────────────────────────┘
```

**Warehouse QC Zones:**
- `is_quarantine_zone` - Warehouse for items awaiting inspection/disposition
- `is_rejection_zone` - Warehouse for rejected items
- `linked_quarantine_warehouse_id` - Link main warehouse to its quarantine zone
- `linked_rejection_warehouse_id` - Link main warehouse to its rejection zone
- `requires_qc_release` - Stock requires QC approval before use

**Disposition → Quality Status Mapping:**
| Disposition | Stock Quality Status |
|-------------|---------------------|
| Accept | available |
| Use As-Is | conditional |
| Reject | rejected |
| Return to Supplier | rejected |
| Rework | on_hold |
| Quarantine | quarantine |

**QC Permissions:**
- `qc.view` - View rules, inspections, NCRs
- `qc.create` - Create rules and NCRs
- `qc.edit` - Edit rules and NCRs
- `qc.delete` - Delete rules and NCRs
- `qc.inspect` - Perform inspections
- `qc.review` - Review NCRs
- `qc.approve` - Approve inspections/dispositions

### 2.6 Environment Variables

```env
# Module Configuration
MODULE_PROCUREMENT_ENABLED=true
MODULE_PROCUREMENT_QC_ENABLED=true
MODULE_MANUFACTURING_ENABLED=false
MODULE_MANUFACTURING_QC_ENABLED=true

# Prediction Service
PREDICTION_SERVICE_ENABLED=false
PREDICTION_SERVICE_URL=http://localhost:8001
PREDICTION_SERVICE_API_KEY=your-secret-key

# Webhooks
WEBHOOKS_ENABLED=false
```

---

## 3. Technology Stack

### 3.1 Backend Core
```yaml
Framework: Laravel 12.x
PHP Version: 8.4+
Database: PostgreSQL 16+
Cache: Redis 7.x
Search Engine: Elasticsearch 8.x
Queue: Redis Queue
Session: Redis
```

### 3.2 Key Packages
```yaml
Authentication: Laravel Sanctum
Search: Laravel Scout + Elasticsearch Driver
Cache: Laravel Redis
PDF Generation: DomPDF / Snappy
Excel: Maatwebsite Excel
Barcode: Picqer/php-barcode-generator
Background Jobs: Laravel Queue (Redis)
Testing: Pest / PHPUnit
Code Quality: Laravel Pint, PHPStan
```

### 3.3 Infrastructure
```yaml
Web Server: Nginx
Container: Docker + Docker Compose
CI/CD: GitHub Actions / GitLab CI
Monitoring: Laravel Telescope (dev), Sentry (production)
Logging: Monolog + Database Logger
```

### 3.4 Frontend (Separate Repo)
```yaml
Framework: React 19+
State Management: Redux Toolkit / Zustand
HTTP Client: Axios
UI Framework: Ant Design / Shadcn UI
i18n: react-i18next (UI translations only)
Build Tool: Vite
```

---

## 4. Key Features

### 4.1 Core Features
- Multi-tenant architecture
- User management with role-based access
- Product catalog with dynamic attributes
- Multi-level BOM (Bill of Materials)
- Inventory management (multi-warehouse)
- Purchase order management
- Sales order management
- Production planning & work orders
- Quality control & inspection
- Comprehensive reporting

### 4.2 Internationalization Strategy

**🎯 NEW APPROACH: UI i18n Only**

- **Multi-language UI**: Complete interface translation via react-i18next
  - Button labels, menus, forms translated
  - Validation messages, notifications translated
  - Help text, tooltips translated

- **Single Language Data**: User-entered content stored as-is
  - Product names: User enters in their language (e.g., "Dell XPS 15")
  - Descriptions: Stored in user's input language
  - Customer/Supplier names: Stored as entered
  - No translation tables needed for business data

- **Multi-currency**: Price management in multiple currencies
- **Exchange Rates**: Automatic rate updates and manual overrides
- **Localized Formats**: Date, number, currency formatting per locale

### 4.3 Advanced Features
- **MRP II (Manufacturing Resource Planning)**:
  - Material Requirements Planning (MRP) with multi-level BOM explosion
  - Capacity Requirements Planning (CRP) for work center capacity analysis
  - Master Production Schedule (MPS) through Work Orders
  - Shop Floor Control with operation tracking
- Demand forecasting
- Lot/batch/serial number tracking
- Barcode/QR code support
- Low stock alerts
- Email/SMS notifications
- Activity logging & audit trail
- Advanced analytics & dashboards

---

## 5. Database Architecture

### 5.1 Design Principles
- **Normalized**: Proper 3NF normalization for data integrity
- **Simplified**: No translation tables for user-entered data
- **Flexible**: JSONB for dynamic fields, EAV for typed attributes
- **Performant**: Proper indexing, materialized views for reports
- **Scalable**: Partition-ready for large datasets

### 5.2 Table Count Summary
```
Total Tables: ~35 tables (SIMPLIFIED from 50)

Core Business: 24 tables
├── Organization: 3 (companies, users, roles/permissions)
├── Products: 11 tables:
│   ├── products, product_types, categories
│   ├── category_product (pivot: product-category M:M)
│   ├── attributes, attribute_values
│   ├── category_attributes (pivot: category-attribute M:M)
│   ├── product_attributes (product attribute values)
│   ├── product_prices, product_details, media
├── Inventory: 3 (warehouses, stock, movements)
├── Orders: 4 (purchase, sales, GRN, delivery)
├── Other: 3 (suppliers, customers, units_of_measure)

Multi-currency: 2 tables
├── Currencies: 1 (currency definitions)
├── Exchange Rates: 1 (rate history)

Manufacturing: 3 tables
├── BOM: 2 (boms, items)
├── Production: 1 (production_orders, work_centers)

Support Systems: 5 tables
├── Logging: 2 (activity, errors)
├── Notifications: 1
├── Settings: 1
├── Media: 1 (polymorphic)

Note: Attributes are linked to CATEGORIES (not product types)
      for category-specific attribute requirements
```

### 5.3 Database Design Philosophy

**What we AVOID:**
- ❌ Magento-style over-engineering (300+ tables)
- ❌ Translation tables for user data (product_translations, category_translations)
- ❌ Pure EAV for everything (performance killer)

**What we USE:**
- ✅ Simple structure: Direct columns for names/descriptions
- ✅ JSONB for truly flexible data (specifications, custom fields)
- ✅ Typed EAV for validated attributes (product type specific)
- ✅ Multi-currency pricing with exchange rates
- ✅ Frontend i18n for UI translations (react-i18next)

---

## 6. Core Business Models

### 6.1 Organization & Multi-tenancy

#### Companies
```sql
companies
├── id (bigint, PK)
├── name (varchar(255), unique)
├── legal_name (varchar(255))
├── tax_id (varchar(50), unique)
├── email (varchar(255))
├── phone (varchar(50))
├── address (text)
├── city (varchar(100))
├── country (varchar(100))
├── postal_code (varchar(20))
├── base_currency (varchar(3), default: 'USD')
├── supported_currencies (jsonb) -- ["USD", "EUR", "TRY", "GBP"]
├── timezone (varchar(50), default: 'UTC')
├── fiscal_year_start (date)
├── settings (jsonb)
├── is_active (boolean, default: true)
├── created_at (timestamp)
├── updated_at (timestamp)
└── deleted_at (timestamp, nullable)

-- Note: No default_language or supported_languages
-- UI language is handled by frontend i18n
```

#### Users
```sql
users
├── id (bigint, PK)
├── company_id (bigint, FK)
├── email (varchar(255), unique)
├── password (varchar(255))
├── first_name (varchar(100))
├── last_name (varchar(100))
├── phone (varchar(50), nullable)
├── avatar_url (text, nullable)
├── preferred_currency (varchar(3), default: 'USD')
├── is_active (boolean, default: true)
├── last_login_at (timestamp, nullable)
├── last_login_ip (inet, nullable)
├── email_verified_at (timestamp, nullable)
├── created_at (timestamp)
├── updated_at (timestamp)
└── deleted_at (timestamp, nullable)

-- Note: No preferred_language field
-- UI language stored in browser localStorage
```

---

### 6.2 Product Catalog (Simplified - No Translations)

#### Product Types
```sql
product_types
├── id (bigint, PK)
├── company_id (bigint, FK)
├── code (varchar(20), unique) -- 'electronics', 'textile', 'food'
├── name (varchar(100)) -- Single language, user input
├── description (text, nullable) -- Single language
├── can_be_purchased (boolean, default: true)
├── can_be_sold (boolean, default: true)
├── can_be_manufactured (boolean, default: false)
├── track_inventory (boolean, default: true)
├── is_active (boolean, default: true)
├── created_at (timestamp)
└── updated_at (timestamp)
```

#### Categories (Hierarchical - No Translations)
```sql
categories
├── id (bigint, PK)
├── company_id (bigint, FK)
├── parent_id (bigint, FK to categories, nullable)
├── name (varchar(255)) -- Single language, user input
├── slug (varchar(255), unique) -- URL-friendly identifier
├── description (text, nullable) -- Single language
├── is_active (boolean, default: true)
├── sort_order (integer, default: 0)
├── created_by (bigint, FK)
├── created_at (timestamp)
├── updated_at (timestamp)
└── deleted_at (timestamp, nullable)

INDEX idx_categories_parent ON categories(parent_id)
INDEX idx_categories_slug ON categories(slug)

-- ❌ REMOVED: category_translations table
-- Note: Products linked via category_product pivot table (many-to-many)
```

#### Units of Measure
```sql
units_of_measure
├── id (bigint, PK)
├── company_id (bigint, FK)
├── code (varchar(20)) -- 'kg', 'lbs', 'pcs', 'l', 'm'
├── name (varchar(50)) -- Single language
├── uom_type (enum: weight, volume, length, area, quantity, time)
├── base_unit_id (bigint, FK to units_of_measure, nullable)
├── conversion_factor (decimal(20,6), nullable)
├── precision (integer, default: 2)
├── is_active (boolean, default: true)
└── created_at (timestamp)
```

#### Category-Product Pivot (Many-to-Many)
```sql
category_product
├── id (bigint, PK)
├── category_id (bigint, FK)
├── product_id (bigint, FK)
├── is_primary (boolean, default: false) -- Primary category flag
├── created_at (timestamp)
└── updated_at (timestamp)

UNIQUE idx_category_product ON category_product(category_id, product_id)
INDEX idx_category_product_primary ON category_product(product_id, is_primary)
```

#### Products (Core - No Translations)
```sql
products
├── id (bigint, PK)
├── company_id (bigint, FK)
├── product_type_id (bigint, FK)
├── sku (varchar(100), unique)
├── slug (varchar(255), unique) -- URL-friendly identifier
├── name (varchar(255)) -- Single language, user input
├── description (text, nullable) -- Single language
├── short_description (text, nullable) -- Brief summary
├── uom_id (bigint, FK)
│
├── -- Inventory Control
├── track_lot_number (boolean, default: false)
├── track_serial_number (boolean, default: false)
├── track_expiry_date (boolean, default: false)
├── reorder_point (decimal(15,3), default: 0)
├── safety_stock (decimal(15,3), default: 0)
├── lead_time_days (integer, default: 0)
│
├── -- Negative Stock Policy
├── negative_stock_policy (varchar(20), default: 'NEVER')
│   -- Policy: 'NEVER', 'ALLOWED', 'LIMITED'
├── negative_stock_limit (decimal(15,3), default: 0)
│   -- Maximum allowed negative quantity (for LIMITED policy)
│
├── -- Reservation Policy
├── reservation_policy (varchar(20), default: 'full')
│   -- Policy: 'full', 'partial', 'reject', 'wait'
│
├── -- Over-Delivery Tolerance
├── over_delivery_tolerance_percentage (decimal(5,2), nullable)
│   -- Product-specific tolerance percentage
│
├── -- Costing (in base currency)
├── cost_method (enum: fifo, lifo, avg, std)
├── standard_cost (decimal(15,4), default: 0)
├── average_cost (decimal(15,4), default: 0)
├── base_currency (varchar(3), default: 'USD')
│
├── -- Manufacturing
├── has_bom (boolean, default: false)
├── make_to_order (boolean, default: false)
├── requires_qc (boolean, default: false)
│
├── -- Status
├── status (enum: active, inactive, discontinued, pending_approval)
├── is_active (boolean, default: true) -- Quick active flag
├── is_featured (boolean, default: false) -- Featured product flag
├── meta_data (jsonb, nullable) -- Flexible metadata storage
├── created_by (bigint, FK)
├── created_at (timestamp)
├── updated_at (timestamp)
└── deleted_at (timestamp, nullable)

INDEX idx_products_sku ON products(company_id, sku)
INDEX idx_products_type ON products(product_type_id)
INDEX idx_products_status ON products(status)
INDEX idx_products_active ON products(is_active)
INDEX idx_products_name ON products(name) -- Full-text search
UNIQUE idx_products_company_sku ON products(company_id, sku)

-- ❌ REMOVED: product_translations table
-- ❌ REMOVED: category_id (moved to category_product pivot table)
```

#### Product Prices (Multi-currency)
```sql
product_prices
├── id (bigint, PK)
├── product_id (bigint, FK)
├── currency_code (varchar(3)) -- 'USD', 'EUR', 'TRY', 'GBP'
├── price_type (enum: base, cost, wholesale, retail, special)
├── unit_price (decimal(15,4))
├── min_quantity (decimal(15,3), default: 1) -- Tiered pricing
├── customer_group_id (bigint, FK, nullable)
├── effective_date (date)
├── expiry_date (date, nullable)
├── is_active (boolean, default: true)
├── created_at (timestamp)
└── updated_at (timestamp)

INDEX idx_prices_product ON product_prices(product_id, currency_code)
INDEX idx_prices_effective ON product_prices(effective_date, expiry_date)
```

#### Media (Polymorphic - Unified)
```sql
media
├── id (bigint, PK)
├── company_id (bigint, FK)
├── mediable_type (varchar(100)) -- App\Models\Product, App\Models\User
├── mediable_id (bigint) -- Related model's ID
├── collection_name (varchar(50)) -- 'images', 'documents', 'videos'
├── media_type (enum: image, video, document, pdf, cad)
├── file_name (varchar(255))
├── file_path (text)
├── file_url (text)
├── file_size_kb (integer)
├── mime_type (varchar(100))
├── disk (varchar(50), default: 'public')
├── order_column (integer, default: 0)
├── custom_properties (jsonb, nullable)
├── created_by (bigint, FK)
├── created_at (timestamp)
└── updated_at (timestamp)

INDEX idx_media_mediable ON media(mediable_type, mediable_id)
INDEX idx_media_collection ON media(mediable_type, mediable_id, collection_name)

-- ❌ REMOVED: product_media table (replaced by polymorphic media)
```

#### Product Details (JSONB for flexibility)
```sql
product_details
├── id (bigint, PK)
├── product_id (bigint, FK, unique)
├── barcodes (jsonb, nullable)
│   -- {"primary": "123456", "ean": "9876543210", "upc": "456789"}
├── dimensions (jsonb, nullable)
│   -- {"weight": 1.5, "weight_unit": "kg", "length": 10, "width": 5, "height": 3, "unit": "cm"}
├── specifications (jsonb, nullable)
│   -- Free-form technical specs
├── custom_fields (jsonb, nullable)
│   -- Company-specific custom data
└── updated_at (timestamp)

CREATE INDEX idx_details_barcodes ON product_details USING GIN(barcodes);
CREATE INDEX idx_details_specs ON product_details USING GIN(specifications);
```

#### Attributes (Master attribute definitions)
```sql
attributes
├── id (bigint, PK)
├── company_id (bigint, FK)
├── name (varchar(100)) -- 'color', 'size', 'storage' - unique per company
├── display_name (varchar(100)) -- 'Renk', 'Beden', 'Depolama'
├── type (enum: select, text, number, boolean)
├── order (integer, default: 0) -- Display order
├── is_variant_attribute (boolean, default: false) -- Can be used for variant generation
├── is_filterable (boolean, default: true) -- Show in filters
├── is_visible (boolean, default: true) -- Show on product page
├── is_required (boolean, default: false)
├── description (text, nullable)
├── created_at (timestamp)
└── updated_at (timestamp)

UNIQUE idx_attr_name ON attributes(company_id, name)
INDEX idx_attr_variant ON attributes(is_variant_attribute)
```

#### Attribute Values (Predefined options for select-type attributes)
```sql
attribute_values
├── id (bigint, PK)
├── attribute_id (bigint, FK)
├── value (varchar(255)) -- 'Siyah', 'S', '128GB'
├── label (varchar(255), nullable) -- Optional display label
├── order (integer, default: 0)
├── is_active (boolean, default: true)
├── created_at (timestamp)
└── updated_at (timestamp)

UNIQUE idx_attr_value ON attribute_values(attribute_id, value)
```

#### Category Attributes (Links attributes to categories)
```sql
category_attributes
├── id (bigint, PK)
├── category_id (bigint, FK)
├── attribute_id (bigint, FK)
├── is_required (boolean, default: false) -- Override for this category
├── order (integer, default: 0) -- Display order in this category
├── created_at (timestamp)
└── updated_at (timestamp)

UNIQUE idx_cat_attr ON category_attributes(category_id, attribute_id)

-- Note: Attributes are linked to categories, NOT to product types
-- This allows category-specific attribute requirements
```

#### Product Attributes (Actual attribute values for products)
```sql
product_attributes
├── id (bigint, PK)
├── product_id (bigint, FK)
├── attribute_id (bigint, FK)
├── value (varchar(255)) -- The actual value
├── created_at (timestamp)
└── updated_at (timestamp)

UNIQUE idx_prod_attr ON product_attributes(product_id, attribute_id)
```

---

### 6.3 Bill of Materials (BOM)

#### BOM Header
```sql
boms
├── id (bigint, PK)
├── company_id (bigint, FK)
├── product_id (bigint, FK)
├── bom_number (varchar(50), unique)
├── version (integer, default: 1)
├── name (varchar(255)) -- Single language
├── description (text, nullable) -- Single language
├── bom_type (enum: manufacturing, engineering, sales, phantom)
├── quantity (decimal(15,3), default: 1.0)
├── uom_id (bigint, FK)
├── status (enum: draft, active, obsolete)
├── is_default (boolean, default: false)
├── effective_date (date)
├── expiry_date (date, nullable)
├── created_by (bigint, FK)
├── created_at (timestamp)
└── updated_at (timestamp)
```

#### BOM Items
```sql
bom_items
├── id (bigint, PK)
├── bom_id (bigint, FK)
├── component_id (bigint, FK to products)
├── line_number (integer)
├── quantity (decimal(15,6))
├── uom_id (bigint, FK)
├── scrap_percentage (decimal(5,2), default: 0)
├── is_optional (boolean, default: false)
├── is_phantom (boolean, default: false)
└── created_at (timestamp)
```

---

### 6.4 Inventory Management

#### Warehouses
```sql
warehouses
├── id (bigint, PK)
├── company_id (bigint, FK)
├── code (varchar(50))
├── name (varchar(255)) -- Single language
├── warehouse_type (enum: finished_goods, raw_materials, wip, returns)
├── address (text, nullable)
├── city (varchar(100), nullable)
├── country (varchar(100), nullable)
├── contact_person (varchar(255), nullable)
├── contact_phone (varchar(50), nullable)
├── is_active (boolean, default: true)
├── created_at (timestamp)
└── updated_at (timestamp)

UNIQUE idx_warehouses_code ON warehouses(company_id, code)
```

#### Stock (Current levels)
```sql
stock
├── id (bigint, PK)
├── product_id (bigint, FK)
├── warehouse_id (bigint, FK)
├── lot_number (varchar(100), nullable)
├── serial_number (varchar(100), nullable)
├── quantity_on_hand (decimal(15,3), default: 0)
├── quantity_reserved (decimal(15,3), default: 0)
├── quantity_available (decimal(15,3) GENERATED AS (quantity_on_hand - quantity_reserved) STORED)
├── unit_cost (decimal(15,4))
├── total_value (decimal(20,4) GENERATED AS (quantity_on_hand * unit_cost) STORED)
├── expiry_date (date, nullable)
├── status (enum: available, reserved, quarantine, damaged, expired)
└── updated_at (timestamp)

UNIQUE idx_stock_unique ON stock(product_id, warehouse_id, COALESCE(lot_number, ''), COALESCE(serial_number, ''))
```

#### Stock Movements (Transaction log)
```sql
stock_movements
├── id (bigint, PK)
├── company_id (bigint, FK)
├── product_id (bigint, FK)
├── warehouse_id (bigint, FK)
├── lot_number (varchar(100), nullable)
├── movement_type (enum: receipt, issue, transfer, adjustment, production_consume, production_output, return, scrap)
├── transaction_type (enum: purchase_order, sales_order, production_order, transfer_order, adjustment)
├── reference_number (varchar(100), nullable)
├── reference_id (bigint, nullable)
├── quantity (decimal(15,3))
├── quantity_before (decimal(15,3))
├── quantity_after (decimal(15,3))
├── unit_cost (decimal(15,4))
├── total_cost (decimal(20,4))
├── notes (text, nullable)
├── created_by (bigint, FK)
└── created_at (timestamp)

INDEX idx_movements_product ON stock_movements(product_id, created_at DESC)
INDEX idx_movements_warehouse ON stock_movements(warehouse_id, created_at DESC)
```

#### Stock Debts (Negative Stock Tracking)
```sql
stock_debts
├── id (bigint, PK)
├── company_id (bigint, FK)
├── product_id (bigint, FK)
├── warehouse_id (bigint, FK)
├── stock_movement_id (bigint, FK, nullable)
│   -- Which movement caused this debt
├── quantity (decimal(15,3), NOT NULL)
│   -- Debt amount (positive value)
├── reconciled_quantity (decimal(15,3), default: 0)
│   -- Settled amount
├── outstanding_quantity (decimal(15,3) GENERATED AS (quantity - reconciled_quantity) STORED)
├── reference_type (varchar(50), nullable)
│   -- DeliveryNote, WorkOrder, etc.
├── reference_id (bigint, nullable)
├── created_at (timestamp)
├── reconciled_at (timestamp, nullable)
└── updated_at (timestamp)

INDEX idx_stock_debts_outstanding ON stock_debts(company_id, product_id, warehouse_id, outstanding_quantity)
INDEX idx_stock_debts_reference ON stock_debts(reference_type, reference_id)
```

---

### 6.5 Negative Stock Policy System

The system implements a controlled negative stock mechanism with product-level policies and automatic debt tracking. This allows operational flexibility while maintaining data consistency and preventing uncontrolled negative stock scenarios.

#### 6.5.1 Policy Types

Each product can have a negative stock policy:

| Policy | Behavior | Use Case |
|--------|----------|----------|
| **NEVER** | Cannot go negative. Reject transaction if insufficient stock. | Finished products, critical materials |
| **ALLOWED** | Can go negative without limit. Stock debt is tracked. | Raw materials, operational flexibility |
| **LIMITED** | Can go negative up to `negative_stock_limit`. Reject if limit exceeded. | Semi-finished products, controlled scenarios |

#### 6.5.2 Database Schema

**Products Table:**
```sql
products
├── ...
├── negative_stock_policy (varchar(20), default: 'NEVER')
├── negative_stock_limit (decimal(15,3), default: 0)
└── ...
```

**Stock Debts Table:**
See Section 6.4.3 above for schema.

#### 6.5.3 Automatic Debt Creation

When stock goes negative:
1. System checks product's `negative_stock_policy`
2. If policy allows (ALLOWED or LIMITED within limit):
   - Stock `quantity_on_hand` goes negative
   - `StockDebt` record is created
   - Debt is linked to the stock movement and reference (DeliveryNote, WorkOrder, etc.)
3. If policy rejects (NEVER or LIMITED exceeded):
   - Transaction is rejected with error message

**StockService::issueStock() Logic:**
```php
if ($quantityAfter < 0) {
    $product = $stock->product;
    
    if (!$this->canGoNegative($product, abs($quantityAfter))) {
        throw new BusinessException("Insufficient stock...");
    }
    
    // Create stock debt
    $this->createStockDebt($stock, abs($quantityAfter), $data);
}
```

#### 6.5.4 Automatic Debt Reconciliation

When stock is received:
1. System automatically reconciles outstanding debts (FIFO order)
2. Oldest debts are settled first
3. `reconciled_quantity` is updated
4. `reconciled_at` timestamp is set

**StockService::receiveStock() Logic:**
```php
// Update stock
$stock->quantity_on_hand += $data['quantity'];
$stock->save();

// Automatically reconcile debts
$this->reconcileStockDebts($stock, $data['quantity']);
```

#### 6.5.5 MRP II Integration

Negative stock is considered in MRP (Material Requirements Planning) calculations:
- Negative stock is treated as **priority requirement**
- MRP recommendations are marked as high priority when negative stock exists
- Net requirement calculation includes negative stock impact

#### 6.5.6 Stock Alert Service

The system provides alerts for:
- Current negative stock items
- Long-term outstanding debts (>7 days)
- Products approaching negative stock limit
- Debt reconciliation status

**API Endpoints:**
- `GET /api/stock-debts` - List all stock debts
- `GET /api/stock-debts/outstanding` - List outstanding debts
- `GET /api/stock-debts/{id}` - Get debt details
- `GET /api/stock-alerts/negative-stock` - Get negative stock alerts

#### 6.5.7 Benefits

1. **Operational Flexibility**: Process transactions even when stock is temporarily unavailable
2. **Data Consistency**: All movements are recorded, audit trail is maintained
3. **Automatic Tracking**: Stock debts are automatically tracked and reconciled
4. **MRP II Integration**: MRP (Material Requirements Planning) calculations consider negative stock as priority requirement
5. **Controlled Scenarios**: Policy-based control prevents uncontrolled negative stock

#### 6.5.8 Best Practices

**Policy Recommendations:**
- **Finished Products**: `NEVER` - Should never go negative
- **Critical Raw Materials**: `NEVER` or `LIMITED` - Can stop production
- **Standard Raw Materials**: `LIMITED` - Controlled negative stock
- **Semi-Finished Products**: `LIMITED` - Internal production flexibility
- **Auxiliary Materials**: `ALLOWED` - Maximum operational flexibility

**Monitoring:**
- Regular negative stock reports
- Automatic alerts for long-term debts
- Pre-MRP run negative stock checks (MRP II Material Requirements Planning)
- Supplier performance evaluation based on debt patterns

---

### 6.6 Stock Reservation System

The system implements a flexible stock reservation mechanism with configurable policies to handle stock reservations when insufficient stock is available. Reservations are automatically created and released based on order status transitions.

#### 6.5.1 Reservation Policy Enum

The system uses a `ReservationPolicy` enum to define how reservations are handled:

```php
enum ReservationPolicy: string
{
    case FULL = 'full';      // Only reserve if full quantity available
    case PARTIAL = 'partial'; // Reserve available quantity even if less
    case REJECT = 'reject';   // Reject reservation if insufficient
    case WAIT = 'wait';       // Queue for future auto-retry (TODO: Future)
}
```

**Policy Behaviors:**

| Policy | Behavior | Use Case |
|--------|----------|----------|
| **FULL** | Only reserve if full quantity is available. Reject if insufficient. | Critical orders requiring exact quantities |
| **PARTIAL** | Reserve available quantity even if less than requested. | Flexible orders where partial fulfillment is acceptable |
| **REJECT** | Reject the reservation request if insufficient stock. | Strict inventory control, no partial reservations |
| **WAIT** | Queue and auto-retry when stock becomes available. ⚠️ **NOT YET IMPLEMENTED** | Future: Automated retry mechanism |

#### 6.5.2 Database Schema

**Products Table:**
```sql
products
├── ...
├── reservation_policy (varchar(20), default: 'full')
│   -- Policy for this product: 'full', 'partial', 'reject', 'wait'
└── ...
```

**Stock Table:**
```sql
stock
├── ...
├── quantity_on_hand (decimal(15,3), default: 0)
├── quantity_reserved (decimal(15,3), default: 0)
├── quantity_available (decimal(15,3) GENERATED AS (quantity_on_hand - quantity_reserved) STORED)
└── ...
```

#### 6.5.3 Automatic Reservation Flow

**Sales Orders:**
1. **Sales Order Confirmed** → Automatically reserve stock for all items
   - Uses `StockService::reserveStock()` with product's `reservation_policy`
   - Respects policy: FULL, PARTIAL, REJECT, or WAIT
2. **Sales Order Cancelled/Rejected** → Automatically release reservations
3. **Delivery Note Shipped** → Release reservations (physical stock issued)

**Work Orders:**
1. **Work Order Released** → Automatically reserve materials for all items
   - Uses `StockService::reserveStock()` with product's `reservation_policy`
   - Materials are reserved from the work order's warehouse
2. **Work Order Cancelled** → Automatically release material reservations
3. **Materials Issued** → Release reservations (physical stock issued)

#### 6.5.4 Reservation Logic Implementation

**StockService::reserveStock():**
```php
public function reserveStock(
    int $productId,
    int $warehouseId,
    float $requestedQty,
    ?string $lotNumber = null,
    string $operationType = 'sale',
    bool $skipQualityCheck = false
): StockReservation
{
    // Get product and reservation policy
    $product = Product::findOrFail($productId);
    $policy = ReservationPolicy::tryFrom($product->reservation_policy ?? 'full') 
        ?? ReservationPolicy::FULL;

    $stock = $this->findOrCreateStock($productId, $warehouseId, $lotNumber);
    $availableQty = $stock->quantity_available;

    // Apply policy logic
    if ($availableQty < $requestedQty) {
        if ($policy === ReservationPolicy::PARTIAL) {
            // Reserve available quantity
            $reservedQty = $availableQty;
        } elseif ($policy === ReservationPolicy::WAIT) {
            // TODO: Queue for future retry
            throw new BusinessException("Insufficient stock. WAIT policy not yet implemented.");
        } else {
            // FULL or REJECT: Reject reservation
            throw new BusinessException("Insufficient stock. Available: {$availableQty}, Requested: {$requestedQty}");
        }
    } else {
        $reservedQty = $requestedQty;
    }

    // Create reservation record
    // Update stock.quantity_reserved
    // Return StockReservation
}
```

#### 6.5.5 Reservation Release

**StockService::releaseReservation():**
```php
public function releaseReservation(
    int $productId,
    int $warehouseId,
    float $quantity,
    ?string $lotNumber = null
): void
{
    // Find reservation
    // Update stock.quantity_reserved (decrease)
    // Mark reservation as released
}
```

#### 6.5.6 Service Integration

**SalesOrderService:**
- `confirm()` → Calls `reserveStockForOrder()` → Automatically reserves stock
- `cancel()` → Calls `releaseStockForOrder()` → Automatically releases reservations
- `reject()` → Calls `releaseStockForOrder()` → Automatically releases reservations

**DeliveryNoteService:**
- `ship()` → Calls `releaseReservation()` → Releases reservation before issuing stock

**WorkOrderService:**
- `release()` → Calls `reserveMaterialsForOrder()` → Automatically reserves materials
- `cancel()` → Calls `releaseMaterialsForOrder()` → Automatically releases reservations
- `issueMaterials()` → Calls `releaseReservation()` → Releases reservation before issuing stock

#### 6.5.7 Benefits

1. **Flexible Policies**: Different products can have different reservation behaviors
2. **Automatic Management**: Reservations created/released automatically based on order status
3. **Stock Availability**: `quantity_available` automatically calculated (on_hand - reserved)
4. **Partial Reservations**: Support for partial fulfillment when policy allows
5. **Future-Ready**: WAIT policy placeholder for future queue/retry mechanism

#### 6.5.8 Use Cases

**High-Value Items (FULL policy):**
- Electronics, precision instruments
- Require exact quantities, no partial reservations

**Bulk Materials (PARTIAL policy):**
- Raw materials, chemicals
- Accept partial fulfillment, reserve what's available

**Strict Control (REJECT policy):**
- Critical inventory items
- No reservations if insufficient stock

**Future: Automated Retry (WAIT policy):**
- Queue reservation requests
- Auto-retry when stock arrives
- Requires queue system implementation

---

### 6.7 Procurement

#### Suppliers
```sql
suppliers
├── id (bigint, PK)
├── company_id (bigint, FK)
├── supplier_code (varchar(50))
├── name (varchar(255)) -- Single language
├── email (varchar(255), nullable)
├── phone (varchar(50), nullable)
├── address (text, nullable)
├── city (varchar(100), nullable)
├── country (varchar(100), nullable)
├── currency (varchar(3), default: 'USD')
├── payment_terms_days (integer, default: 30)
├── credit_limit (decimal(15,2), nullable)
├── lead_time_days (integer, default: 0)
├── is_active (boolean, default: true)
├── created_by (bigint, FK)
├── created_at (timestamp)
└── updated_at (timestamp)

UNIQUE idx_suppliers_code ON suppliers(company_id, supplier_code)
```

#### Purchase Orders
```sql
purchase_orders
├── id (bigint, PK)
├── company_id (bigint, FK)
├── order_number (varchar(50), unique)
├── supplier_id (bigint, FK)
├── warehouse_id (bigint, FK)
├── order_date (date)
├── expected_delivery_date (date, nullable)
├── status (enum: draft, pending_approval, approved, sent, partially_received, received, cancelled)
├── currency (varchar(3))
├── exchange_rate (decimal(15,6), default: 1.0)
├── subtotal (decimal(15,2))
├── tax_amount (decimal(15,2), default: 0)
├── shipping_cost (decimal(15,2), default: 0)
├── total_amount (decimal(15,2))
├── notes (text, nullable)
├── created_by (bigint, FK)
├── created_at (timestamp)
├── updated_at (timestamp)
└── deleted_at (timestamp, nullable)

INDEX idx_po_supplier ON purchase_orders(supplier_id, order_date DESC)
INDEX idx_po_status ON purchase_orders(status)
```

#### Purchase Order Items
```sql
purchase_order_items
├── id (bigint, PK)
├── purchase_order_id (bigint, FK)
├── product_id (bigint, FK)
├── quantity_ordered (decimal(15,3))
├── quantity_received (decimal(15,3), default: 0)
├── uom_id (bigint, FK)
├── unit_price (decimal(15,4))
├── tax_percentage (decimal(5,2), default: 0)
├── line_total (decimal(15,2))
├── over_delivery_tolerance_percentage (decimal(5,2), nullable)
│   -- Over-delivery tolerance for this specific order item (most specific level)
└── created_at (timestamp)
```

---

### 6.8 Sales Management (Optional Module)

#### Customers
```sql
customers
├── id (bigint, PK)
├── company_id (bigint, FK)
├── customer_code (varchar(50))
├── name (varchar(255)) -- Single language
├── email (varchar(255), nullable)
├── phone (varchar(50), nullable)
├── address (text, nullable)
├── city (varchar(100), nullable)
├── country (varchar(100), nullable)
├── currency (varchar(3), default: 'USD')
├── payment_terms_days (integer, default: 30)
├── credit_limit (decimal(15,2), nullable)
├── is_active (boolean, default: true)
├── created_by (bigint, FK)
├── created_at (timestamp)
├── updated_at (timestamp)
└── deleted_at (timestamp, nullable)

UNIQUE idx_customers_code ON customers(company_id, customer_code)
```

#### Sales Orders
```sql
sales_orders
├── id (bigint, PK)
├── company_id (bigint, FK)
├── order_number (varchar(50), unique)
├── customer_id (bigint, FK)
├── warehouse_id (bigint, FK)
├── order_date (date)
├── required_date (date, nullable)
├── status (enum: draft, confirmed, in_production, ready_to_ship, shipped, delivered, cancelled)
├── currency (varchar(3))
├── exchange_rate (decimal(15,6), default: 1.0)
├── subtotal (decimal(15,2))
├── tax_amount (decimal(15,2), default: 0)
├── shipping_cost (decimal(15,2), default: 0)
├── total_amount (decimal(15,2))
├── notes (text, nullable)
├── created_by (bigint, FK)
├── created_at (timestamp)
├── updated_at (timestamp)
└── deleted_at (timestamp, nullable)

INDEX idx_so_customer ON sales_orders(customer_id, order_date DESC)
INDEX idx_so_status ON sales_orders(status)
```

#### Sales Order Items
```sql
sales_order_items
├── id (bigint, PK)
├── sales_order_id (bigint, FK)
├── product_id (bigint, FK)
├── quantity_ordered (decimal(15,3))
├── quantity_shipped (decimal(15,3), default: 0)
├── uom_id (bigint, FK)
├── unit_price (decimal(15,4))
├── tax_percentage (decimal(5,2), default: 0)
├── line_total (decimal(15,2))
├── over_delivery_tolerance_percentage (decimal(5,2), nullable)
│   -- Over-delivery tolerance for this specific order item (most specific level)
└── created_at (timestamp)
```

---

### 6.9 Over-Delivery Tolerance System

The system implements a flexible over-delivery tolerance mechanism for both **Sales Orders → Delivery Notes** and **Purchase Orders → Goods Received Notes (GRN)**. This allows partial deliveries while preventing excessive over-delivery through a hierarchical fallback system.

#### 6.9.1 Tolerance Levels (Fallback Logic)

The system uses a **4-level fallback hierarchy** (most specific to least specific, SaaS application - no system level):

```
1. Order Item Level (Most Specific)
   ├── sales_order_items.over_delivery_tolerance_percentage
   └── purchase_order_items.over_delivery_tolerance_percentage

2. Product Level
   └── products.over_delivery_tolerance_percentage

3. Category Level
   └── categories.over_delivery_tolerance_percentage (primary category)

4. Company Level (Company-specific default - Final Fallback)
   └── settings.delivery.default_over_delivery_tolerance.{company_id}
   
Note: No system-level tolerance as this is a SaaS application where each company
manages its own tolerance settings. Company-level is the final fallback.
```

**Decision Logic:**
```php
$tolerance = $orderItem->over_delivery_tolerance_percentage
    ?? $product->over_delivery_tolerance_percentage
    ?? $category->over_delivery_tolerance_percentage
    ?? Setting::get("delivery.default_over_delivery_tolerance.{$companyId}", 0);
    
// Note: Company-level is the final fallback (no system-level in SaaS architecture)
```

**API Endpoints for Company-Level Tolerance:**
- `GET /api/over-delivery-tolerance` - Get current company's default tolerance
- `PUT /api/over-delivery-tolerance` - Update current company's default tolerance (Admin only)
- `GET /api/over-delivery-tolerance/levels` - Get all tolerance levels (Item, Product, Category, Company)

#### 6.9.2 Database Schema

**Products Table:**
```sql
products
├── ...
├── over_delivery_tolerance_percentage (decimal(5,2), nullable)
│   -- Product-specific tolerance (e.g., 5.0 for 5%)
└── ...
```

**Categories Table:**
```sql
categories
├── ...
├── over_delivery_tolerance_percentage (decimal(5,2), nullable)
│   -- Category-specific tolerance (e.g., 2.0 for bulk items)
└── ...
```

**Sales Order Items Table:**
```sql
sales_order_items
├── ...
├── over_delivery_tolerance_percentage (decimal(5,2), nullable)
│   -- Item-specific override (most specific)
└── ...
```

**Purchase Order Items Table:**
```sql
purchase_order_items
├── ...
├── over_delivery_tolerance_percentage (decimal(5,2), nullable)
│   -- Item-specific override (most specific)
└── ...
```

**Company Settings (in Settings table):**
```sql
settings
├── group = 'delivery'
├── key = 'default_over_delivery_tolerance.{company_id}'
├── value = '0' (default: no tolerance, company-specific)
└── ...

Note: Each company has its own default tolerance setting. No global system-level tolerance.
```

#### 6.9.3 Sales Order → Delivery Note Flow

**Quantity Control Logic:**
1. Calculate total quantity already in delivery notes (including DRAFTs):
   ```php
   $totalInDeliveryNotes = DeliveryNoteItem::where('sales_order_item_id', $item->id)
       ->sum('quantity_shipped');
   ```

2. Calculate remaining quantity:
   ```php
   $remainingQty = $salesOrderItem->quantity_ordered - $totalInDeliveryNotes;
   ```

3. Get tolerance using fallback logic:
   ```php
   $tolerancePercentage = $this->getOverDeliveryTolerance($salesOrderItem);
   ```

4. Calculate maximum allowed quantity:
   ```php
   $maxAllowedQty = $salesOrderItem->quantity_ordered * (1 + $tolerancePercentage / 100);
   $maxAllowedQtyInDeliveryNotes = $maxAllowedQty - $totalInDeliveryNotes;
   ```

5. Validate:
   - If `quantity_requested > remainingQty`:
     - If `quantity_requested <= maxAllowedQtyInDeliveryNotes`: ✅ **Allow with warning log**
     - If `quantity_requested > maxAllowedQtyInDeliveryNotes`: ❌ **Reject with error**

**Example Scenario:**
- Sales Order Item: 1000 units ordered
- Company default tolerance: 5%
- Max allowed: 1000 × 1.05 = 1050 units

| Delivery Note | Quantity | Result | Reason |
|--------------|----------|--------|--------|
| DN-001 | 1000 | ✅ Success | Normal delivery |
| DN-002 | 50 | ✅ Success (Warning) | Within tolerance (1050 total) |
| DN-003 | 1 | ❌ Error | Exceeds tolerance (1051 > 1050) |

#### 6.9.4 Purchase Order → GRN Flow

**Quantity Control Logic:**
1. Calculate total quantity already in GRNs (including DRAFTs):
   ```php
   $totalInGrns = GoodsReceivedNoteItem::where('purchase_order_item_id', $item->id)
       ->sum('quantity_received');
   ```

2. Calculate remaining quantity:
   ```php
   $remainingQty = $purchaseOrderItem->quantity_ordered - $totalInGrns;
   ```

3. Get tolerance using fallback logic (same as Sales Orders):
   ```php
   $tolerancePercentage = $this->getOverDeliveryTolerance($purchaseOrderItem);
   ```

4. Calculate maximum allowed quantity:
   ```php
   $maxAllowedQty = $purchaseOrderItem->quantity_ordered * (1 + $tolerancePercentage / 100);
   $maxAllowedQtyInGrns = $maxAllowedQty - $totalInGrns;
   ```

5. Validate (same logic as Delivery Notes)

**Example Scenario:**
- Purchase Order Item: 500 units ordered
- Product tolerance: 3%
- Max allowed: 500 × 1.03 = 515 units

| GRN | Quantity | Result | Reason |
|-----|----------|--------|--------|
| GRN-001 | 500 | ✅ Success | Normal receipt |
| GRN-002 | 15 | ✅ Success (Warning) | Within tolerance (515 total) |
| GRN-003 | 1 | ❌ Error | Exceeds tolerance (516 > 515) |

#### 6.9.5 Partial Delivery Support

Both systems support **multiple partial deliveries**:

- **Sales Orders**: Multiple delivery notes can be created for the same sales order item
- **Purchase Orders**: Multiple GRNs can be created for the same purchase order item
- **Total Control**: System tracks total quantity across all delivery notes/GRNs (including DRAFTs)
- **Tolerance Applied**: Tolerance is applied to the **total delivered/received quantity**, not per delivery

#### 6.9.6 Service Implementation

**DeliveryNoteService:**
```php
protected function getOverDeliveryTolerance(SalesOrderItem $salesOrderItem): float
{
    // 1. Check SalesOrderItem level (most specific)
    if ($salesOrderItem->over_delivery_tolerance_percentage !== null) {
        return (float) $salesOrderItem->over_delivery_tolerance_percentage;
    }

    // 2. Check Product level
    $product = $salesOrderItem->product;
    if ($product && $product->over_delivery_tolerance_percentage !== null) {
        return (float) $product->over_delivery_tolerance_percentage;
    }

    // 3. Check Category level (primary category)
    if ($product) {
        $primaryCategory = $product->primaryCategory;
        if ($primaryCategory && $primaryCategory->over_delivery_tolerance_percentage !== null) {
            return (float) $primaryCategory->over_delivery_tolerance_percentage;
        }
    }

    // 4. Company default (company-specific, final fallback)
    $companyId = Auth::user()->company_id;
    $companyKey = "delivery.default_over_delivery_tolerance.{$companyId}";
    $companyDefault = Setting::get($companyKey, 0);
    
    $tolerance = is_array($companyDefault) ? (float) ($companyDefault[0] ?? 0) : (float) $companyDefault;
    return $tolerance;
    
    // Note: No system-level tolerance as this is a SaaS application.
    // Company-level is the final fallback.
}
```

**GoodsReceivedNoteService:**
- Same `getOverDeliveryTolerance()` method, but accepts `PurchaseOrderItem` instead

#### 6.9.7 Benefits

1. **Flexibility**: Different tolerance levels for different products/categories
2. **Control**: Prevents excessive over-delivery while allowing reasonable variations
3. **Hierarchy**: Most specific setting wins (item > product > category > system)
4. **Partial Delivery**: Supports multiple deliveries/receipts per order
5. **Audit Trail**: Warning logs when tolerance is used

#### 6.9.8 Use Cases

**High-Value Items (0% tolerance):**
- Electronics, precision instruments
- Set at Product or Category level

**Bulk Materials (2-5% tolerance):**
- Raw materials, chemicals, grains
- Set at Category level

**Special Orders (Item-level override):**
- Customer-specific tolerance for specific order
- Set at Sales Order Item level

**Company-Wide Default:**
- Company-specific tolerance for all items (e.g., 0% = strict, 5% = flexible)
- Set via API: `PUT /api/over-delivery-tolerance` (Admin only)
- Managed per company independently

**System-Wide Default:**
- Global fallback tolerance for all companies
- Set in System Settings (for companies without company-specific setting)

---

### 6.10 Manufacturing (Phase 5) - MRP II Core

Manufacturing modülü **MRP II (Manufacturing Resource Planning)** sisteminin çekirdeğidir. MRP II, klasik MRP'nin (Material Requirements Planning) gelişmiş versiyonudur ve aşağıdaki bileşenleri içerir:

#### 6.10.1 MRP II Components Overview

**Implemented Components:**

**1. Material Requirements Planning (MRP)**
- Multi-level BOM explosion
- Net requirement calculations
- Purchase order and work order recommendations
- Safety stock considerations
- Lead time respect

**2. Capacity Requirements Planning (CRP)**
- Work center capacity analysis
- Capacity load reports
- Bottleneck identification
- Calendar-based capacity planning
- Work center availability tracking

**3. Master Production Schedule (MPS)**
- Work Orders as production schedule
- Production planning horizon
- Production order prioritization
- Material availability checks

**4. Shop Floor Control (Atölye Kontrolü)**
- **Definition**: Shop Floor Control refers to the real-time monitoring and management of production activities on the manufacturing floor (shop floor). It bridges the gap between production planning (MPS/MRP) and actual execution.

- **Key Features**:
  - **Work Order Management**: Create, release, start, complete, cancel, and hold work orders
  - **Operation Tracking**: Track individual operations within a work order (start, complete, status)
  - **Material Consumption**: Issue materials from stock when production starts
  - **Finished Goods Receipt**: Receive completed products back into inventory
  - **Production Progress Tracking**: Monitor quantity completed vs. ordered
  - **Status Management**: Track work order and operation statuses (draft, released, in_progress, completed, cancelled, on_hold)
  - **Real-time Updates**: Actual start/end dates, actual quantities, scrap tracking
  - **Capacity Integration**: Check work center availability before starting operations

- **Workflow**:
  1. Work Order created (draft) → Material requirements calculated from BOM
  2. Work Order released → Materials automatically reserved
  3. Work Order started → Status changes to "in_progress", actual start date recorded
  4. Operations started/completed → Individual operation tracking
  5. Materials issued → Stock consumed for production
  6. Finished goods received → Completed products added to inventory
  7. Work Order completed → Status finalized, actual end date recorded

- **Integration Points**:
  - Links to MRP recommendations (work orders can be created from MRP)
  - Uses BOM for material requirements
  - Uses Routing for operation sequences
  - Integrates with Stock Service for material issuance and finished goods receipt
  - Capacity planning checks work center availability

**5. Bill of Materials (BOM) Management**
- Multi-level product structures
- BOM versioning and status management
- Component quantity calculations
- BOM explosion for MRP

**6. Routing Management**
- Manufacturing process definitions
- Operation sequences
- Work center assignments
- Setup and run times
- Lead time calculations

**7. Work Center Management**
- Production resource definitions
- Capacity definitions (hours per day)
- Efficiency tracking
- Basic cost per hour tracking (for operational planning)
- Availability calendars

**Not Implemented (Future Roadmap):**

**8. Financial Planning & Cost Management**
- **Status**: Not implemented - conscious design decision
- **Current Focus**: Planning and inventory management
- **Basic Cost Tracking Available**: 
  - Standard cost per product
  - Average cost calculation
  - Work center cost per hour (for operational planning)
  - Estimated vs actual cost in Work Orders (basic tracking)
- **Future Plans**: 
  - Comprehensive cost accounting
  - Financial planning and budgeting
  - Cost center management
  - Financial reporting and analysis
  - Integration with external accounting systems

**Design Philosophy:**
The system prioritizes **planning and inventory management** over financial integration. While basic cost tracking exists for operational purposes (e.g., standard costs, work center costs), comprehensive financial planning is deferred to future releases. This allows the system to focus on its core strengths: material planning, capacity planning, and inventory control.

#### Work Centers
```sql
work_centers
├── id (bigint, PK)
├── company_id (bigint, FK)
├── code (varchar(50), unique per company)
├── name (varchar(255))
├── description (text, nullable)
├── work_center_type (enum: machine, labor, subcontract, tool)
├── cost_per_hour (decimal(15,4), default: 0)
├── cost_currency (varchar(3), default: 'USD')
├── capacity_per_day (decimal(15,3), default: 8) -- Hours per day
├── efficiency_percentage (decimal(5,2), default: 100.00)
├── is_active (boolean, default: true)
├── settings (jsonb, nullable)
├── created_by (bigint, FK)
├── created_at (timestamp)
├── updated_at (timestamp)
└── deleted_at (timestamp, nullable)

INDEX idx_work_centers_active ON work_centers(company_id, is_active)
INDEX idx_work_centers_type ON work_centers(company_id, work_center_type)
```

#### BOMs (Bill of Materials Header)
```sql
boms
├── id (bigint, PK)
├── company_id (bigint, FK)
├── product_id (bigint, FK)
├── bom_number (varchar(50), unique per company)
├── version (integer, default: 1)
├── name (varchar(255))
├── description (text, nullable)
├── bom_type (enum: manufacturing, engineering, phantom)
├── status (enum: draft, active, obsolete)
├── quantity (decimal(15,4), default: 1) -- Base quantity
├── uom_id (bigint, FK)
├── is_default (boolean, default: false)
├── effective_date (date, nullable)
├── expiry_date (date, nullable)
├── notes (text, nullable)
├── meta_data (jsonb, nullable)
├── created_by (bigint, FK)
├── created_at (timestamp)
├── updated_at (timestamp)
└── deleted_at (timestamp, nullable)

INDEX idx_boms_product ON boms(company_id, product_id)
INDEX idx_boms_status ON boms(company_id, status)
INDEX idx_boms_default ON boms(product_id, is_default)
```

#### BOM Items (Components)
```sql
bom_items
├── id (bigint, PK)
├── bom_id (bigint, FK)
├── component_id (bigint, FK to products)
├── line_number (integer, default: 1)
├── quantity (decimal(15,4))
├── uom_id (bigint, FK)
├── scrap_percentage (decimal(5,2), default: 0)
├── is_optional (boolean, default: false)
├── is_phantom (boolean, default: false) -- Pass-through item
├── notes (text, nullable)
├── created_at (timestamp)
└── updated_at (timestamp)

UNIQUE idx_bom_component ON bom_items(bom_id, component_id)
INDEX idx_bom_items_line ON bom_items(bom_id, line_number)
```

#### Routings (Header)
```sql
routings
├── id (bigint, PK)
├── company_id (bigint, FK)
├── product_id (bigint, FK)
├── routing_number (varchar(50), unique per company)
├── version (integer, default: 1)
├── name (varchar(255))
├── description (text, nullable)
├── status (enum: draft, active, obsolete)
├── is_default (boolean, default: false)
├── effective_date (date, nullable)
├── expiry_date (date, nullable)
├── notes (text, nullable)
├── meta_data (jsonb, nullable)
├── created_by (bigint, FK)
├── created_at (timestamp)
├── updated_at (timestamp)
└── deleted_at (timestamp, nullable)

INDEX idx_routings_product ON routings(company_id, product_id)
INDEX idx_routings_status ON routings(company_id, status)
```

#### Routing Operations
```sql
routing_operations
├── id (bigint, PK)
├── routing_id (bigint, FK)
├── work_center_id (bigint, FK)
├── operation_number (integer)
├── name (varchar(255))
├── description (text, nullable)
├── setup_time (decimal(10,2), default: 0) -- Minutes
├── run_time_per_unit (decimal(10,4), default: 0) -- Minutes
├── queue_time (decimal(10,2), default: 0) -- Wait before operation
├── move_time (decimal(10,2), default: 0) -- Move to next operation
├── is_subcontracted (boolean, default: false)
├── subcontractor_id (bigint, FK to suppliers, nullable)
├── subcontract_cost (decimal(15,4), nullable)
├── instructions (text, nullable)
├── settings (jsonb, nullable)
├── created_at (timestamp)
└── updated_at (timestamp)

UNIQUE idx_routing_op ON routing_operations(routing_id, operation_number)
INDEX idx_routing_ops_wc ON routing_operations(work_center_id)
```

#### Work Orders (Production Orders)
```sql
work_orders
├── id (bigint, PK)
├── company_id (bigint, FK)
├── work_order_number (varchar(50), unique per company)
├── product_id (bigint, FK)
├── bom_id (bigint, FK, nullable)
├── routing_id (bigint, FK, nullable)
├── quantity_ordered (decimal(15,3))
├── quantity_completed (decimal(15,3), default: 0)
├── quantity_scrapped (decimal(15,3), default: 0)
├── uom_id (bigint, FK)
├── warehouse_id (bigint, FK) -- Finished goods destination
├── status (enum: draft, released, in_progress, completed, cancelled, on_hold)
├── priority (enum: low, normal, high, urgent)
├── planned_start_date (datetime, nullable)
├── planned_end_date (datetime, nullable)
├── actual_start_date (datetime, nullable)
├── actual_end_date (datetime, nullable)
├── estimated_cost (decimal(15,4), default: 0)
├── actual_cost (decimal(15,4), default: 0)
├── notes (text, nullable)
├── internal_notes (text, nullable)
├── meta_data (jsonb, nullable)
├── created_by (bigint, FK)
├── approved_by (bigint, FK, nullable)
├── approved_at (timestamp, nullable)
├── released_by (bigint, FK, nullable)
├── released_at (timestamp, nullable)
├── completed_by (bigint, FK, nullable)
├── completed_at (timestamp, nullable)
├── created_at (timestamp)
├── updated_at (timestamp)
└── deleted_at (timestamp, nullable)

INDEX idx_wo_status ON work_orders(company_id, status)
INDEX idx_wo_product ON work_orders(company_id, product_id)
INDEX idx_wo_priority ON work_orders(company_id, priority, status)
INDEX idx_wo_dates ON work_orders(planned_start_date, planned_end_date)
```

#### Work Order Operations
```sql
work_order_operations
├── id (bigint, PK)
├── work_order_id (bigint, FK)
├── routing_operation_id (bigint, FK, nullable)
├── work_center_id (bigint, FK)
├── operation_number (integer)
├── name (varchar(255))
├── description (text, nullable)
├── status (enum: pending, in_progress, completed, skipped)
├── quantity_completed (decimal(15,3), default: 0)
├── quantity_scrapped (decimal(15,3), default: 0)
├── planned_start (datetime, nullable)
├── planned_end (datetime, nullable)
├── actual_start (datetime, nullable)
├── actual_end (datetime, nullable)
├── actual_setup_time (decimal(10,2), default: 0) -- Minutes
├── actual_run_time (decimal(10,2), default: 0) -- Minutes
├── actual_cost (decimal(15,4), default: 0)
├── notes (text, nullable)
├── started_by (bigint, FK, nullable)
├── completed_by (bigint, FK, nullable)
├── created_at (timestamp)
└── updated_at (timestamp)

UNIQUE idx_wo_op ON work_order_operations(work_order_id, operation_number)
INDEX idx_wo_ops_status ON work_order_operations(work_order_id, status)
INDEX idx_wo_ops_wc ON work_order_operations(work_center_id)
```

#### Work Order Materials (Material Consumption)
```sql
work_order_materials
├── id (bigint, PK)
├── work_order_id (bigint, FK)
├── product_id (bigint, FK)
├── bom_item_id (bigint, FK, nullable)
├── quantity_required (decimal(15,4))
├── quantity_issued (decimal(15,4), default: 0)
├── quantity_returned (decimal(15,4), default: 0)
├── uom_id (bigint, FK)
├── warehouse_id (bigint, FK)
├── unit_cost (decimal(15,4), default: 0)
├── total_cost (decimal(15,4), default: 0)
├── notes (text, nullable)
├── created_at (timestamp)
└── updated_at (timestamp)

INDEX idx_wo_materials ON work_order_materials(work_order_id, product_id)
```

### 6.10 Manufacturing Enums

#### WorkCenterType
```
machine     - Machine-based (CNC, lathe, etc.)
labor       - Labor-intensive (assembly, inspection)
subcontract - Outsourced operations
tool        - Tool or equipment based
```

#### BomStatus / RoutingStatus
```
draft    - Can be edited
active   - Can be used for production
obsolete - No longer in use
```

#### WorkOrderStatus
```
draft       → released → in_progress → completed
                      ↘ on_hold ↗
         → cancelled
```

#### WorkOrderPriority
```
low, normal, high, urgent
```

#### OperationStatus
```
pending → in_progress → completed
                    → skipped
```

### 6.11 Manufacturing Services

#### WorkCenterService
- CRUD operations
- Capacity calculation
- Availability check

#### BomService
- CRUD for BOM and items
- Version management
- Copy/clone BOM
- **explodeBom()** - Multi-level BOM explosion
- **calculateMaterialRequirements()** - Material calculation
- **validateBomItems()** - Circular reference check

```php
// BOM Explosion Algorithm
public function explodeBom(Bom $bom, float $quantity = 1, int $level = 0): array
{
    $materials = [];
    foreach ($bom->items as $item) {
        $requiredQty = $item->quantity * $quantity * (1 + $item->scrap_percentage/100);

        if ($item->is_phantom && $item->component->defaultBom) {
            // Recursive explosion for phantom items
            $childBom = $item->component->defaultBom;
            $childMaterials = $this->explodeBom($childBom, $requiredQty, $level + 1);
            $materials = array_merge($materials, $childMaterials);
        } else {
            $materials[] = [
                'product_id' => $item->component_id,
                'quantity' => $requiredQty,
                'level' => $level,
            ];
        }
    }
    return $materials;
}
```

#### RoutingService
- CRUD for Routing and operations
- Calculate total lead time
- Clone routing

#### WorkOrderService
- **createFromBom()** - Create from BOM + Routing
- **release()** - Release for production
- **start() / complete()** - Status transitions
- **issueMaterials()** - Material consumption (stock issue)
- **receiveFinishedGoods()** - Finished goods receipt (stock receive)
- **calculateCosts()** - Cost calculation
- **getProgress()** - Progress tracking

```php
// Material Issuance Flow
public function issueMaterials(WorkOrder $workOrder): void
{
    // 1. Get required materials from work_order_materials
    // 2. Check stock availability (quality_status = 'available')
    // 3. Issue stock (create stock movement: issue)
    // 4. Update work_order_materials.quantity_issued
}

// Finished Goods Receipt Flow
public function receiveFinishedGoods(WorkOrder $workOrder, float $quantity): void
{
    // 1. Validate quantity <= quantity_ordered - quantity_completed
    // 2. Receive stock (create stock movement: production_output)
    // 3. Update work_order.quantity_completed
    // 4. If complete, update status to 'completed'
}
```

### 6.12 Manufacturing API Routes

See Section 10.8 for complete Manufacturing module endpoint documentation.

### 6.13 Manufacturing Permissions

```
manufacturing.view      - View work centers, BOMs, routings, work orders
manufacturing.create    - Create new records
manufacturing.edit      - Edit existing records
manufacturing.delete    - Delete records
manufacturing.release   - Release work orders for production
manufacturing.complete  - Complete operations and work orders
```

---

## 7. Internationalization

### 7.1 Strategy Overview

**🎯 Approach: Frontend i18n + Backend Multi-currency**

```
┌─────────────────────────────────────────────────┐
│ FRONTEND (React + react-i18next)               │
│ ✅ UI Labels, Buttons, Menus                    │
│ ✅ Form Labels, Validation Messages            │
│ ✅ Help Text, Tooltips                         │
│ ✅ Notifications, Alerts                       │
│ Translation files: public/locales/{lang}/       │
└─────────────────────────────────────────────────┘
                      ↓
┌─────────────────────────────────────────────────┐
│ BACKEND (Laravel API)                           │
│ ✅ Single Language Data (user input)           │
│ ✅ Multi-currency Support                      │
│ ✅ Currency Conversion                         │
│ ❌ NO translation tables                       │
└─────────────────────────────────────────────────┘
```

### 7.2 Currencies

```sql
currencies
├── id (bigint, PK)
├── code (varchar(3), unique) -- ISO 4217: USD, EUR, TRY, GBP
├── name (varchar(100)) -- US Dollar, Euro, Turkish Lira
├── symbol (varchar(10)) -- $, €, ₺, £
├── decimal_places (integer, default: 2)
├── thousands_separator (varchar(1), default: ',')
├── decimal_separator (varchar(1), default: '.')
├── is_active (boolean, default: true)
└── created_at (timestamp)
```

### 7.3 Exchange Rates

```sql
exchange_rates
├── id (bigint, PK)
├── from_currency (varchar(3), FK)
├── to_currency (varchar(3), FK)
├── rate (decimal(15,6))
├── effective_date (date)
├── source (varchar(50)) -- 'manual', 'api', 'central_bank'
├── created_by (bigint, FK, nullable)
└── created_at (timestamp)

UNIQUE idx_exchange_rate ON exchange_rates(from_currency, to_currency, effective_date)
INDEX idx_rate_date ON exchange_rates(effective_date DESC)
```

### 7.4 Frontend i18n Setup

**React i18next Structure:**
```
frontend/
├── public/
│   └── locales/
│       ├── en/
│       │   └── translation.json
│       ├── tr/
│       │   └── translation.json
│       ├── de/
│       │   └── translation.json
│       └── fr/
│           └── translation.json
└── src/
    ├── i18n/
    │   └── config.js
    └── components/
        └── LanguageSwitcher.jsx
```

**Example Translation File (en):**
```json
{
  "nav": {
    "products": "Products",
    "categories": "Categories",
    "orders": "Orders"
  },
  "product": {
    "form": {
      "sku": "SKU",
      "name": "Product Name",
      "description": "Description"
    },
    "actions": {
      "save": "Save",
      "cancel": "Cancel"
    }
  }
}
```

### 7.5 What Gets Translated vs. What Doesn't

**✅ Frontend Translations (react-i18next):**
- UI labels, button text
- Form field labels
- Validation messages
- Menu items, navigation
- Help text, tooltips
- Success/error messages

**❌ Backend Data (NO translation tables):**
- Product names (stored as user enters: "Dell XPS 15")
- Product descriptions (user input language)
- Category names (user input)
- Customer/Supplier names (user input)
- Notes, comments (user input)
- SKU, codes (language-independent)

**✅ Backend Multi-currency:**
- Price conversion via exchange_rates
- Currency formatting
- Historical rate tracking

---

## 8. Support Systems

### 8.1 Activity Logging

```sql
activity_logs
├── id (bigint, PK)
├── company_id (bigint, FK)
├── user_id (bigint, FK, nullable)
├── log_type (enum: user_action, system_event, security, data_change)
├── module (varchar(50)) -- 'products', 'orders', 'inventory'
├── action (varchar(100)) -- 'created', 'updated', 'deleted'
├── subject_type (varchar(100)) -- Model class name
├── subject_id (bigint, nullable)
├── description (text)
├── ip_address (inet, nullable)
├── old_values (jsonb, nullable)
├── new_values (jsonb, nullable)
└── created_at (timestamp)

INDEX idx_activity_user ON activity_logs(user_id, created_at DESC)
INDEX idx_activity_subject ON activity_logs(subject_type, subject_id)
```

### 8.2 Error Logging

```sql
error_logs
├── id (bigint, PK)
├── company_id (bigint, FK, nullable)
├── user_id (bigint, FK, nullable)
├── error_type (enum: exception, validation, database, api, system)
├── severity (enum: debug, info, warning, error, critical)
├── message (text)
├── exception_class (varchar(255), nullable)
├── file_path (text, nullable)
├── line_number (integer, nullable)
├── stack_trace (text, nullable)
├── context (jsonb, nullable)
├── resolved (boolean, default: false)
├── resolved_by (bigint, FK, nullable)
└── created_at (timestamp)

INDEX idx_errors_severity ON error_logs(severity, resolved, created_at DESC)
```

### 8.3 Notifications

```sql
notifications
├── id (bigint, PK)
├── company_id (bigint, FK)
├── user_id (bigint, FK)
├── notification_type (varchar(100))
├── channel (enum: database, email, sms)
├── priority (enum: low, normal, high, urgent)
├── title (varchar(255))
├── message (text)
├── data (jsonb, nullable)
├── read_at (timestamp, nullable)
└── created_at (timestamp)

INDEX idx_notifications_user ON notifications(user_id, read_at)
```

### 8.4 System Settings

```sql
system_settings
├── id (bigint, PK)
├── company_id (bigint, FK, nullable) -- NULL = global
├── category (varchar(50))
├── key (varchar(100))
├── value (text)
├── data_type (enum: string, integer, boolean, json, decimal)
├── is_editable (boolean, default: true)
├── updated_by (bigint, FK, nullable)
├── created_at (timestamp)
└── updated_at (timestamp)

UNIQUE idx_settings_key ON system_settings(company_id, key)
```

---

## 9. Search & Performance

### 9.1 Elasticsearch Integration

**Indexed Models:**
- Products (name, sku, description - single language)
- Customers
- Suppliers
- Orders

**Product Index Mapping (Simplified):**
```json
{
  "mappings": {
    "properties": {
      "id": {"type": "long"},
      "company_id": {"type": "long"},
      "sku": {"type": "keyword"},
      "name": {
        "type": "text",
        "analyzer": "standard",
        "fields": {
          "fuzzy": {
            "type": "text",
            "analyzer": "trigram"
          }
        }
      },
      "description": {"type": "text"},
      "prices": {
        "type": "nested",
        "properties": {
          "currency": {"type": "keyword"},
          "amount": {"type": "scaled_float", "scaling_factor": 100}
        }
      },
      "category": {"type": "keyword"},
      "status": {"type": "keyword"}
    }
  }
}
```

**Features:**
- Fuzzy search (typo tolerance)
- Autocomplete
- Faceted filtering
- Relevance scoring

### 9.2 Redis Caching

**Cache Strategy:**
```php
// Product (no translation caching needed)
Cache::tags(['products', "product:{$id}"])
    ->remember("product:{$id}", 600, fn() =>
        Product::find($id)
    );

// Exchange rates (daily)
Cache::remember('exchange_rates:' . $date, 86400, fn() =>
    ExchangeRate::where('effective_date', $date)->get()
);

// Category tree
Cache::tags(['categories'])
    ->remember('categories:tree', 3600, fn() =>
        Category::get()->toTree()
    );
```

### 9.3 Database Optimization

**Indexes:**
```sql
-- B-tree indexes
CREATE INDEX idx_products_sku ON products(company_id, sku);
CREATE INDEX idx_products_name ON products(name); -- Full-text search

-- GIN indexes for JSONB
CREATE INDEX idx_product_details_specs ON product_details USING GIN(specifications);

-- GIST indexes for ltree
CREATE INDEX idx_categories_path ON categories USING GIST(path);

-- Partial indexes
CREATE INDEX idx_active_products ON products(id) WHERE status = 'active' AND deleted_at IS NULL;
```

---

## 10. API Structure

### 10.1 API Base URL
```
/api/...
```

**Note:** API versioning (`/api/v1/`) is not currently implemented. All endpoints use `/api/` prefix.

### 10.2 Authentication Endpoints

```
POST   /api/auth/register
POST   /api/auth/login
POST   /api/auth/logout
GET    /api/auth/me
POST   /api/auth/refresh
POST   /api/auth/forgot-password
POST   /api/auth/reset-password
```

### 10.3 Core System Endpoints

**Users:**
```
GET    /api/users
POST   /api/users
GET    /api/users/{id}
PUT    /api/users/{id}
DELETE /api/users/{id}
POST   /api/users/{id}/restore
DELETE /api/users/{id}/force
```

**Roles & Permissions:**
```
GET    /api/roles
POST   /api/roles
GET    /api/roles/{id}
PUT    /api/roles/{id}
DELETE /api/roles/{id}
POST   /api/roles/{id}/permissions/assign
POST   /api/roles/{id}/permissions/revoke

GET    /api/permissions
POST   /api/permissions
GET    /api/permissions/{id}
PUT    /api/permissions/{id}
DELETE /api/permissions/{id}
GET    /api/permissions/modules/list
```

**Settings:**
```
GET    /api/settings
POST   /api/settings
GET    /api/settings/groups
GET    /api/settings/group/{group}
GET    /api/settings/{group}/{key}
PUT    /api/settings/{group}/{key}
DELETE /api/settings/{group}/{key}
```

**Over-Delivery Tolerance:**
```
GET    /api/over-delivery-tolerance
PUT    /api/over-delivery-tolerance
GET    /api/over-delivery-tolerance/levels
```

**Company Calendar:**
```
GET    /api/company-calendar
POST   /api/company-calendar
POST   /api/company-calendar/bulk
GET    /api/company-calendar/date-range
GET    /api/company-calendar/{id}
PUT    /api/company-calendar/{id}
DELETE /api/company-calendar/{id}
```

**Currencies:**
```
GET    /api/currencies
POST   /api/currencies
GET    /api/currencies/active
GET    /api/currencies/{id}
PUT    /api/currencies/{id}
DELETE /api/currencies/{id}
POST   /api/currencies/{id}/toggle-active
GET    /api/currencies/exchange-rate/get
GET    /api/currencies/exchange-rate/history
POST   /api/currencies/exchange-rate/set
POST   /api/currencies/convert
```

**Units of Measure:**
```
GET    /api/units-of-measure
POST   /api/units-of-measure
GET    /api/units-of-measure/list
GET    /api/units-of-measure/types
GET    /api/units-of-measure/{id}
PUT    /api/units-of-measure/{id}
DELETE /api/units-of-measure/{id}
```

### 10.4 Product Management Endpoints

**Categories:**
```
GET    /api/categories
POST   /api/categories
GET    /api/categories/{id}
PUT    /api/categories/{id}
DELETE /api/categories/{id}
GET    /api/categories/{id}/attributes
POST   /api/categories/{id}/attributes
PUT    /api/categories/{id}/attributes/{attributeId}
DELETE /api/categories/{id}/attributes/{attributeId}
```

**Attributes:**
```
GET    /api/attributes
POST   /api/attributes
GET    /api/attributes/{id}
PUT    /api/attributes/{id}
DELETE /api/attributes/{id}
POST   /api/attributes/{id}/values
PUT    /api/attributes/{id}/values/{valueId}
DELETE /api/attributes/{id}/values/{valueId}
POST   /api/variants/bulk-generate
```

**Product Types:**
```
GET    /api/producttypes
POST   /api/producttypes
GET    /api/producttypes/{id}
PUT    /api/producttypes/{id}
DELETE /api/producttypes/{id}
```

**Products:**
```
GET    /api/products
POST   /api/products
GET    /api/products/search
GET    /api/products/{id}
PUT    /api/products/{id}
DELETE /api/products/{id}
POST   /api/products/{id}/restore

# Product Attributes
GET    /api/products/{id}/attributes
POST   /api/products/{id}/attributes
PUT    /api/products/{id}/attributes/{attributeId}
DELETE /api/products/{id}/attributes/{attributeId}

# Product Images
POST   /api/products/{id}/images
PUT    /api/products/{id}/images/{imageId}
DELETE /api/products/{id}/images/{imageId}
POST   /api/products/{id}/images/reorder

# Product Variants
GET    /api/products/{id}/variants
POST   /api/products/{id}/variants
POST   /api/products/{id}/variants/generate
POST   /api/products/{id}/variants/expand
PUT    /api/products/{id}/variants/{variantId}
DELETE /api/products/{id}/variants/{variantId}
DELETE /api/products/{id}/variants/clear
DELETE /api/products/{id}/variants/{variantId}/force
DELETE /api/products/{id}/variants/force-clear

# Product UOM Conversions
GET    /api/products/{id}/uom-conversions
POST   /api/products/{id}/uom-conversions
POST   /api/products/{id}/uom-conversions/bulk
POST   /api/products/{id}/uom-conversions/copy-from
GET    /api/products/{id}/uom-conversions/{conversionId}
PUT    /api/products/{id}/uom-conversions/{conversionId}
DELETE /api/products/{id}/uom-conversions/{conversionId}
POST   /api/products/{id}/uom-conversions/{conversionId}/toggle-active
POST   /api/products/{id}/uom-conversions/convert
```

### 10.5 Inventory Management Endpoints

**Warehouses:**
```
GET    /api/warehouses
POST   /api/warehouses
GET    /api/warehouses/list
GET    /api/warehouses/quarantine-zones
GET    /api/warehouses/rejection-zones
GET    /api/warehouses/qc-zones
GET    /api/warehouses/{id}
PUT    /api/warehouses/{id}
DELETE /api/warehouses/{id}
POST   /api/warehouses/{id}/toggle-active
POST   /api/warehouses/{id}/set-default
GET    /api/warehouses/{id}/stock-summary
```

**Stock:**
```
GET    /api/stock
GET    /api/stock/low-stock
GET    /api/stock/expiring
GET    /api/stock/product/{productId}
GET    /api/stock/warehouse/{warehouseId}
POST   /api/stock/receive
POST   /api/stock/issue
POST   /api/stock/transfer
POST   /api/stock/adjust
POST   /api/stock/reserve
POST   /api/stock/release-reservation
```

**Stock Movements:**
```
GET    /api/stock-movements
GET    /api/stock-movements/summary
GET    /api/stock-movements/daily-report
GET    /api/stock-movements/audit-trail
GET    /api/stock-movements/product/{productId}
GET    /api/stock-movements/warehouse/{warehouseId}
GET    /api/stock-movements/types/movement
GET    /api/stock-movements/types/transaction
```

**Stock Debts (Negative Stock):**
```
GET    /api/stock-debts
GET    /api/stock-debts/{id}
GET    /api/stock-debts/alerts
GET    /api/stock-debts/weekly-report
GET    /api/stock-debts/long-term
```

### 10.6 Procurement Module Endpoints

**Suppliers:**
```
GET    /api/suppliers
POST   /api/suppliers
GET    /api/suppliers/list
GET    /api/suppliers/{id}
PUT    /api/suppliers/{id}
DELETE /api/suppliers/{id}
POST   /api/suppliers/{id}/toggle-active
GET    /api/suppliers/{id}/statistics
GET    /api/suppliers/for-product/{productId}
POST   /api/suppliers/{id}/products
PUT    /api/suppliers/{id}/products/{productId}
DELETE /api/suppliers/{id}/products/{productId}

# Supplier Quality (requires QC module)
GET    /api/suppliers/quality-ranking
GET    /api/suppliers/{id}/quality-score
GET    /api/suppliers/{id}/quality-statistics
```

**Purchase Orders:**
```
GET    /api/purchase-orders
POST   /api/purchase-orders
GET    /api/purchase-orders/statistics
GET    /api/purchase-orders/overdue
GET    /api/purchase-orders/{id}
PUT    /api/purchase-orders/{id}
DELETE /api/purchase-orders/{id}
POST   /api/purchase-orders/{id}/items
PUT    /api/purchase-orders/{id}/items/{itemId}
DELETE /api/purchase-orders/{id}/items/{itemId}
POST   /api/purchase-orders/{id}/submit
POST   /api/purchase-orders/{id}/approve
POST   /api/purchase-orders/{id}/reject
POST   /api/purchase-orders/{id}/send
POST   /api/purchase-orders/{id}/cancel
POST   /api/purchase-orders/{id}/close
```

**Goods Received Notes (GRN):**
```
GET    /api/goods-received-notes
POST   /api/goods-received-notes
GET    /api/goods-received-notes/pending-inspection
GET    /api/goods-received-notes/for-purchase-order/{purchaseOrderId}
GET    /api/goods-received-notes/{id}
PUT    /api/goods-received-notes/{id}
DELETE /api/goods-received-notes/{id}
POST   /api/goods-received-notes/{id}/submit-inspection
POST   /api/goods-received-notes/{id}/record-inspection
POST   /api/goods-received-notes/{id}/complete
POST   /api/goods-received-notes/{id}/cancel
```

### 10.7 Quality Control Module Endpoints

**Acceptance Rules:**
```
GET    /api/acceptance-rules
POST   /api/acceptance-rules
GET    /api/acceptance-rules/list
GET    /api/acceptance-rules/inspection-types
GET    /api/acceptance-rules/sampling-methods
POST   /api/acceptance-rules/find-applicable
GET    /api/acceptance-rules/{id}
PUT    /api/acceptance-rules/{id}
DELETE /api/acceptance-rules/{id}
```

**Receiving Inspections:**
```
GET    /api/receiving-inspections
GET    /api/receiving-inspections/statistics
GET    /api/receiving-inspections/results
GET    /api/receiving-inspections/dispositions
GET    /api/receiving-inspections/for-grn/{grnId}
GET    /api/receiving-inspections/{id}
POST   /api/receiving-inspections/create-for-grn/{grnId}
POST   /api/receiving-inspections/{id}/record-result
POST   /api/receiving-inspections/{id}/approve
PUT    /api/receiving-inspections/{id}/disposition
POST   /api/receiving-inspections/{id}/transfer-to-qc
```

**Non-Conformance Reports (NCR):**
```
GET    /api/ncrs
POST   /api/ncrs
GET    /api/ncrs/statistics
GET    /api/ncrs/statuses
GET    /api/ncrs/severities
GET    /api/ncrs/defect-types
GET    /api/ncrs/dispositions
GET    /api/ncrs/supplier/{supplierId}/summary
GET    /api/ncrs/{id}
PUT    /api/ncrs/{id}
DELETE /api/ncrs/{id}
POST   /api/ncrs/from-inspection/{inspectionId}
POST   /api/ncrs/{id}/submit-review
POST   /api/ncrs/{id}/complete-review
POST   /api/ncrs/{id}/start-progress
POST   /api/ncrs/{id}/set-disposition
POST   /api/ncrs/{id}/close
POST   /api/ncrs/{id}/cancel
```

### 10.8 Manufacturing Module Endpoints

**Work Centers:**
```
GET    /api/work-centers
POST   /api/work-centers
GET    /api/work-centers/list
GET    /api/work-centers/types
GET    /api/work-centers/{id}
GET    /api/work-centers/{id}/availability
PUT    /api/work-centers/{id}
DELETE /api/work-centers/{id}
POST   /api/work-centers/{id}/toggle-active
```

**BOMs (Bill of Materials):**
```
GET    /api/boms
POST   /api/boms
GET    /api/boms/list
GET    /api/boms/types
GET    /api/boms/statuses
GET    /api/boms/for-product/{productId}
GET    /api/boms/{id}
PUT    /api/boms/{id}
DELETE /api/boms/{id}
POST   /api/boms/{id}/items
PUT    /api/boms/{id}/items/{itemId}
DELETE /api/boms/{id}/items/{itemId}
POST   /api/boms/{id}/activate
POST   /api/boms/{id}/obsolete
POST   /api/boms/{id}/set-default
POST   /api/boms/{id}/copy
GET    /api/boms/{id}/explode
```

**Routings:**
```
GET    /api/routings
POST   /api/routings
GET    /api/routings/list
GET    /api/routings/statuses
GET    /api/routings/for-product/{productId}
GET    /api/routings/{id}
PUT    /api/routings/{id}
DELETE /api/routings/{id}
POST   /api/routings/{id}/operations
PUT    /api/routings/{id}/operations/{operationId}
DELETE /api/routings/{id}/operations/{operationId}
POST   /api/routings/{id}/operations/reorder
POST   /api/routings/{id}/activate
POST   /api/routings/{id}/obsolete
POST   /api/routings/{id}/set-default
POST   /api/routings/{id}/copy
POST   /api/routings/{id}/calculate-lead-time
```

**Work Orders:**
```
GET    /api/work-orders
POST   /api/work-orders
GET    /api/work-orders/statistics
GET    /api/work-orders/statuses
GET    /api/work-orders/priorities
GET    /api/work-orders/{id}
PUT    /api/work-orders/{id}
DELETE /api/work-orders/{id}
POST   /api/work-orders/{id}/release
POST   /api/work-orders/{id}/start
POST   /api/work-orders/{id}/complete
POST   /api/work-orders/{id}/cancel
POST   /api/work-orders/{id}/hold
POST   /api/work-orders/{id}/resume
GET    /api/work-orders/{id}/material-requirements
POST   /api/work-orders/{id}/issue-materials
POST   /api/work-orders/{id}/receive-finished-goods
POST   /api/work-orders/{id}/operations/{operationId}/start
POST   /api/work-orders/{id}/operations/{operationId}/complete
GET    /api/work-orders/{id}/check-capacity
```

**MRP (Material Requirements Planning) - MRP II Component:**
```
GET    /api/mrp
POST   /api/mrp
GET    /api/mrp/statistics
GET    /api/mrp/statuses
GET    /api/mrp/recommendation-types
GET    /api/mrp/recommendation-statuses
GET    /api/mrp/priorities
GET    /api/mrp/products-needing-attention
GET    /api/mrp/{id}
GET    /api/mrp/{id}/progress
GET    /api/mrp/{id}/recommendations
POST   /api/mrp/{id}/cancel
POST   /api/mrp/invalidate-cache
POST   /api/mrp/recommendations/{id}/approve
POST   /api/mrp/recommendations/{id}/reject
POST   /api/mrp/recommendations/bulk-approve
POST   /api/mrp/recommendations/bulk-reject
```

**Capacity Requirements Planning (CRP) - MRP II Component:**
```
GET    /api/capacity/overview
GET    /api/capacity/load-report
GET    /api/capacity/bottleneck-analysis
GET    /api/capacity/day-types
GET    /api/capacity/work-center/{id}
GET    /api/capacity/work-center/{id}/daily
GET    /api/capacity/work-center/{id}/find-slot
GET    /api/capacity/work-center/{id}/calendar
POST   /api/capacity/generate-calendar
POST   /api/capacity/work-center/{id}/set-holiday
POST   /api/capacity/work-center/{id}/set-maintenance
PUT    /api/capacity/calendar/{id}
```

### 10.9 Sales Module Endpoints

**Customer Groups:**
```
GET    /api/customer-groups
POST   /api/customer-groups
GET    /api/customer-groups/list
GET    /api/customer-groups/{id}
PUT    /api/customer-groups/{id}
DELETE /api/customer-groups/{id}
GET    /api/customer-groups/{id}/prices
POST   /api/customer-groups/{id}/prices
POST   /api/customer-groups/{id}/prices/bulk
DELETE /api/customer-groups/{id}/prices/{priceId}
```

**Customers:**
```
GET    /api/customers
POST   /api/customers
GET    /api/customers/list
GET    /api/customers/{id}
PUT    /api/customers/{id}
DELETE /api/customers/{id}
GET    /api/customers/{id}/statistics
```

**Sales Orders:**
```
GET    /api/sales-orders
POST   /api/sales-orders
GET    /api/sales-orders/statistics
GET    /api/sales-orders/statuses
GET    /api/sales-orders/{id}
PUT    /api/sales-orders/{id}
DELETE /api/sales-orders/{id}
POST   /api/sales-orders/{id}/submit-for-approval
POST   /api/sales-orders/{id}/approve
POST   /api/sales-orders/{id}/reject
POST   /api/sales-orders/{id}/confirm
POST   /api/sales-orders/{id}/cancel
POST   /api/sales-orders/{id}/mark-as-shipped
POST   /api/sales-orders/{id}/mark-as-delivered
```

**Delivery Notes:**
```
GET    /api/delivery-notes
POST   /api/delivery-notes
GET    /api/delivery-notes/statuses
GET    /api/delivery-notes/for-sales-order/{salesOrderId}
GET    /api/delivery-notes/{id}
PUT    /api/delivery-notes/{id}
DELETE /api/delivery-notes/{id}
POST   /api/delivery-notes/{id}/confirm
POST   /api/delivery-notes/{id}/ship
POST   /api/delivery-notes/{id}/mark-as-delivered
POST   /api/delivery-notes/{id}/cancel
```

### 10.10 Module Status Endpoints

**Public (No Authentication):**
```
GET    /api/modules
```

**Protected:**
```
POST   /api/modules/clear-cache
```

### 10.3 Request Headers

```
Currency: TRY
Authorization: Bearer {token}
```

**Note:** No Accept-Language header needed for data.
UI language handled by frontend.

### 10.4 Response Format

```json
{
  "success": true,
  "data": {
    "id": 123,
    "sku": "LAPTOP-001",
    "name": "Dell XPS 15",
    "price": {
      "amount": 41400.00,
      "currency": "TRY",
      "formatted": "₺41,400.00"
    },
    "stock_available": 15
  },
  "meta": {
    "currency": "TRY"
  }
}
```

---

## 11. Security & Authorization

### 11.1 Role-Based Access Control

| Module | Admin | Manager | Purchaser | Warehouse | Sales | Viewer |
|--------|-------|---------|-----------|-----------|-------|--------|
| Users | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Products: Manage | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Products: View | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Product Types: Manage | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Product Types: View | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Categories: Manage | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Categories: View | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Purchase Orders | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Sales Orders | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ |
| Stock Adjust | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ |
| Reports | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ |

### 11.2 Security Measures

1. **Authentication**: Laravel Sanctum (API tokens)
2. **Password**: bcrypt hashing
3. **SQL Injection**: Eloquent ORM (parameterized queries)
4. **XSS**: Output escaping
5. **CSRF**: Token validation
6. **Rate Limiting**: Throttle middleware
7. **CORS**: Configured per environment
8. **HTTPS**: Enforced in production

---

## 12. Architecture Best Practices

### 12.1 Simplified Models (No Translations)

**Product Model:**
```php
class Product extends Model
{
    use HasFactory, SoftDeletes, Searchable;

    protected $fillable = [
        'company_id', 'product_type_id', 'sku', 'slug', 'uom_id',
        'name', 'description', 'short_description',
        'track_lot_number', 'track_serial_number', 'reorder_point',
        'standard_cost', 'base_currency', 'status',
        'is_active', 'is_featured', 'meta_data'
    ];

    // ❌ NO translations() relationship
    // ❌ NO getNameAttribute() accessor
    // ❌ NO withTranslation() scope

    // Many-to-many relationship with categories
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_product')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    // Get primary category
    public function primaryCategory()
    {
        return $this->belongsToMany(Category::class, 'category_product')
            ->wherePivot('is_primary', true)
            ->limit(1);
    }

    // Accessor for primary category
    public function getPrimaryCategoryAttribute()
    {
        return $this->categories()->wherePivot('is_primary', true)->first();
    }

    public function prices()
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function media()
    {
        return $this->morphMany(Media::class, 'mediable');
    }
}
```

### 12.2 Service Layer Pattern

**ProductService:**
```php
class ProductService
{
    public function createProduct(CreateProductDTO $dto): Product
    {
        DB::beginTransaction();

        try {
            // Create product (single language)
            $product = Product::create([
                'company_id' => auth()->user()->company_id,
                'sku' => $dto->sku,
                'name' => $dto->name, // Direct value
                'description' => $dto->description, // Direct value
                'category_id' => $dto->categoryId,
            ]);

            // Create prices (multi-currency)
            if ($dto->prices) {
                $this->pricingService->createPrices($product, $dto->prices);
            }

            // Upload media
            if ($dto->media) {
                $this->mediaService->attachMedia($product, $dto->media);
            }

            DB::commit();

            Cache::tags(['products'])->flush();
            $product->searchable();

            return $product->fresh();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
```

### 12.3 API Resources (Simplified)

```php
class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        $currency = $request->header('Currency', 'USD');

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name, // Direct value, no translation
            'description' => $this->description, // Direct value

            // Price with currency conversion
            'price' => [
                'amount' => $this->getPriceInCurrency($currency),
                'currency' => $currency,
                'formatted' => $this->formatPrice($currency),
            ],

            'category' => new CategoryResource($this->whenLoaded('category')),
            'media' => MediaResource::collection($this->whenLoaded('media')),
        ];
    }
}
```

---

## 13. Implementation Phases

### Phase 1: Foundation & Architecture

**Database & Core Setup**
- ✅ PostgreSQL setup
- ✅ Core migrations (companies, users, roles/permissions)
- ✅ User authentication (Sanctum)
- ✅ Multi-tenant setup

**Architecture Patterns**
- 🔴 Service Layer Pattern
- 🔴 Laravel Policies
- 🔴 API Resources
- 🔴 Polymorphic Media

**Product Catalog (Simplified)**
- ✅ Product types
- ✅ Categories (no translation tables)
- ✅ Products (no translation tables)
- ✅ Multi-currency pricing
- ✅ Product attributes
- ✅ Frontend i18n setup (react-i18next)

**Deliverables:**
- Working authentication
- Service Layer architecture
- Authorization (Policies)
- Single-language product catalog
- Multi-currency support
- Polymorphic media system
- Frontend i18n (UI translations)

### Phase 2: Inventory
- ✅ Warehouses
- ✅ Stock tracking
- ✅ Stock movements
- ✅ Elasticsearch setup

### Phase 3: Procurement
- ✅ Suppliers
- ✅ Purchase orders
- ✅ GRN

### Phase 4: Sales
- ✅ Customers
- ✅ Sales orders
- ✅ Stock reservation

### Phase 5: Manufacturing
- ✅ BOM management
- ✅ Production orders

### Phase 6: Support & Reporting
- ✅ Activity logs
- ✅ Notifications
- ✅ Dashboard
- ✅ Reports

### Phase 7: Testing & Deployment
- ✅ Unit tests
- ✅ Feature tests
- ✅ Production deployment

---

## Appendix A: Database Changes Summary

### Tables REMOVED (Simplification)

```
❌ product_translations
❌ category_translations
❌ product_type_attribute_translations
❌ product_media (replaced by polymorphic media)
❌ languages (not needed, frontend handles)
```

### Tables ADDED

```
✅ media (polymorphic, replaces product_media)
```

### Tables SIMPLIFIED

```
✅ companies (removed default_language, supported_languages)
✅ users (removed preferred_language)
✅ products (direct name, description columns)
✅ categories (direct name, description columns)
✅ product_types (direct name, description columns)
✅ product_type_attributes (direct attribute_name column)
```

**Net Result: ~30 tables (from 50)**

---

## Appendix B: Frontend i18n Example

**Component Example:**
```jsx
import { useTranslation } from 'react-i18next';

function ProductForm() {
  const { t } = useTranslation();

  return (
    <form>
      <label>{t('product.form.sku')}</label>
      <input name="sku" placeholder={t('product.form.sku')} />

      <label>{t('product.form.name')}</label>
      <input name="name" placeholder={t('product.form.name')} />

      <button type="submit">{t('product.actions.save')}</button>
    </form>
  );
}
```

---

**End of Document**

---

## Document History

**Version 5.9** - 2026-01-08
- ✅ **Negative Stock Policy System**: Comprehensive negative stock management with product-level policies
- ✅ Added Section 6.5: Negative Stock Policy System with NEVER, ALLOWED, LIMITED policies
- ✅ Added Stock Debt tracking system with automatic reconciliation
- ✅ Documented automatic debt creation and reconciliation flow
- ✅ Added `negative_stock_policy` and `negative_stock_limit` fields to Products table schema
- ✅ Added Stock Debts table schema (Section 6.4.3)
- ✅ Documented MRP integration with negative stock as priority requirement
- ✅ Documented Stock Alert Service for negative stock monitoring
- ✅ Renumbered sections: Stock Reservation (6.6), Procurement (6.7), Sales (6.8), Over-Delivery Tolerance (6.9), Manufacturing (6.10)

**Version 5.8** - 2026-01-08
- ✅ **Stock Reservation Policy System**: Comprehensive reservation management with configurable policies
- ✅ Added Section 6.6: Stock Reservation System with ReservationPolicy enum
- ✅ Documented automatic reservation flow for Sales Orders and Work Orders
- ✅ Documented reservation policies: FULL, PARTIAL, REJECT, WAIT
- ✅ Documented automatic reservation creation/release based on order status
- ✅ Renumbered sections: Procurement (6.7), Sales (6.8), Over-Delivery Tolerance (6.9), Manufacturing (6.10)

**Version 5.7** - 2026-01-08
- ✅ **Over-Delivery Tolerance System**: Comprehensive tolerance management for Sales Orders and Purchase Orders
- ✅ Added Section 6.7: Over-Delivery Tolerance System with hierarchical fallback logic
- ✅ Added `over_delivery_tolerance_percentage` to `purchase_order_items` table
- ✅ Added `over_delivery_tolerance_percentage` to `sales_order_items` table
- ✅ Added `over_delivery_tolerance_percentage` to `products` table
- ✅ Added `over_delivery_tolerance_percentage` to `categories` table
- ✅ Implemented 4-level fallback hierarchy: Order Item → Product → Category → Company Default (SaaS - no system level)
- ✅ Delivery Note quantity control with tolerance validation
- ✅ GRN quantity control with tolerance validation
- ✅ Support for multiple partial deliveries per order
- ✅ Warning logs when tolerance is used
- ✅ Renumbered Manufacturing section from 6.7 to 6.8

**Version 5.6** - 2025-12-30
- ✅ **Manufacturing Module (Phase 5)**: Complete Manufacturing documentation
- ✅ Added Section 6.7-6.11: Comprehensive Manufacturing module
- ✅ Work Centers with types (machine, labor, subcontract, tool)
- ✅ BOMs with multi-level explosion support (phantom items)
- ✅ Routings with operations and time estimates
- ✅ Work Orders with full lifecycle (draft → released → in_progress → completed)
- ✅ Work Order Operations tracking
- ✅ Work Order Materials for material consumption
- ✅ Manufacturing Enums (WorkCenterType, BomStatus, RoutingStatus, WorkOrderStatus, WorkOrderPriority, OperationStatus)
- ✅ Manufacturing Services documentation (BOM explosion algorithm)
- ✅ Manufacturing API Routes (40+ endpoints)
- ✅ Manufacturing Permissions

**Version 5.5** - 2025-12-28
- ✅ **QC Zones**: Added quarantine and rejection warehouse zones
- ✅ **Supplier Quality Scoring**: Quality score and grade calculation from inspection data
- ✅ **Stock Quality Status**: Comprehensive status tracking with operation restrictions

**Version 5.4** - 2025-12-26
- ✅ **Standard Quality Control**: Implemented QC module within Procurement
- ✅ Added `acceptance_rules` table for inspection criteria (product/category/supplier-specific)
- ✅ Added `receiving_inspections` table for GRN item inspections
- ✅ Added `non_conformance_reports` (NCR) table for quality issues
- ✅ Added QC permissions (qc.view, qc.create, qc.edit, qc.delete, qc.inspect, qc.review, qc.approve)
- ✅ Added QC Inspector and QC Manager roles
- ✅ AQL sampling support with configurable sample sizes
- ✅ NCR workflow: Open → Review → Disposition → Close
- ✅ Updated Section 2.5 with QC architecture diagram

**Version 5.3** - 2025-12-25
- ✅ **Modular Architecture**: Introduced modular MRP II architecture
- ✅ Added Section 2: Modular Architecture with architecture diagram
- ✅ Added module configuration system (`config/modules.php`)
- ✅ Added module middleware for route protection
- ✅ Core module (mandatory), Procurement (optional), Manufacturing (optional)
- ✅ Sales module as optional module (can be enabled via `MODULE_SALES_ENABLED=true`) with webhook API for external integrations
- ✅ Python Prediction Service integration (sync HTTP, async future)
- ✅ Renumbered all sections to accommodate new architecture section
- ✅ Updated system type from MRP to MRP II

**Version 5.2** - 2025-12-25
- ✅ **Attribute System**: Changed from ProductType-based to Category-based
- ✅ Replaced `product_type_attributes` with `attributes` + `category_attributes`
- ✅ Added `attribute_values` table for predefined select options
- ✅ Added `product_attributes` table for actual product values
- ✅ Updated table count summary (now ~35 tables)
- ✅ Added ProductType permissions to RBAC section

**Version 5.1** - 2025-12-18
- ✅ **Product-Category**: Changed from belongsTo to belongsToMany (many-to-many)
- ✅ Added `category_product` pivot table with `is_primary` flag
- ✅ Added `slug` field to products and categories
- ✅ Added `short_description`, `is_active`, `is_featured`, `meta_data` to products
- ✅ Updated Product model example with new relationships
- ✅ Removed `category_id` from products table (now in pivot)
- ✅ Removed `code`, `path`, `level` from categories (simplified)

**Version 5.0** - 2025-12-08
- 🔴 **MAJOR UPDATE**: Removed all translation tables
- ✅ Simplified to UI i18n only (react-i18next)
- ✅ Single language data storage (user input)
- ✅ Reduced from ~50 tables to ~30 tables
- ✅ Added polymorphic media table
- ✅ Updated all models, examples, and implementation phases
- ✅ Removed complexity from backend, moved i18n to frontend
- ✅ Maintained multi-currency support (critical for business)

**Version 4.0** - 2025-12-05
- Architecture Best Practices section added

**Version 3.0** - 2025-12-05
- Production Ready with Multi-language & Multi-currency

---

*Current Version: 5.9*
*Last Updated: 2026-01-08*
