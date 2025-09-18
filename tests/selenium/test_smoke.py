from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

from .helpers import Dashboard, login


def assert_table_has_rows(driver: Dashboard) -> None:
    rows = driver.driver.find_elements(By.CSS_SELECTOR, "table tbody tr")
    assert rows, "Expected at least one row in the table"


def test_normal_user_can_view_my_roster(driver, base_url):
    """Normal operator should be able to sign in and read their roster."""
    dashboard = login(driver, base_url, "low", "low")
    dashboard.open_menu_item("My Roster")

    header = WebDriverWait(driver, 10).until(
        EC.presence_of_element_located((By.XPATH, "//h2[contains(., 'My Roster')]")
    )
    assert "My Roster" in header.text

    assert_table_has_rows(dashboard)
    dashboard.exit_iframe()


def test_admin_can_access_assign_roles(driver, base_url):
    """Admin should be able to open the Assign Roles screen and see known users."""
    dashboard = login(driver, base_url, "test", "test")
    dashboard.open_menu_item("Assign Roles")

    heading = WebDriverWait(driver, 10).until(
        EC.presence_of_element_located((By.TAG_NAME, "h2"))
    )
    assert "Assign Roles" in heading.text

    page_text = driver.find_element(By.TAG_NAME, "body").text
    assert "low" in page_text, "Expected to find the operator user listed"

    dashboard.exit_iframe()
