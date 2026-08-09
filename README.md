# MedStore — Medical Store / Pharmacy POS System

Full-stack pharmacy management system built with **Vue 3 + Laravel**. Features inventory with batch/expiry tracking, POS billing, purchases & suppliers, customers, and profit reports.

## Tech Stack

- **Backend:** Laravel 13 (API), Laravel Sanctum (auth), SQLite local / MySQL production
- **Frontend:** Vue 3 + Vite, Pinia, Vue Router, Axios
- **Roles:** Admin (everything), Cashier (POS, customers, products read)

## Quick Start

### 1. Backend

```bash
cd backend
composer install
copy .env.example .env        # set DB_* (MySQL) or keep SQLite default
php artisan key:generate
php artisan migrate --seed     # creates demo users, products, suppliers, customers
php artisan serve              # http://127.0.0.1:8000
```

### 2. Frontend

```bash
cd frontend
npm install
npm run dev                    # http://localhost:5173 (proxies /api -> :8000)
```

Open **http://localhost:5173** and log in:

| Email                  | Password  | Role    |
|------------------------|-----------|---------|
| admin@pharmacy.test    | password  | Admin   |
| cashier@pharmacy.test  | password  | Cashier |

## API Overview

| Method | Endpoint | Purpose | Access |
|--------|----------|---------|--------|
| POST | `/api/login` | Get Sanctum token | Public |
| GET/POST | `/api/products` | Product list / create | Auth |
| GET | `/api/products/all` | Full list for POS (cached) | Auth |
| POST | `/api/products/{id}/batches` | Add stock batch | Auth |
| POST | `/api/sales` | Checkout — deducts batch stock, computes profit | Auth |
| POST | `/api/sales/{id}/refund` | Refund — restores stock | Auth |
| GET/POST | `/api/purchases` | Purchase + auto batch creation / stock in | Admin |
| GET/POST | `/api/customers` `/api/suppliers` | Masters | Auth |
| GET | `/api/reports/dashboard` | Today/month KPIs, low stock, expiring batches | Auth |
| GET | `/api/reports/summary?range=7` | Daily revenue series + totals | Auth |
| GET | `/api/reports/top-products` | Top sellers | Auth |

## Key Business Logic

- **Batch-wise stock:** stock is tracked per batch (`batches.quantity`); sales consume oldest-expiry batches first (FEFO).
- **Expired batches** are never sold automatically.
- **Profit per sale** = Σ (unit_price − unit_cost) × qty, where unit_cost is captured at purchase time.
- **Refund** returns units to the exact batch sold.

## Production Build (Phase 6)

```bash
cd frontend && npm run build     # dist/ folder
cd ../backend && php artisan config:cache route:cache
```

Deploy the `backend/` app (PHP ≥ 8.2, MySQL), point its `public/` at the web root (Nginx/Apache), serve `frontend/dist/` as a static site (or copy it into `backend/public/`), and add `'origin' => false` config. Enable HTTPS + scheduled backup:

```bash
php artisan schedule:run   # add a backup command in Console Kernel
```

## Notes

- Local dev uses SQLite (no setup needed). For MySQL, edit `backend/.env`: `DB_CONNECTION=mysql`, `DB_DATABASE`, etc.
- POS caches products in localStorage-backed Pinia store and refreshes after each sale.
- Frontend routes are lazy-loaded and code-split (`vendor` chunk).