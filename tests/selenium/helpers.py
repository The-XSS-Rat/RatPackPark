"""Shared helpers for RatPack Park Selenium tests."""
from __future__ import annotations

from typing import Iterable, Sequence

from selenium.webdriver.common.by import By
from selenium.webdriver.remote.webdriver import WebDriver
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.ui import WebDriverWait

LOGIN_BUTTON = (By.CSS_SELECTOR, "button[type='submit']")
SIDEBAR = (By.CLASS_NAME, "sidebar")
MAIN_IFRAME = (By.CSS_SELECTOR, "iframe[name='mainframe']")


class Dashboard:
    """Helper around the RatPack Park dashboard layout."""

    def __init__(self, driver: WebDriver) -> None:
        self.driver = driver

    def wait_until_loaded(self) -> None:
        WebDriverWait(self.driver, 10).until(EC.presence_of_element_located(SIDEBAR))

    def open_menu_item(self, link_text: str) -> WebDriver:
        """Click a sidebar link and switch into the main iframe."""
        self.driver.switch_to.default_content()
        WebDriverWait(self.driver, 10).until(EC.element_to_be_clickable((By.LINK_TEXT, link_text))).click()
        WebDriverWait(self.driver, 10).until(EC.frame_to_be_available_and_switch_to_it(MAIN_IFRAME))
        WebDriverWait(self.driver, 10).until(EC.presence_of_element_located((By.TAG_NAME, "body")))
        return self.driver

    def exit_iframe(self) -> None:
        self.driver.switch_to.default_content()

    def visible_menu_items(self) -> Sequence[str]:
        self.driver.switch_to.default_content()
        elements = self.driver.find_elements(By.CSS_SELECTOR, ".sidebar a")
        return [el.text.strip() for el in elements]


def login(driver: WebDriver, base_url: str, username: str, password: str) -> Dashboard:
    driver.get(f"{base_url}/login.php")
    driver.find_element(By.NAME, "username").send_keys(username)
    driver.find_element(By.NAME, "password").send_keys(password)
    driver.find_element(*LOGIN_BUTTON).click()

    dashboard = Dashboard(driver)
    dashboard.wait_until_loaded()
    return dashboard


def assert_links_present(actual: Sequence[str], expected: Iterable[str]) -> None:
    missing = [label for label in expected if label not in actual]
    assert not missing, f"Missing expected menu links: {missing}"


def assert_links_absent(actual: Sequence[str], forbidden: Iterable[str]) -> None:
    present = [label for label in forbidden if label in actual]
    assert not present, f"Menu unexpectedly contained: {present}"
