# Small E-commerce Management

A small shop management app with a Laravel API and a Vue dashboard. Admins manage shop owners, shops, and the shared product catalog. Shop owners manage the product listings, prices, descriptions, and thumbnails for their assigned shops. The public storefront displays active listings only.

## Technology

- **Backend:** PHP 8.3+, Laravel 13, Laravel Sanctum, Spatie Laravel Permission
- **Frontend:** Vue 3, TypeScript, Vite
- **Database:** SQLite by default; MySQL can be configured

## Requirements

Install these tools before setup:

- Git
- PHP 8.3 or newer, with PDO SQLite enabled for the default database
- Composer 2
- Node.js 22.18+ or 24.12+, and npm

Check the installed tools:

```sh
php --version
php -m
composer --version
node --version
npm --version
```

When using SQLite, confirm `pdo_sqlite` appears in the output of `php -m`.

## 1. Clone the repository

```sh
git clone https://github.com/YeaminRabbi/vue-laravel-ecommerce.git small_ecommerce
cd small_ecommerce
```

On Windows PowerShell, the same commands work. If your shell does not support changing into the newly cloned folder in the same terminal, run `Set-Location small_ecommerce`.

The repository has two applications:

```text
small_ecommerce/
├── backend/       Laravel API and database
├── frontend/      Vue dashboard and storefront
└── README.md
```

## 2. Set up the Laravel backend

Open a terminal in the project root and install the PHP dependencies:

```sh
cd backend
composer install
```

Create the local environment file from the example.

**Windows PowerShell**

```powershell
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
```

**macOS / Linux**

```sh
test -f .env || cp .env.example .env
```

The example config uses SQLite. Create its database file before running migrations.

**Windows PowerShell**

```powershell
if (-not (Test-Path database/database.sqlite)) { New-Item -ItemType File -Path database/database.sqlite | Out-Null }
```

**macOS / Linux**

```sh
touch database/database.sqlite
```

Generate the application key, create the tables, and load the starter roles and sample records:

```sh
php artisan key:generate
php artisan migrate --seed
```

The seed command creates the admin, shop owner, and customer roles, demo accounts, a sample shop, and sample products. You can safely run `php artisan db:seed` again later to restore or add the starter data.

### Use MySQL instead of SQLite (optional)

Create an empty MySQL database, then edit the database settings in `backend/.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=small_ecommerce
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
```

Then run the migrations and seeders:

```sh
php artisan migrate --seed
```

### Start the backend

Keep this terminal open:

```sh
php artisan serve --host=127.0.0.1 --port=8000
```

The API is available at `http://127.0.0.1:8000`. The Vue development server forwards `/api` requests to this address by default.

## 3. Set up the Vue frontend

Open a **second terminal** in the project root:

```sh
cd frontend
npm ci
npm run dev
```

Open the local URL printed by Vite, usually [http://127.0.0.1:5173](http://127.0.0.1:5173).

To connect the Vite proxy to a Laravel server running on a different address, create `frontend/.env.local` with the correct target, then restart Vite:

```dotenv
VITE_API_PROXY_TARGET=http://127.0.0.1:8000
```

The API base path is `/api/v1` by default. For a separately hosted API, set `VITE_API_BASE_URL` to its full API URL, such as `https://api.example.com/api/v1`, in the appropriate Vite environment file before building.

## Sign in

Use one of these development accounts after seeding:

| Role | Email | Password |
|---|---|---|
| Administrator | `admin@gmail.com` | `password` |
| Shop owner | `owner@example.com` | `password` |
| Customer sample account | `user@gmail.com` | `password` |

The seeded customer account does not have dashboard access. The customer storefront is public and is the frontend home page. Visitors can filter active products by shop; use **Sign in to workspace** to open the admin or shop owner dashboard. You can also open `http://127.0.0.1:5173/#storefront` directly.

These credentials are for local development only. Change or remove them before using the app in a shared or production environment.

## What is included

- Role and permission based access for administrators and shop owners
- Shop management and owner assignment
- A shared product catalog
- Shop specific listing names, descriptions, prices, thumbnails, and visibility
- A public storefront that only shows active products assigned to active shops
- Sanctum personal access tokens for dashboard sign-in; login requests are rate limited
- Pagination and product search in the dashboard

A product in the shared catalog is not visible in the storefront until it is assigned to a shop. A shop owner can only manage listings for shops assigned to their account.

## API overview

The API is versioned under `/api/v1`.

| Method | Endpoint | Access |
|---|---|---|
| `POST` | `/api/v1/login` | Public; returns an API token |
| `POST` | `/api/v1/logout` | Signed in |
| `GET` | `/api/v1/me` | Signed in |
| `GET` | `/api/v1/dashboard` | Admin or shop owner |
| `GET`, `POST` | `/api/v1/shops` | Shop owners view assigned shops; admins create shops |
| `PUT`, `DELETE` | `/api/v1/shops/{shop}` | Admin |
| `GET`, `POST` | `/api/v1/owners` | Admin |
| `PUT`, `DELETE` | `/api/v1/owners/{owner}` | Admin |
| `GET`, `POST` | `/api/v1/products` | Signed in, permission checked |
| `PUT`, `DELETE` | `/api/v1/products/{product}` | Admin or product creator, permission checked |
| `GET`, `POST` | `/api/v1/shops/{shop}/listings` | Shop owner for their shop, or admin |
| `PUT`, `DELETE` | `/api/v1/shops/{shop}/listings/{listing}` | Shop owner for their shop, or admin |
| `GET` | `/api/v1/storefront` | Public |
| `GET` | `/api/v1/storefront/shops` | Public; lists shops with active products |

Signed-in API requests use the token returned from login:

```http
Authorization: Bearer <token>
Accept: application/json
```

## Database tables

The migrations create the standard Laravel user, cache, and queue tables, Sanctum's `personal_access_tokens` table, Spatie's role and permission tables, and these commerce tables:

- `shops` — shop details and assigned owner
- `products` — the shared product catalog
- `shop_products` — each shop's listing details and overrides

## Build and run checks

Build the Vue app for production:

```sh
cd frontend
npm run build
```

Run the backend feature tests:

```sh
cd backend
php artisan test
```

## Troubleshooting

**SQLite reports that the database file cannot be opened**

- Confirm `backend/database/database.sqlite` exists.
- Confirm `pdo_sqlite` is enabled in the PHP installation used by both Composer and Artisan.

**The dashboard cannot connect to the API**

- Start Laravel first and confirm `http://127.0.0.1:8000` is available.
- Check `VITE_API_PROXY_TARGET` in `frontend/.env.local` if Laravel uses another address.
- Restart the Vite server after changing a Vite environment file.

**Sign-in returns an error after changing or resetting the database**

Run the seeders again from the backend folder:

```sh
php artisan db:seed
```

**A user can sign in but cannot open a dashboard**

Only accounts with the admin or shop owner role have dashboard access. Use the seeded account above or have an administrator create a shop owner account.

