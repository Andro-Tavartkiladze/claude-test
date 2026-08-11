"""ნაბიჯი 1 — ავტორიზაცია და სესიის შენახვა.

პაროლს არსად არ ვინახავთ და არსად არ ვგზავნით: ბრაუზერი ხილულად იხსნება,
ლოგინს, SMS კოდს და პროფილის არჩევას მომხმარებელი თვითონ აკეთებს.
სკრიპტი მხოლოდ ელოდება, სესიას ინახავს და ეკრანს იღებს.

გაშვება:
    python login.py                 # ხილული ბრაუზერი, სესიის შენახვა
    python login.py --check         # ამოწმებს შენახული სესია ჯერ კიდევ ცოცხალია თუ არა
"""

import argparse
import sys
from datetime import datetime
from pathlib import Path

from playwright.sync_api import TimeoutError as PWTimeout
from playwright.sync_api import sync_playwright

PORTAL = "https://eservices.rs.ge/"
ROOT = Path(__file__).parent
STATE = ROOT / "storage_state.json"
SHOTS = ROOT / "shots"

# ავტორიზაციის შემდეგ URL იცვლება — ამით ვცნობთ რომ შესვლა შედგა.
LOGGED_IN_URL = "**/app/**"


def shot(page, name: str) -> Path:
    SHOTS.mkdir(exist_ok=True)
    path = SHOTS / f"{datetime.now():%H%M%S}-{name}.png"
    page.screenshot(path=str(path), full_page=True)
    print(f"  screenshot: {path.relative_to(ROOT)}")
    return path


def login(headless: bool) -> int:
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=headless)
        ctx = browser.new_context(locale="ka-GE", viewport={"width": 1600, "height": 1000})
        page = ctx.new_page()

        print(f"იხსნება {PORTAL} ...")
        page.goto(PORTAL, wait_until="domcontentloaded", timeout=60_000)
        shot(page, "01-login-page")

        print()
        print("  >> ბრაუზერის ფანჯარაში ხელით შეასრულე:")
        print("     1. მომხმარებელი და პაროლი")
        print("     2. SMS კოდი (თუ ითხოვს)")
        print("     3. აირჩიე პროფილი — სატესტო1")
        print()
        print("  ველოდები 5 წუთს ...")

        try:
            page.wait_for_url(LOGGED_IN_URL, timeout=300_000)
        except PWTimeout:
            shot(page, "02-timeout")
            print("\n  ვერ დაფიქსირდა ავტორიზაცია 5 წუთში.")
            print("  თუ რეალურად შეხვედი, მაგრამ URL სხვაა — გამომიგზავნე ეს screenshot")
            print("  და მისამართის ველი, LOGGED_IN_URL-ს შევასწორებ.")
            browser.close()
            return 1

        print(f"\n  ავტორიზაცია დაფიქსირდა: {page.url}")
        page.wait_for_load_state("networkidle", timeout=30_000)
        shot(page, "03-after-login")

        ctx.storage_state(path=str(STATE))
        print(f"  სესია შენახულია: {STATE.name}")

        # ეს ჩამონათვალი მჭირდება რომ ზედნადების განყოფილება ვიპოვო.
        print("\n  ხილული ბმულები/ღილაკები მთავარ გვერდზე:")
        seen = set()
        for el in page.locator("a, button").all()[:200]:
            try:
                text = (el.inner_text(timeout=500) or "").strip()
            except PWTimeout:
                continue
            if text and text not in seen and len(text) < 60:
                seen.add(text)
                print(f"    - {text}")

        browser.close()
        return 0


def check() -> int:
    if not STATE.exists():
        print(f"{STATE.name} არ არსებობს — ჯერ გაუშვი: python login.py")
        return 1

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        ctx = browser.new_context(storage_state=str(STATE), locale="ka-GE")
        page = ctx.new_page()
        page.goto(PORTAL, wait_until="networkidle", timeout=60_000)
        shot(page, "check")

        alive = page.locator("input[type=password]").count() == 0
        print("სესია ცოცხალია" if alive else "სესია ამოიწურა — ხელახლა გაუშვი: python login.py")
        browser.close()
        return 0 if alive else 1


if __name__ == "__main__":
    ap = argparse.ArgumentParser()
    ap.add_argument("--check", action="store_true", help="შენახული სესიის შემოწმება")
    ap.add_argument("--headless", action="store_true", help="ბრაუზერის ფანჯრის გარეშე")
    args = ap.parse_args()

    sys.exit(check() if args.check else login(headless=args.headless))
