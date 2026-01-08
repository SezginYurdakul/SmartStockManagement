# Smart Stock Management System (MRP II) - Final Design Document

**Version:** 5.7
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
An enterprise-grade **Material Requirements Planning (MRP)** system with comprehensive inventory management, production planning, procurement, sales order management, and real-time analytics.

### 1.2 System Characteristics
- **Multi-language UI**: Complete interface translation via frontend i18n
- **Multi-currency**: Support for multiple currencies with exchange rates
- **Flexible Architecture**: Dynamic product attributes based on product types
- **Scalable**: Designed for growth from small business to enterprise
- **Modern Stack**: Laravel 12, PostgreSQL, Redis, Elasticsearch

### 1.3 Key Differentiators
- ✅ **Multi-language UI**: Frontend translations (react-i18next / vue-i18n)
- ✅ **Single Language Data**: User-entered data stored in user's language
- ✅ **Multi-currency Pricing**: Automatic currency conversion, tiered pricing
- ✅ **Dynamic Attributes**: Product type-specific attributes with validation
- ✅ **MRP Logic**: Automated material requirement calculations
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
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌──────────────┐   ┌──────────────────┐   ┌──────────────────┐    │
│  │  CORE        │   │  PROCUREMENT     │   │  MANUFACTURING   │    │
│  │  (Mandatory) │   │  (Optional)      │   │  (Optional)      │    │
│  ├──────────────┤   ├──────────────────┤   ├──────────────────┤    │
│  │ - Stock      │   │ - Suppliers      │   │ - BOM            │    │
│  │ - Products   │   │ - PurchaseOrders │   │ - WorkOrders     │    │
│  │ - Categories │   │ - Receiving      │   │ - Production     │    │
│  │ - Warehouses │   │ - Basic QC       │   │ - Basic QC       │    │
│  │ - Attributes │   │   (pass/fail)    │   │   (pass/fail)    │    │
│  │ - UoM        │   │                  │   │                  │    │
│  └──────────────┘   └──────────────────┘   └──────────────────┘    │
│                                                                      │
├─────────────────────────────────────────────────────────────────────┤
│                       INTEGRATION LAYER                              │
│  ┌──────────────────────────────────────────────────────────────┐   │
│  │  Webhook API for External Systems (Sales, Finance, etc.)     │   │
│  │  - Stock reservation webhooks                                 │   │
│  │  - Stock movement notifications                               │   │
│  │  - Inventory level alerts                                     │   │
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
2. **Sales/Finance External Only**: No built-in Customer/SalesOrder - external systems integrate via webhooks
3. **Standard QC**: Acceptance rules, inspections, NCR - no CAPA, SPC (can be added later)
4. **Stateless Python Service**: Prediction service has no database - queries Laravel API for data
5. **Sync First, Async Later**: Start with HTTP for simplicity - add Redis Queue when needed
6. **Graceful Degradation**: If Python service is down, Laravel continues to work

### 2.5 Quality Control (Standard Level)

The system includes a standard-level QC module within Procurement:

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
│  Future Expansion: CAPA, Supplier Ratings, SPC                  │
└─────────────────────────────────────────────────────────────────┘
```

**QC Workflow:**
1. GRN created → Inspections auto-created per item
2. Inspector records results (pass/fail quantities)
3. Failed items → NCR created
4. NCR workflow: Open → Review → Disposition → Close
5. Dispositions: Accept, Reject, Rework, Return to Supplier, Use As-Is
6. Stock quality status updated automatically based on disposition

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
- MRP (Material Requirements Planning)
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

---

### 6.5 Procurement

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

### 6.6 Sales Management (External Integration)

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

### 6.7 Over-Delivery Tolerance System

The system implements a flexible over-delivery tolerance mechanism for both **Sales Orders → Delivery Notes** and **Purchase Orders → Goods Received Notes (GRN)**. This allows partial deliveries while preventing excessive over-delivery through a hierarchical fallback system.

#### 6.7.1 Tolerance Levels (Fallback Logic)

The system uses a **4-level fallback hierarchy** (most specific to least specific):

```
1. Order Item Level (Most Specific)
   ├── sales_order_items.over_delivery_tolerance_percentage
   └── purchase_order_items.over_delivery_tolerance_percentage

2. Product Level
   └── products.over_delivery_tolerance_percentage

