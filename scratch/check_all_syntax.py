
import os

def check_balance(file_path):
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            content = f.read()
    except Exception as e:
        # print(f"Could not read {file_path}: {e}")
        return
    
    stack = []
    pairs = {')': '(', '}': '{', ']': '['}
    php_open = False
    
    i = 0
    while i < len(content):
        if content[i:i+5] == '<?php':
            php_open = True
            i += 5
            continue
        if content[i:i+2] == '?>':
            php_open = False
            i += 2
            continue
        
        char = content[i]
        if char in '({[':
            stack.append((char, i))
        elif char in ')}]':
            if not stack:
                pass # print(f"Error in {file_path}: Extra closing {char} at index {i}")
            else:
                top, pos = stack.pop()
                if top != pairs[char]:
                    pass # print(f"Error in {file_path}: Mismatched {char} at index {i}")
        i += 1
    
    if php_open:
        # print(f"Error in {file_path}: Unclosed <?php")
        pass
    
    if stack:
        char, pos = stack.pop()
        line_no = content.count('\n', 0, pos) + 1
        print(f"Error in {file_path}: Unclosed {char} at line {line_no}")

root = r'c:\Users\shahe\OneDrive\working\ai-sales'
for file in os.listdir(root):
    if file.endswith('.php'):
        check_balance(os.path.join(root, file))
