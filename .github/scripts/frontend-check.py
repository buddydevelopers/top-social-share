#!/usr/bin/env python3
"""Open the public post in Chrome and record a front-end failure with screenshots."""

import argparse
import json
import pathlib
import subprocess
import sys

MOBILE_UA = (
    "Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) "
    "AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1"
)

OPTIONS = {
    "tss_field_post_types_post": "1",
    "tss_field_post_types_page": "1",
    "tss_field_share_buttons_facebook": "1",
    "tss_field_share_buttons_twitter": "1",
    "tss_field_share_buttons_pinterest": "1",
    "tss_field_share_buttons_linkedIn": "1",
    "tss_field_share_buttons_whatsapp": "1",
    "tss_field_icon_size": "small",
    "tss_field_icon_style": "original",
    "tss_field_icon_style_bg_color": "#000000",
    "tss_field_icon_style_foreground": "original",
    "tss_field_icon_style_foreground_color": "#8224e3",
    "tss_field_icon_placement_floating_left": "1",
    "tss_field_icon_placement_below_post_title": "1",
    "tss_field_icon_placement_after_post_content": "1",
    "tss_field_icon_placement_inside_feature_image": "1",
}

DESKTOP_MARKERS = (
    ("tss-share-floating-bar", "floating share bar"),
    ("tss-social-div", "share icon list"),
    ("facebook.com/sharer.php", "Facebook share link"),
    ("twitter.com/intent/tweet", "Twitter share link"),
    ("pinterest.com/pin/create", "Pinterest share link"),
    ("linkedin.com/shareArticle", "LinkedIn share link"),
    ("tss-share-shortcode-output", "[top-share] shortcode"),
    ("assets/css/style.css", "front-end stylesheet"),
)


def run(command):
    completed = subprocess.run(command, text=True, capture_output=True)
    if completed.returncode != 0:
        detail = (completed.stderr or completed.stdout or "").strip()
        raise RuntimeError(f"{' '.join(command)}\n{detail}")
    return completed.stdout.strip()


def capture(chrome, url, html_path, png_path, width, height, user_agent=None):
    command = [
        chrome,
        "--headless=new",
        "--disable-gpu",
        "--no-sandbox",
        "--hide-scrollbars",
        f"--window-size={width},{height}",
        "--virtual-time-budget=15000",
        "--timeout=20000",
        "--default-background-color=FFFFFFFF",
    ]
    if user_agent:
        command.append(f"--user-agent={user_agent}")
    subprocess.run(command + [f"--screenshot={png_path}", url], check=False, timeout=60)
    dom = subprocess.run(command + ["--dump-dom", url], text=True, capture_output=True, timeout=60)
    html_path.write_text(dom.stdout or "")
    if dom.stderr:
        html_path.with_suffix(".err").write_text(dom.stderr)
    return html_path.read_text()


def page_problems(html, mobile=False):
    problems = []
    lowered = html.lower()
    fatal = "there has been a critical error" in lowered or "fatal error" in lowered or "uncaught" in lowered
    if fatal:
        problems.append("The public page hit a PHP error instead of rendering the post.")
    if "frontend share check" not in lowered:
        problems.append("The browser did not render the test post.")
    if fatal:
        return problems
    for needle, label in DESKTOP_MARKERS:
        if needle not in html:
            problems.append(f"Missing {label} ({needle}).")
    has_whatsapp = "api.whatsapp.com" in html
    if mobile and not has_whatsapp:
        problems.append("WhatsApp is missing on a mobile browser.")
    if not mobile and has_whatsapp:
        problems.append("WhatsApp is visible on desktop. It should render only on mobile.")
    return problems