3. Category Level
   └── categories.over_delivery_tolerance_percentage (primary category)

4. System Default (Least Specific)
   └── settings.delivery.default_over_delivery_tolerance
```

**Decision Logic:**
```php
$tolerance = $orderItem->over_delivery_tolerance_percentage
    ?? $product->over_delivery_tolerance_percentage
    ?? $category->over_delivery_tolerance_percentage
    ?? Setting::get('delivery.default_over_delivery_tolerance', 0);
```

#### 6.7.2 Database Schema

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

**System Settings:**
```sql
system_settings
├── category = 'delivery'
├── key = 'default_over_delivery_tolerance'
├── value = '0' (default: no tolerance)
└── ...
```

#### 6.7.3 Sales Order → Delivery Note Flow

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
- System default tolerance: 5%
- Max allowed: 1000 × 1.05 = 1050 units

| Delivery Note | Quantity | Result | Reason |
|--------------|----------|--------|--------|
| DN-001 | 1000 | ✅ Success | Normal delivery |
| DN-002 | 50 | ✅ Success (Warning) | Within tolerance (1050 total) |
| DN-003 | 1 | ❌ Error | Exceeds tolerance (1051 > 1050) |

#### 6.7.4 Purchase Order → GRN Flow

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

#### 6.7.5 Partial Delivery Support

Both systems support **multiple partial deliveries**:

- **Sales Orders**: Multiple delivery notes can be created for the same sales order item
- **Purchase Orders**: Multiple GRNs can be created for the same purchase order item
- **Total Control**: System tracks total quantity across all delivery notes/GRNs (including DRAFTs)
- **Tolerance Applied**: Tolerance is applied to the **total delivered/received quantity**, not per delivery

#### 6.7.6 Service Implementation

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

    // 4. System default
    $systemDefault = Setting::get('delivery.default_over_delivery_tolerance', 0);
    return (float) $systemDefault;
}
```

**GoodsReceivedNoteService:**
- Same `getOverDeliveryTolerance()` method, but accepts `PurchaseOrderItem` instead

#### 6.7.7 Benefits

1. **Flexibility**: Different tolerance levels for different products/categories
2. **Control**: Prevents excessive over-delivery while allowing reasonable variations
3. **Hierarchy**: Most specific setting wins (item > product > category > system)
4. **Partial Delivery**: Supports multiple deliveries/receipts per order
5. **Audit Trail**: Warning logs when tolerance is used

#### 6.7.8 Use Cases

**High-Value Items (0% tolerance):**
- Electronics, precision instruments
- Set at Product or Category level

**Bulk Materials (2-5% tolerance):**
- Raw materials, chemicals, grains
- Set at Category level

**Special Orders (Item-level override):**
- Customer-specific tolerance for specific order
- Set at Sales Order Item level

**System-Wide Default:**
- General tolerance for all items (e.g., 0% = strict, 5% = flexible)
- Set in System Settings

---

### 6.8 Manufacturing (Phase 5)

Manufacturing modülü MRP II sisteminin çekirdeğidir. BOM, Work Centers, Routings ve Work Orders içerir.

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

### 6.8 Manufacturing Enums

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

### 6.9 Manufacturing Services

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

### 6.10 Manufacturing API Routes

