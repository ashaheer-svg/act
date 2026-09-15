with open("reports.php", "r", encoding="utf-8", errors="ignore") as f:
    lines = f.readlines()

for i, line in enumerate(lines):
    if "monthly" in line:
        print(f"Line {i+1}: {line.strip()[:100]}")
