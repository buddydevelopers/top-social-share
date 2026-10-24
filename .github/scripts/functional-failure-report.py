#!/usr/bin/env python3
"""Build a failure screenshot and a GitHub issue body for the functionality check."""

import argparse
import base64
import html
import json
import os
import pathlib
import re
import textwrap
import urllib.error
import urllib.request


FALLBACK_GUIDES = {
    "default options are stored on boot": {
        "title": "Default share settings are never saved outside wp-admin",
        "expected": "After the plugin boots, tss_options is the default array: Facebook, Twitter, LinkedIn, Pinterest, and WhatsApp on, posts and pages on, small icons, and the floating placement on.",
        "actual": "get_option( 'tss_options' ) is not an array. The option was never created, so WordPress returns false.",
        "why": "Defaults are written only from BD_TSS_Settings::bd_tss_settings_init(), which runs on admin_init. bd_tss_plugin_activate() loads that settings class only when is_admin() is true. The functionality check runs through WP-CLI, and WP-CLI does not define WP_ADMIN, so is_admin() is false. The settings class is skipped, add_option() never runs, and tss_options stays missing. The same gap affects any boot that is not an admin request, including plugin activation from WP-CLI: the front end then reads tss_options before it exists.",
        "fix": "Seed tss_options from bd_tss_default_options() whenever the option is missing, including from register_activation_hook. Do not require is_admin() for that write. Keep admin_init for registering the settings screen.",
        "where": [
            {
                "file": "top-social-share.php",
                "lines": "47-57",
                "symbol": "bd_tss_plugin_activate()",
                "detail": "Loads includes/class-tss-settings.php only inside is_admin(). Otherwise the file that saves defaults is never included.",
            },
            {
                "file": "includes/class-tss-settings.php",
                "lines": "40-51",
                "symbol": "BD_TSS_Settings::load()",
                "detail": "Reads tss_options once in the constructor and attaches bd_tss_settings_init to admin_init.",
            },
            {
                "file": "includes/class-tss-settings.php",
                "lines": "142-145",
                "symbol": "BD_TSS_Settings::bd_tss_settings_init()",
                "detail": "This is the only add_option() call for tss_options, and it runs only when the constructor saw false.",
            },
        ],
    },
    "front end share bar is missing": {
        "title": "Front end share bar does not render for visitors",
        "screenshot_alt": "Front end of the post when the share bar failed",
        "expected": "A published post shows the floating share bar, icons around the content, and the [top-share] shortcode. Facebook, Twitter, Pinterest, and LinkedIn are visible. WhatsApp appears only in a mobile browser.",
        "actual": "The public post does not show that share bar.",
        "why": "On a normal front-end request is_admin() is false, so bd_tss_plugin_activate() never loads the settings class and tss_options is never created. The share class still loads and reads that missing option. bd_tss_build_share_icons() then uses tss_options as an array, which throws on PHP 8 and the public page dies before the icons render.",
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
    },
}


def load_report(path):
    file_path = pathlib.Path(path)
    if not file_path.is_file():
        return {}
    return json.loads(file_path.read_text())


def failure_message(report, log_text):
    if report.get("message"):
        return report["message"]
    match = re.search(r"FAIL:\s*(.+)", log_text)
    if match:
        return match.group(1).strip()
    if "Error:" in log_text or "Fatal error:" in log_text:
        return "WordPress install or plugin boot failed before the checks finished"
    return "Plugin functionality check failed"


def guide_for(report, message):
    guide = report.get("guide") or {}
    if guide:
        return guide
    if message in FALLBACK_GUIDES:
        return FALLBACK_GUIDES[message]
    return {
        "title": message,
        "expected": "The functionality check named in the log should pass.",
        "actual": "The check stopped at this assertion.",
        "why": "The saved log does not include a structured diagnosis. Start from the assertion text in .github/workflows/php.yml and the stack in the log below.",
        "fix": "Open the assertion in .github/workflows/php.yml and follow it to the plugin function it calls.",
        "where": [],
    }


def runtime_lines(report):
    if not report:
        return ["No structured runtime report was written. The log below is the output that was captured."]
    lines = []
    wordpress = report.get("wordpress")
    if wordpress:
        lines.append(f"WordPress {wordpress}")
    if report.get("is_admin") is not None:
        lines.append(f"is_admin(): {str(report.get('is_admin')).lower()}")
    if "wp_admin" in report:
        value = report.get("wp_admin")
        lines.append("WP_ADMIN: " + ("undefined" if value is None else str(value).lower()))
    if "settings_loaded" in report:
        lines.append(f"BD_TSS_Settings loaded: {str(report.get('settings_loaded')).lower()}")
    if "share_loaded" in report:
        lines.append(f"BD_TSS_Social_Share loaded: {str(report.get('share_loaded')).lower()}")
    if report.get("option_type"):
        lines.append(f"tss_options type: {report.get('option_type')}")
    if report.get("admin_init_count") is not None:
        lines.append(f"admin_init ran: {report.get('admin_init_count')} time(s)")
    context = report.get("context") or {}
    for key, value in context.items():
        lines.append(f"{key}: {value}")
    return lines