def guide(actual, seeded_ok):
    why = (
        "On a normal front-end request is_admin() is false, so bd_tss_plugin_activate() never loads the settings class and tss_options is never created. "
        "The share class still loads and reads that missing option. bd_tss_build_share_icons() then uses tss_options as an array, which throws on PHP 8 and the public page dies before the icons render."
    )
    if seeded_ok:
        why += " After tss_options is written directly, the same post renders the share bar. Visitors never get that write unless an admin request runs bd_tss_settings_init()."
    return {
        "title": "Front end share bar does not render for visitors",
        "screenshot_alt": "Front end of the post when the share bar failed",
        "expected": "A published post shows the floating share bar, icons around the content, and the [top-share] shortcode. Facebook, Twitter, Pinterest, and LinkedIn are visible. WhatsApp appears only in a mobile browser.",
        "actual": actual,
        "why": why,
        "fix": "Save the default tss_options array without requiring is_admin(), and make bd_tss_build_share_icons() return nothing when tss_options is not an array instead of reading offsets on false.",
        "where": [
            {
                "file": "top-social-share.php",
                "lines": "47-57",
                "symbol": "bd_tss_plugin_activate()",
                "detail": "Loads the settings class only for admin requests, so a visitor never triggers add_option().",
            },
            {
                "file": "includes/class-tss-settings.php",
                "lines": "142-145",
                "symbol": "BD_TSS_Settings::bd_tss_settings_init()",
                "detail": "The only place tss_options is created, and only on admin_init.",
            },
            {
                "file": "includes/class-tss-social-share.php",
                "lines": "41-49",
                "symbol": "BD_TSS_Social_Share::load()",
                "detail": "Hooks the floating bar, content placement, featured image, and [top-share] shortcode on the front end.",
            },
            {
                "file": "includes/class-tss-social-share.php",
                "lines": "81-98",
                "symbol": "BD_TSS_Social_Share::bd_tss_build_share_icons()",
                "detail": "Reads tss_option as an array. A missing option is false and this call fatals on the public page.",
            },
            {
                "file": "includes/class-tss-social-share.php",
                "lines": "211-214",
                "symbol": "BD_TSS_Social_Share::bd_tss_social_share_link()",
                "detail": "WhatsApp markup is returned only when wp_is_mobile() is true.",
            },
        ],
    }


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--wp", default="/tmp/wordpress")
    parser.add_argument("--chrome", required=True)
    parser.add_argument("--out", default="/tmp/tss-frontend")
    args = parser.parse_args()
    out = pathlib.Path(args.out)
    out.mkdir(parents=True, exist_ok=True)
    wp = args.wp
    log_lines = []

    post_id = run([
        "wp", f"--path={wp}", "post", "create",
        "--post_title=Frontend share check",
        "--post_content=Share this post. [top-share]",
        "--post_status=publish",
        "--porcelain",
    ])
    url = run(["wp", f"--path={wp}", "post", "url", post_id])
    log_lines.append(f"Post {post_id}: {url}")

    unseeded_html = capture(args.chrome, url, out / "unseeded.html", out / "unseeded.png", 1280, 900)
    unseeded = page_problems(unseeded_html, mobile=False)
    log_lines.append("Visitor, before settings exist:")
    log_lines.extend(f"- {item}" for item in unseeded) if unseeded else log_lines.append("- share bar rendered")

    option_file = out / "options.json"
    option_file.write_text(json.dumps(OPTIONS))
    run(["wp", f"--path={wp}", "option", "update", "tss_options", option_file.read_text(), "--format=json"])
    seeded_html = capture(args.chrome, url, out / "seeded.html", out / "seeded.png", 1280, 900)
    seeded = page_problems(seeded_html, mobile=False)
    log_lines.append("Visitor, after tss_options is saved:")
    log_lines.extend(f"- {item}" for item in seeded) if seeded else log_lines.append("- share bar rendered")

    mobile_html = capture(args.chrome, url, out / "mobile.html", out / "mobile.png", 390, 844, MOBILE_UA)
    mobile = page_problems(mobile_html, mobile=True)
    log_lines.append("Mobile visitor, after tss_options is saved:")
    log_lines.extend(f"- {item}" for item in mobile) if mobile else log_lines.append("- WhatsApp rendered")

    problems = []
    screenshots = []
    if unseeded:
        problems.append("A visitor who has never opened wp-admin does not get a working share bar.")
        problems.extend(unseeded)
        screenshots.append(str(out / "unseeded.png"))
    if seeded:
        problems.append("The share bar is still wrong after tss_options has been saved.")
        problems.extend(seeded)
        screenshots.append(str(out / "seeded.png"))
    if mobile:
        problems.append("The mobile front end does not match the WhatsApp rule.")
        problems.extend(mobile)
        screenshots.append(str(out / "mobile.png"))

    version = run(["wp", f"--path={wp}", "core", "version"])
    actual = " ".join(problems) if problems else "The front end rendered the share bar."
    report = {
        "message": "front end share bar is missing",
        "guide": guide(actual, seeded_ok=not seeded),
        "wordpress": version,
        "is_admin": False,
        "wp_admin": None,
        "settings_loaded": False,
        "share_loaded": True,
        "option_type": "boolean" if unseeded else "array",
        "admin_init_count": 0,
        "primary_screenshot": screenshots[0] if screenshots else "",
        "screenshots": screenshots,
        "context": {"post_url": url},
    }
    (out / "result.json").write_text(json.dumps(report, indent=2))
    (out / "screenshots.tsv").write_text("".join(
        f"{pathlib.Path(path).stem}\t{path}\n" for path in screenshots
    ))
    log_path = out / "check.log"
    log_path.write_text("\n".join(log_lines) + "\n")
    print(log_path.read_text())
    if problems:
        sys.exit(1)


if __name__ == "__main__":
    main()
