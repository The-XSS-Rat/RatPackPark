from __future__ import annotations

from collections import Counter

from selenium.webdriver.common.by import By
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.ui import WebDriverWait

from .helpers import (
    assert_links_absent,
    assert_links_present,
    login,
)


OPERATOR_EXPECTED_MENU = [
    "My Roster",
    "Report a problem",
    "Rat Track",
    "Logout",
]

OPERATOR_FORBIDDEN_MENU = [
    "Settings",
    "Rosters",
    "Tickets",
    "Assign Roles",
    "Admin problem overview",
]

ADMIN_EXPECTED_MENU = [
    "Settings",
    "Rosters",
    "Tickets",
    "Special Discounts",
    "My Roster",
    "Analytics",
    "Maintenance",
    "Report a problem",
    "Admin problem overview",
    "Role Management",
    "Assign Roles",
    "Daily Operations",
    "Rat Track",
    "Logout",
]


def wait_for_heading(driver, locator, expected_text: str) -> None:
    heading = WebDriverWait(driver, 10).until(EC.presence_of_element_located(locator))
    assert expected_text in heading.text


def test_operator_navigation(driver, base_url):
    dashboard = login(driver, base_url, "low", "low")
    menu = dashboard.visible_menu_items()
    assert_links_present(menu, OPERATOR_EXPECTED_MENU)
    assert_links_absent(menu, OPERATOR_FORBIDDEN_MENU)

    dashboard.open_menu_item("My Roster")
    wait_for_heading(driver, (By.TAG_NAME, "h2"), "My Roster")
    rows = driver.find_elements(By.CSS_SELECTOR, "table tbody tr")
    assert rows, "Expected roster rows for operator"
    dashboard.exit_iframe()

    dashboard.open_menu_item("Report a problem")
    wait_for_heading(driver, (By.TAG_NAME, "h2"), "Report a Problem")
    assert driver.find_element(By.ID, "category")
    assert driver.find_element(By.ID, "description")
    dashboard.exit_iframe()

    dashboard.open_menu_item("Rat Track")
    wait_for_heading(driver, (By.TAG_NAME, "h1"), "Rat Track")
    vuln_rows = driver.find_elements(By.CSS_SELECTOR, "table tbody tr")
    assert vuln_rows, "Expected Rat Track to list vulnerabilities"
    dashboard.exit_iframe()


def test_admin_regression_suite(driver, base_url):
    dashboard = login(driver, base_url, "test", "test")
    menu = dashboard.visible_menu_items()

    counts = Counter(menu)
    duplicates = [label for label, count in counts.items() if count > 1]
    assert not duplicates, f"Duplicate menu entries detected: {duplicates}"

    assert_links_present(menu, ADMIN_EXPECTED_MENU)

    dashboard.open_menu_item("Settings")
    wait_for_heading(driver, (By.TAG_NAME, "h2"), "Settings")
    options = {el.text.strip() for el in driver.find_elements(By.CSS_SELECTOR, ".settings-option")}
    assert {"👥 User Management", "🎟️ Ticket Types"}.issubset(options)
    dashboard.exit_iframe()

    dashboard.open_menu_item("Rosters")
    wait_for_heading(driver, (By.TAG_NAME, "h2"), "Staff Rosters")
    assert driver.find_elements(By.CSS_SELECTOR, "table tbody tr"), "Rosters table should list shifts"
    dashboard.exit_iframe()

    dashboard.open_menu_item("Tickets")
    wait_for_heading(driver, (By.TAG_NAME, "h2"), "Available Tickets")
    assert driver.find_elements(By.CSS_SELECTOR, "table tbody tr"), "Tickets should be listed"
    dashboard.exit_iframe()

    dashboard.open_menu_item("Special Discounts")
    wait_for_heading(driver, (By.TAG_NAME, "h2"), "Special Discounts")
    assert driver.find_elements(By.CSS_SELECTOR, "table tbody tr"), "Discounts table should render"
    dashboard.exit_iframe()

    dashboard.open_menu_item("My Roster")
    wait_for_heading(driver, (By.TAG_NAME, "h2"), "My Roster")
    assert driver.find_elements(By.CSS_SELECTOR, "table tbody tr"), "Admin roster should show shifts"
    dashboard.exit_iframe()

    dashboard.open_menu_item("Analytics")
    wait_for_heading(driver, (By.TAG_NAME, "h2"), "Analytics Dashboard")
    cards = driver.find_elements(By.CSS_SELECTOR, ".card p")
    assert cards and all(card.text.strip() for card in cards), "Analytics cards should have metrics"
    dashboard.exit_iframe()

    dashboard.open_menu_item("Maintenance")
    wait_for_heading(driver, (By.TAG_NAME, "h2"), "Maintenance Tasks")
    assert driver.find_elements(By.CSS_SELECTOR, "ul li"), "Maintenance list should not be empty"
    dashboard.exit_iframe()

    dashboard.open_menu_item("Report a problem")
    wait_for_heading(driver, (By.TAG_NAME, "h2"), "Report a Problem")
    assert driver.find_elements(By.CSS_SELECTOR, "table tbody tr"), "Problem history should be visible"
    dashboard.exit_iframe()

    dashboard.open_menu_item("Admin problem overview")
    wait_for_heading(driver, (By.TAG_NAME, "h2"), "Admin Problem Management")
    assert driver.find_elements(By.CSS_SELECTOR, "table tbody tr"), "Admin problem list should render"
    dashboard.exit_iframe()

    dashboard.open_menu_item("Role Management")
    wait_for_heading(driver, (By.TAG_NAME, "h2"), "Role Management")
    assert driver.find_elements(By.CSS_SELECTOR, "table tbody tr"), "Role table should display entries"
    dashboard.exit_iframe()

    dashboard.open_menu_item("Assign Roles")
    wait_for_heading(driver, (By.TAG_NAME, "h2"), "Assign Roles")
    assert driver.find_elements(By.CSS_SELECTOR, "select[name='user_id'] option[value]")
    dashboard.exit_iframe()

    dashboard.open_menu_item("Daily Operations")
    wait_for_heading(driver, (By.CSS_SELECTOR, ".summary-card h2"), "Daily Operations")
    assert driver.find_elements(By.CSS_SELECTOR, ".summary-card ul.metrics li")
    dashboard.exit_iframe()

    dashboard.open_menu_item("Rat Track")
    wait_for_heading(driver, (By.TAG_NAME, "h1"), "Rat Track")
    assert driver.find_elements(By.CSS_SELECTOR, "table tbody tr"), "Rat Track should list guidance"
    dashboard.exit_iframe()