def excerpt(workspace, item):
    file_name = item.get("file")
    line_span = str(item.get("lines") or "")
    if not file_name or "-" not in line_span:
        return ""
    start_text, end_text = line_span.split("-", 1)
    try:
        start = int(start_text)
        end = int(end_text)
    except ValueError:
        return ""
    source = pathlib.Path(workspace) / file_name
    if not source.is_file():
        return ""
    rows = source.read_text().splitlines()
    chosen = []
    for number in range(start, end + 1):
        if 1 <= number <= len(rows):
            chosen.append(f"{number:4}  {rows[number - 1]}")
    return "\n".join(chosen)


def write_html(destination, guide, runtime, meta):
    locations = []
    for item in guide.get("where") or []:
        code = excerpt(meta["workspace"], item)
        locations.append(
            f"""
            <section class="place">
              <header>
                <code>{html.escape(item.get("file", ""))}</code>
                <span>lines {html.escape(str(item.get("lines", "")))}</span>
              </header>
              <h3>{html.escape(item.get("symbol", ""))}</h3>
              <p>{html.escape(item.get("detail", ""))}</p>
              <pre>{html.escape(code)}</pre>
            </section>
            """
        )
    runtime_html = "".join(f"<li>{html.escape(line)}</li>" for line in runtime)
    document = f"""<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Functional check failure</title>
  <style>
    * {{ box-sizing: border-box; }}
    body {{
      margin: 0;
      background: #f4f1ea;
      color: #1d1a16;
      font: 16px/1.45 "Segoe UI", Helvetica, Arial, sans-serif;
    }}
    main {{
      width: 1240px;
      margin: 0 auto;
      padding: 28px 32px 36px;
    }}
    .eyebrow {{
      color: #8a2b2b;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      font-size: 13px;
    }}
    h1 {{
      margin: 6px 0 18px;
      font-size: 32px;
      line-height: 1.2;
    }}
    .grid {{
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }}
    .card, .place {{
      background: #fff;
      border: 1px solid #e4d8c8;
      border-radius: 12px;
      padding: 16px 18px;
    }}
    .card h2, .why h2, .fix h2 {{
      margin: 0 0 8px;
      font-size: 14px;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }}
    .expected h2 {{ color: #1f6b3a; }}
    .actual h2 {{ color: #8a2b2b; }}
    .why, .fix {{ margin-top: 16px; }}
    .places {{
      display: grid;
      grid-template-columns: 1fr;
      gap: 12px;
      margin-top: 16px;
    }}
    .place header {{
      display: flex;
      justify-content: space-between;
      gap: 12px;
      color: #6b5e52;
      font-size: 13px;
    }}
    .place h3 {{ margin: 8px 0; font-size: 16px; }}
    .place p {{ margin: 0 0 8px; }}
    pre {{
      margin: 0;
      padding: 10px 12px;
      overflow: auto;
      background: #231f1b;
      color: #f6efe4;
      border-radius: 8px;
      font: 12px/1.45 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
      white-space: pre;
    }}
    ul {{ margin: 8px 0 0; padding-left: 18px; }}
    .meta {{
      margin-top: 16px;
      color: #6b5e52;
      font-size: 13px;
    }}
  </style>
</head>
<body>
  <main>
    <div class="eyebrow">Top Social Share · functionality check</div>
    <h1>{html.escape(guide.get("title") or "Functionality check failed")}</h1>
    <div class="grid">
      <section class="card expected">
        <h2>Expected</h2>
        <p>{html.escape(guide.get("expected", ""))}</p>
      </section>
      <section class="card actual">
        <h2>Actual</h2>
        <p>{html.escape(guide.get("actual", ""))}</p>
      </section>
    </div>
    <section class="card why">
      <h2>Why it failed</h2>
      <p>{html.escape(guide.get("why", ""))}</p>
      <ul>{runtime_html}</ul>
    </section>
    <div class="places">{''.join(locations)}</div>
    <section class="card fix">
      <h2>Where to change it</h2>
      <p>{html.escape(guide.get("fix", ""))}</p>
    </section>
    <p class="meta">Workflow {html.escape(meta.get("run_url", ""))} · {html.escape(meta.get("ref_name", ""))} · {html.escape(meta.get("commit", ""))}</p>
  </main>
</body>
</html>
"""
    destination.write_text(document)


