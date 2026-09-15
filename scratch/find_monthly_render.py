import sys
sys.stdout.reconfigure(encoding='utf-8')

with open("reports.php", "r", encoding="utf-8", errors="ignore") as f:
    lines = f.readlines()

for i, line in enumerate(lines):
    if "getMonthlySales" in line or ("monthly" in line.lower() and "breakdown" in line.lower()) or "$type === 'monthly'" in line or "$type == 'monthly'" in line:
        print(f"Line {i+1}: {line.strip()[:120]}")
