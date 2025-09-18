import os
import shutil

import pytest
from selenium import webdriver
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.chrome.options import Options


@pytest.fixture(scope="session")
def base_url() -> str:
    """Base URL of the running RatPack Park instance."""
    return os.getenv("RATPACK_BASE_URL", "http://localhost:8000")


@pytest.fixture(scope="session")
def chrome_service(tmp_path_factory):
    """Return a configured Chrome Service, preferring a local chromedriver."""
    driver_path = os.getenv("CHROMEDRIVER") or shutil.which("chromedriver")
    if driver_path:
        return Service(executable_path=driver_path)

    try:
        from webdriver_manager.chrome import ChromeDriverManager
    except ImportError as exc:  # pragma: no cover - defensive
        raise RuntimeError(
            "chromedriver not found on PATH and webdriver-manager not installed. "
            "Install webdriver-manager or provide CHROMEDRIVER."
        ) from exc

    cache_dir = tmp_path_factory.mktemp("wdm")
    executable = ChromeDriverManager(path=cache_dir).install()
    return Service(executable_path=executable)


@pytest.fixture
def driver(chrome_service):
    options = Options()
    options.add_argument("--headless=new")
    options.add_argument("--no-sandbox")
    options.add_argument("--disable-dev-shm-usage")
    options.add_argument("--window-size=1280,720")

    with webdriver.Chrome(service=chrome_service, options=options) as driver:
        yield driver

