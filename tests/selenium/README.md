# Selenium smoke tests

These end-to-end tests now include both smoke coverage and a broader regression
suite that exercises the primary workflows for each role:

* **Normal operators** sign in, review their roster, submit incident reports, and
  confirm the Rat Track knowledge base renders.
* **Admins** validate every sidebar destination including staffing rosters,
  analytics, ticketing, discount management, incident management, and daily
  operations.

## Prerequisites

1. A running instance of RatPack Park that you can reach from your machine. The defaults assume `http://localhost:8000`.
2. Google Chrome (version 115 or later) installed locally.
3. Either:
   * `chromedriver` on your `PATH`, **or**
   * Python package [`webdriver-manager`](https://pypi.org/project/webdriver-manager/) (installed automatically via the requirements below) and outbound network access so it can download the appropriate driver the first time it runs.
4. Python 3.9+ and `pip`.

## Setup

```bash
cd /path/to/RatPackPark
python -m venv .venv
source .venv/bin/activate
pip install -r tests/selenium/requirements.txt
```

If your application is not running on `http://localhost:8000`, set the `RATPACK_BASE_URL` environment variable before running the tests:

```bash
export RATPACK_BASE_URL="http://127.0.0.1:8080"
```

If `chromedriver` is installed in a non-standard location, point the suite at it:

```bash
export CHROMEDRIVER="/usr/local/bin/chromedriver"
```

## Starting the application

The tests assume the PHP application is already running. If you use the provided Docker Compose setup you can start it with:

```bash
docker compose up --build
```

Or, if you are using the built-in PHP server with a local `.env`, you can run:

```bash
php -S localhost:8000
```

Make sure the database referenced by `db.php` is reachable and seeded with `init.sql` so that the built-in accounts (`test` / `test` for admins and `low` / `low` for operators) exist.

## Running the tests

With the application running and the virtual environment activated, execute:

```bash
pytest tests/selenium
```

Pytest will launch a headless Chrome browser, sign in as both user roles, and
validate that the main dashboard features load without errors. The regression
suite (`tests/selenium/test_regression.py`) verifies that each admin page
renders critical UI elements and that operators see the correct, restricted
navigation set.