```
# Work Centers
GET    /api/v1/work-centers
GET    /api/v1/work-centers/list
POST   /api/v1/work-centers
GET    /api/v1/work-centers/{id}
PUT    /api/v1/work-centers/{id}
DELETE /api/v1/work-centers/{id}
POST   /api/v1/work-centers/{id}/toggle-active

# BOMs
GET    /api/v1/boms
GET    /api/v1/boms/list
POST   /api/v1/boms
GET    /api/v1/boms/{id}
PUT    /api/v1/boms/{id}
DELETE /api/v1/boms/{id}
POST   /api/v1/boms/{id}/items
PUT    /api/v1/boms/{id}/items/{itemId}
DELETE /api/v1/boms/{id}/items/{itemId}
POST   /api/v1/boms/{id}/activate
POST   /api/v1/boms/{id}/obsolete
POST   /api/v1/boms/{id}/copy
GET    /api/v1/boms/{id}/explode
GET    /api/v1/boms/for-product/{productId}

# Routings
GET    /api/v1/routings
GET    /api/v1/routings/list
POST   /api/v1/routings
GET    /api/v1/routings/{id}
PUT    /api/v1/routings/{id}
DELETE /api/v1/routings/{id}
POST   /api/v1/routings/{id}/operations
PUT    /api/v1/routings/{id}/operations/{opId}
DELETE /api/v1/routings/{id}/operations/{opId}
POST   /api/v1/routings/{id}/activate
GET    /api/v1/routings/for-product/{productId}

# Work Orders
GET    /api/v1/work-orders
GET    /api/v1/work-orders/statistics
POST   /api/v1/work-orders
GET    /api/v1/work-orders/{id}
PUT    /api/v1/work-orders/{id}
DELETE /api/v1/work-orders/{id}
POST   /api/v1/work-orders/{id}/release
POST   /api/v1/work-orders/{id}/start
POST   /api/v1/work-orders/{id}/complete
POST   /api/v1/work-orders/{id}/cancel
POST   /api/v1/work-orders/{id}/hold
POST   /api/v1/work-orders/{id}/resume
POST   /api/v1/work-orders/{id}/operations/{opId}/start
POST   /api/v1/work-orders/{id}/operations/{opId}/complete
GET    /api/v1/work-orders/{id}/material-requirements
POST   /api/v1/work-orders/{id}/issue-materials
POST   /api/v1/work-orders/{id}/receive-finished-goods
```

### 6.11 Manufacturing Permissions

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

### 10.1 API Versioning
```
/api/v1/...
```

### 10.2 Core Endpoints

**Authentication:**
```
POST   /api/v1/auth/login
POST   /api/v1/auth/logout
POST   /api/v1/auth/refresh
GET    /api/v1/auth/me
```

**Products:**
```
GET    /api/v1/products
POST   /api/v1/products
GET    /api/v1/products/{id}
PUT    /api/v1/products/{id}
DELETE /api/v1/products/{id}
GET    /api/v1/products/{id}/stock
GET    /api/v1/products/{id}/bom
POST   /api/v1/products/search (Elasticsearch)
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

**Week 1: Database & Core Setup**
- ✅ PostgreSQL setup
- ✅ Core migrations (companies, users, roles/permissions)
- ✅ User authentication (Sanctum)
- ✅ Multi-tenant setup

**Week 2: Architecture Patterns**
- 🔴 Service Layer Pattern
- 🔴 Laravel Policies
- 🔴 API Resources
- 🔴 Polymorphic Media

**Week 3: Product Catalog (Simplified)**
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

### Phase 2: Inventory (Weeks 4-5)
- ✅ Warehouses
- ✅ Stock tracking
- ✅ Stock movements
- ✅ Elasticsearch setup

### Phase 3: Procurement (Weeks 6-7)
- ✅ Suppliers
- ✅ Purchase orders
- ✅ GRN

### Phase 4: Sales (Weeks 8-9)
- ✅ Customers
- ✅ Sales orders
- ✅ Stock reservation

### Phase 5: Manufacturing (Weeks 10-11)
- ✅ BOM management
- ✅ Production orders

### Phase 6: Support & Reporting (Weeks 12-13)
- ✅ Activity logs
- ✅ Notifications
- ✅ Dashboard
- ✅ Reports

### Phase 7: Testing & Deployment (Weeks 14-15)
- ✅ Unit tests
- ✅ Feature tests
- ✅ Production deployment

**Total Timeline: 15 weeks (vs. 18 weeks with translation tables)**

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

**Version 5.7** - 2026-01-08
- ✅ **Over-Delivery Tolerance System**: Comprehensive tolerance management for Sales Orders and Purchase Orders
- ✅ Added Section 6.7: Over-Delivery Tolerance System with hierarchical fallback logic
- ✅ Added `over_delivery_tolerance_percentage` to `purchase_order_items` table
- ✅ Added `over_delivery_tolerance_percentage` to `sales_order_items` table
- ✅ Added `over_delivery_tolerance_percentage` to `products` table
- ✅ Added `over_delivery_tolerance_percentage` to `categories` table
- ✅ Implemented 4-level fallback hierarchy: Order Item → Product → Category → System Default
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
- ✅ Sales/Finance as external integrations only (webhook API)
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

*Current Version: 5.7*
*Last Updated: 2026-01-08*
