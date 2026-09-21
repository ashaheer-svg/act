const fs = require('fs');

async function main() {
    const res = await fetch('http://localhost:9222/json');
    const targets = await res.json();
    const target = targets.find(t => t.id === 'E5E4F4BD9CBC7D7AD8D92E1A1D1D662F');
    if (!target) return;

    const ws = new WebSocket(target.webSocketDebuggerUrl);

    ws.onopen = () => {
        // Inspect inputs
        ws.send(JSON.stringify({
            id: 1,
            method: 'Runtime.evaluate',
            params: {
                expression: `(() => {
                    const u = document.querySelector('input[name="username"]')?.value;
                    const p = document.querySelector('input[name="password"]')?.value;
                    return { username: u, passLen: p ? p.length : 0 };
                })()`,
                returnByValue: true
            }
        }));
    };

    ws.onmessage = (event) => {
        const msg = JSON.parse(event.data);
        if (msg.id === 1) {
            console.log('Login Inputs:', msg.result?.result?.value);
            // Submit form via fetch
            const submitScript = `
                fetch('login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        username: document.querySelector('input[name="username"]').value,
                        password: document.querySelector('input[name="password"]').value
                    })
                })
                .then(r => r.json())
                .then(d => {
                    console.log('Login result:', d);
                    if (d.success) {
                        window.location.href = 'rbac.php';
                    }
                })
            `;
            ws.send(JSON.stringify({
                id: 2,
                method: 'Runtime.evaluate',
                params: { expression: submitScript }
            }));
            setTimeout(() => {
                ws.send(JSON.stringify({
                    id: 3,
                    method: 'Runtime.evaluate',
                    params: { expression: 'window.location.href', returnByValue: true }
                }));
            }, 2500);
        } else if (msg.id === 3) {
            console.log('Current URL after login attempt:', msg.result?.result?.value);
            setTimeout(() => {
                ws.send(JSON.stringify({
                    id: 4,
                    method: 'Page.captureScreenshot',
                    params: { format: 'png' }
                }));
            }, 1000);
        } else if (msg.id === 4) {
            const buf = Buffer.from(msg.result.data, 'base64');
            fs.writeFileSync('scratch/rbac_live_after_login.png', buf);
            console.log('Saved scratch/rbac_live_after_login.png');
            ws.close();
        }
    };
}

main().catch(console.error);
