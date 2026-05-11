
def check_balance(file_path):
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()
    
    stack = []
    pairs = {')': '(', '}': '{', ']': '['}
    php_open = False
    
    # We also need to track <?php and ?>
    i = 0
    while i < len(content):
        if content[i:i+5] == '<?php':
            if php_open:
                print("Error: Nested <?php at index", i)
            php_open = True
            i += 5
            continue
        if content[i:i+2] == '?>':
            if not php_open:
                print("Error: Closing ?> without <?php at index", i)
            php_open = False
            i += 2
            continue
        
        char = content[i]
        if char in '({[':
            stack.append((char, i))
        elif char in ')}]':
            if not stack:
                print(f"Error: Extra closing {char} at index {i}")
            else:
                top, pos = stack.pop()
                if top != pairs[char]:
                    print(f"Error: Mismatched {char} at index {i}, matches {top} at index {pos}")
        i += 1
    
    if php_open:
        print("Error: Unclosed <?php at end of file")
    
    while stack:
        char, pos = stack.pop()
        # Find line number for pos
        line_no = content.count('\n', 0, pos) + 1
        print(f"Error: Unclosed {char} at line {line_no} (index {pos})")

check_balance(r'c:\Users\shahe\OneDrive\working\ai-sales\settings.php')
