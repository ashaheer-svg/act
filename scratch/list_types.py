import re

with open("reports.php", "r", encoding="utf-8", errors="ignore") as f:
    text = f.read()

types = set(re.findall(r"\$type\s*===?\s*['\"]([^'\"]+)['\"]", text))
print("Report types found in reports.php:")
for t in sorted(types):
    print(" -", t)