def write_body(destination, guide, runtime, meta, log_text, image_url):
    where_lines = []
    for item in guide.get("where") or []:
        where_lines.append(
            f"- `{item.get('file')}` `{item.get('symbol')}` lines {item.get('lines')}: {item.get('detail')}"
        )
    where_block = "\n".join(where_lines) if where_lines else "- See the assertion in `.github/workflows/php.yml`."
    runtime_block = "\n".join(f"- {line}" for line in runtime)
    log_excerpt = log_text[-8000:] if log_text else "No log was captured."
    image = ""
    if image_url:
        alt = guide.get("screenshot_alt") or "Functional check failure"
        image = f"\n![{alt}]({image_url})\n"
    body = f"""## {guide.get("title") or "Plugin functionality check failed"}

The functionality check failed on **{meta.get("message")}**.
{image}
### Expected

{guide.get("expected", "")}

### Actual

{guide.get("actual", "")}

### Why this happens

{guide.get("why", "")}

### Where to fix

{where_block}

### What to change

{guide.get("fix", "")}

### Runtime

{runtime_block}

### Workflow

- Run: {meta.get("run_url", "")}
- Commit: `{meta.get("commit", "")}`
- Branch: `{meta.get("ref_name", "")}`
- Event: `{meta.get("event_name", "")}`

<details>
<summary>Check log</summary>

```
{log_excerpt}
```

</details>
"""
    destination.write_text(body)


def read_log(path):
    file_path = pathlib.Path(path)
    if not file_path.is_file():
        return ""
    return file_path.read_text(errors="replace")


def build(args):
    report = load_report(args.report)
    log_text = read_log(args.log)
    message = failure_message(report, log_text)
    guide = guide_for(report, message)
    runtime = runtime_lines(report)
    meta = {
        "workspace": args.workspace,
        "message": message,
        "run_url": args.run_url,
        "commit": args.commit,
        "ref_name": args.ref_name,
        "event_name": args.event_name,
    }
    out_dir = pathlib.Path(args.out)
    out_dir.mkdir(parents=True, exist_ok=True)
    write_html(out_dir / "failure.html", guide, runtime, meta)
    write_body(out_dir / "issue.md", guide, runtime, meta, log_text, args.image_url)
    title = "Functional tests failed: " + message
    (out_dir / "title.txt").write_text(textwrap.shorten(title, width=240, placeholder=""))
    print(out_dir / "failure.html")


def github_request(method, url, token, payload=None, ok_status=()):
    data = None if payload is None else json.dumps(payload).encode()
    request = urllib.request.Request(
        url,
        data=data,
        method=method,
        headers={
            "Authorization": f"Bearer {token}",
            "Accept": "application/vnd.github+json",
            "Content-Type": "application/json",
            "X-GitHub-Api-Version": "2022-11-28",
            "User-Agent": "top-social-share-functional-check",
        },
    )
    try:
        with urllib.request.urlopen(request) as response:
            body = response.read().decode()
            return response.status, json.loads(body) if body else {}
    except urllib.error.HTTPError as error:
        if error.code in ok_status:
            return error.code, None
        detail = error.read().decode()
        raise SystemExit(f"GitHub API {method} {url} failed: {error.code} {detail}")


def upload_screenshot(image_path, name=None):
    """Store the screenshot on functional-test-reports and return a URL that renders in issues."""
    repo = os.environ["GITHUB_REPOSITORY"]
    token = os.environ["GH_TOKEN"]
    run_id = name or os.environ["GITHUB_RUN_ID"]
    branch = "functional-test-reports"
    path = f".github/functional-test-reports/{run_id}.png"
    shot = pathlib.Path(image_path).read_bytes()
    _, ref = github_request("GET", f"https://api.github.com/repos/{repo}/git/ref/heads/master", token)
    master_sha = ref["object"]["sha"]
    branch_url = f"https://api.github.com/repos/{repo}/git/ref/heads/{branch}"
    status, _ = github_request("GET", branch_url, token, ok_status=(404,))
    if status == 404:
        github_request(
            "POST",
            f"https://api.github.com/repos/{repo}/git/refs",
            token,
            {"ref": f"refs/heads/{branch}", "sha": master_sha},
        )
    content_url = f"https://api.github.com/repos/{repo}/contents/{path}"
    payload = {
        "message": f"Add functionality-check failure screenshot for run {run_id}",
        "content": base64.b64encode(shot).decode(),
        "branch": branch,
    }
    status, existing = github_request("GET", f"{content_url}?ref={branch}", token, ok_status=(404,))
    if status != 404 and existing:
        payload["sha"] = existing["sha"]
    github_request("PUT", content_url, token, payload)
    return f"https://raw.githubusercontent.com/{repo}/{branch}/{path}"


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--workspace", default=".")
    parser.add_argument("--report", default="/tmp/tss-failure.json")
    parser.add_argument("--log", default="/tmp/tss-functional-tests.log")
    parser.add_argument("--out", default="/tmp/tss-report")
    parser.add_argument("--image-url", default="")
    parser.add_argument("--upload-screenshot", default="")
    parser.add_argument("--upload-name", default="")
    parser.add_argument("--run-url", default=os.environ.get("RUN_URL", ""))
    parser.add_argument("--commit", default=os.environ.get("COMMIT", ""))
    parser.add_argument("--ref-name", default=os.environ.get("REF_NAME", ""))
    parser.add_argument("--event-name", default=os.environ.get("EVENT_NAME", ""))
    args = parser.parse_args()
    if args.upload_screenshot:
        print(upload_screenshot(args.upload_screenshot, args.upload_name or None))
        return
    build(args)


if __name__ == "__main__":
    main()
