# Real Estate Offers API

Laravel 12 REST API for importing supplier accommodation offers asynchronously, returning the cheapest current offer for each property, and safely reserving an offer.

## Requirements

- PHP 8.2+
- Laravel 12
- MySQL 8+
- Composer

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configure the application database in `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=real_estate
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database
DB_QUEUE_CONNECTION=mysql
```

Create the `real_estate` database in MySQL, then migrate and seed it:

```bash
php artisan migrate --seed
```

The seeder creates `supplier-a` and `supplier-b` only.

## Queue

Imports are processed through Laravel's database queue. Run a worker while using the application:

```bash
php artisan queue:work database
```

## Testing

Tests run against MySQL, not SQLite. `phpunit.xml` is configured for the dedicated `real_estate_testing` database.

Create that database and provide your local MySQL credentials through the environment used to run PHPUnit. The test suite refreshes this database.

```bash
php artisan test
```

## API endpoints

### Start a supplier import

```text
POST /api/suppliers/{supplier:slug}/imports
```

Example request:

```json
{
  "offers": [
    {
      "external_offer_id": "supplier-a-rome-001",
      "property": {
        "external_code": "rome-central-hotel",
        "name": "Rome Central Hotel"
      },
      "status": "available",
      "price_amount": "129.50",
      "currency": "EUR",
      "check_in_date": "2027-06-10",
      "check_out_date": "2027-06-13",
      "valid_from": null,
      "valid_until": "2027-06-01T12:00:00Z"
    }
  ]
}
```

Returns `202 Accepted` with an Import ID. Processing is asynchronous. The payload is an incremental offer upsert set, not a full supplier snapshot; omitted offers are not changed.

### Get cheapest current offers

```text
GET /api/offers/cheapest?check_in_date=2027-06-10&check_out_date=2027-06-13&currency=EUR
```

Required query parameters are `check_in_date`, `check_out_date`, and `currency`. The endpoint returns at most one cheapest currently valid Offer per Property for the requested stay dates and currency.

### Reserve an offer

```text
POST /api/offers/{offer}/reservations
```

- `201 Created`: reservation created
- `404 Not Found`: offer does not exist
- `409 Conflict`: offer is unavailable or already reserved
- `410 Gone`: offer is expired or not yet valid

## Design decisions

- `properties.external_code` is a canonical property identifier shared across suppliers.
- Import payloads are incremental upserts, not authoritative snapshots.
- An Offer is identified by `(supplier_id, external_offer_id)`.
- Cheapest-offer selection uses MySQL 8 `ROW_NUMBER()` and resolves equal prices by lower Offer ID.
- Offers are compared only for identical stay dates and currency.
- Reservation concurrency uses `SELECT ... FOR UPDATE` / `lockForUpdate()` and `UNIQUE(reservations.offer_id)`.
- A reserved Offer remains unavailable after later supplier imports.
