import json

path = r"C:\Users\shahe\.gemini\antigravity-ide\brain\b31dc8df-5f81-4e48-a1f4-f1430cc9db81\.system_generated\logs\transcript.jsonl"
with open(path, "r", encoding="utf-8") as f:
    for line in f:
        data = json.loads(line)
        if data.get("type") == "USER_INPUT":
            content = data.get("content", "").strip()
            print(f"STEP {data.get('step_index')}: {content}\n---")
