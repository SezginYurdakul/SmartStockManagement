# Smart Stock Management System (MRP) - Final Design Document

**Version:** 5.0
**Date:** 2025-12-08
**Status:** Production Ready Design
**System Type:** Material Requirements Planning (MRP) with Multi-language UI & Multi-currency Support

---

## 📋 Table of Contents

1. [System Overview](#1-system-overview)
2. [Technology Stack](#2-technology-stack)
3. [Key Features](#3-key-features)
4. [Database Architecture](#4-database-architecture)
5. [Core Business Models](#5-core-business-models)
6. [Internationalization](#6-internationalization)
7. [Support Systems](#7-support-systems)
8. [Search & Performance](#8-search--performance)
9. [API Structure](#9-api-structure)
10. [Security & Authorization](#10-security--authorization)
11. [Architecture Best Practices](#11-architecture-best-practices)
12. [Implementation Phases](#12-implementation-phases)

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

## 2. Technology Stack

### 2.1 Backend Core
```yaml
Framework: Laravel 12.x
PHP Version: 8.4+
Database: PostgreSQL 16+
Cache: Redis 7.x
Search Engine: Elasticsearch 8.x
Queue: Redis Queue
Session: Redis
```

### 2.2 Key Packages
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

### 2.3 Infrastructure
```yaml
Web Server: Nginx
Container: Docker + Docker Compose
CI/CD: GitHub Actions / GitLab CI
Monitoring: Laravel Telescope (dev), Sentry (production)
Logging: Monolog + Database Logger
```

### 2.4 Frontend (Separate Repo)
```yaml
Framework: React 19+
State Management: Redux Toolkit / Zustand
HTTP Client: Axios
UI Framework: Ant Design / Shadcn UI
i18n: react-i18next (UI translations only)
Build Tool: Vite
```

---

## 3. Key Features

### 3.1 Core Features
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

### 3.2 Internationalization Strategy

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

### 3.3 Advanced Features
- MRP (Material Requirements Planning)
- Demand forecasting
- Lot/batch/serial number tracking
- Barcode/QR code support
- Low stock alerts
- Email/SMS notifications
- Activity logging & audit trail
- Advanced analytics & dashboards

---

## 4. Database Architecture

### 4.1 Design Principles
- **Normalized**: Proper 3NF normalization for data integrity
- **Simplified**: No translation tables for user-entered data
- **Flexible**: JSONB for dynamic fields, EAV for typed attributes
- **Performant**: Proper indexing, materialized views for reports
- **Scalable**: Partition-ready for large datasets

### 4.2 Table Count Summary
```
Total Tables: ~32 tables (SIMPLIFIED from 50)

Core Business: 21 tables
├── Organization: 3 (companies, users, roles/permissions)
├── Products: 8 (products, types, categories, category_product, attributes, pricing, media, details)
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

Note: category_product is a pivot table for many-to-many Product-Category relationship
```

### 4.3 Database Design Philosophy

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

## 5. Core Business Models

### 5.1 Organization & Multi-tenancy

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

### 5.2 Product Catalog (Simplified - No Translations)

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

#### Product Type Attributes (Dynamic typed attributes)
```sql
product_type_attributes
├── id (bigint, PK)
├── product_type_id (bigint, FK)
├── attribute_code (varchar(50)) -- 'voltage', 'fabric_type', 'cpu_speed'
├── attribute_name (varchar(100)) -- Single language, user input
├── attribute_type (enum: text, number, decimal, boolean, date, select, multiselect)
├── is_required (boolean, default: false)
├── is_searchable (boolean, default: false)
├── is_filterable (boolean, default: false)
├── validation_rules (jsonb, nullable) -- {"min": 0, "max": 100}
├── options (jsonb, nullable) -- For select: ["Option1", "Option2"]
├── default_value (text, nullable)
├── sort_order (integer, default: 0)
├── is_active (boolean, default: true)
└── created_at (timestamp)

UNIQUE idx_type_attr ON product_type_attributes(product_type_id, attribute_code)

-- ❌ REMOVED: product_type_attribute_translations table
```

#### Product Attribute Values
```sql
product_attribute_values
├── id (bigint, PK)
├── product_id (bigint, FK)
├── attribute_id (bigint, FK)
├── value_text (text, nullable)
├── value_integer (bigint, nullable)
├── value_decimal (decimal(15,4), nullable)
├── value_boolean (boolean, nullable)
├── value_date (date, nullable)
├── value_json (jsonb, nullable) -- For multiselect
└── created_at (timestamp)

UNIQUE idx_product_attr_value ON product_attribute_values(product_id, attribute_id)
```

---

### 5.3 Bill of Materials (BOM)

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

### 5.4 Inventory Management

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

### 5.5 Procurement

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
└── created_at (timestamp)
```

---

### 5.6 Sales Management

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

---

### 5.7 Manufacturing

#### Work Centers
```sql
work_centers
├── id (bigint, PK)
├── company_id (bigint, FK)
├── code (varchar(50))
├── name (varchar(255)) -- Single language
├── work_center_type (enum: machine, manual, assembly, quality)
├── cost_per_hour (decimal(15,4), default: 0)
├── is_active (boolean, default: true)
├── created_at (timestamp)
└── updated_at (timestamp)
```

#### Production Orders
```sql
production_orders
├── id (bigint, PK)
├── company_id (bigint, FK)
├── order_number (varchar(50), unique)
├── product_id (bigint, FK)
├── bom_id (bigint, FK)
├── warehouse_id (bigint, FK)
├── quantity_to_produce (decimal(15,3))
├── quantity_produced (decimal(15,3), default: 0)
├── status (enum: draft, released, in_progress, completed, cancelled)
├── scheduled_start_date (date)
├── scheduled_end_date (date)
├── actual_start_date (date, nullable)
├── actual_end_date (date, nullable)
├── created_by (bigint, FK)
├── created_at (timestamp)
├── updated_at (timestamp)
└── deleted_at (timestamp, nullable)
```

---

## 6. Internationalization

### 6.1 Strategy Overview

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

### 6.2 Currencies

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

### 6.3 Exchange Rates

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

### 6.4 Frontend i18n Setup

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

### 6.5 What Gets Translated vs. What Doesn't

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

## 7. Support Systems

### 7.1 Activity Logging

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

### 7.2 Error Logging

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

### 7.3 Notifications

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

### 7.4 System Settings

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

## 8. Search & Performance

### 8.1 Elasticsearch Integration

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

### 8.2 Redis Caching

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

### 8.3 Database Optimization

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

## 9. API Structure

### 9.1 API Versioning
```
/api/v1/...
```

### 9.2 Core Endpoints

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

### 9.3 Request Headers

```
Currency: TRY
Authorization: Bearer {token}
```

**Note:** No Accept-Language header needed for data.
UI language handled by frontend.

### 9.4 Response Format

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

## 10. Security & Authorization

### 10.1 Role-Based Access Control

| Module | Admin | Manager | Purchaser | Warehouse | Sales | Viewer |
|--------|-------|---------|-----------|-----------|-------|--------|
| Users | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Products: Manage | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Products: View | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Purchase Orders | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Sales Orders | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ |
| Stock Adjust | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ |
| Reports | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ |

### 10.2 Security Measures

1. **Authentication**: Laravel Sanctum (API tokens)
2. **Password**: bcrypt hashing
3. **SQL Injection**: Eloquent ORM (parameterized queries)
4. **XSS**: Output escaping
5. **CSRF**: Token validation
6. **Rate Limiting**: Throttle middleware
7. **CORS**: Configured per environment
8. **HTTPS**: Enforced in production

---

## 11. Architecture Best Practices

### 11.1 Simplified Models (No Translations)

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

### 11.2 Service Layer Pattern

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

### 11.3 API Resources (Simplified)

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

## 12. Implementation Phases

### Phase 1: Foundation & Architecture (Weeks 1-3)

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

*Current Version: 5.1*
*Last Updated: 2025-12-18*
