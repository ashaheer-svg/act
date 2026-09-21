const fs = require('fs');

async function main() {
    const res = await fetch('http://localhost:9222/json');
    const targets = await res.json();
    const target = targets.find(t => t.id === 'E5E4F4BD9CBC7D7AD8D92E1A1D1D662F');
    if (!target) return;

    const ws = new WebSocket(target.webSocketDebuggerUrl);

    ws.onopen = () => {
        const script = `
            fetch('login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username: 'admin', password: 'admin123' })
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    window.location.href = 'rbac.php';
                }
            })
        `;
        ws.send(JSON.stringify({
            id: 1,
            method: 'Runtime.evaluate',
            params: { expression: script }
        }));
    };

    ws.onmessage = (event) => {
        const msg = JSON.parse(event.data);
        if (msg.id === 1) {
            setTimeout(() => {
                ws.send(JSON.stringify({
                    id: 2,
                    method: 'Runtime.evaluate',
                    params: {
                        expression: `(() => {
                            return {
                                url: window.location.href,
                                title: document.title,
                                tableExists: !!document.getElementById('rbacMatrixTable'),
                                rowsCount: document.querySelectorAll('#rbacMatrixTable tbody tr').length,
                                catBulkBtnsCount: document.querySelectorAll('.btn-cat-bulk').length,
                                cloneBtnExists: !!document.querySelector('.clone-modal-overlay')
                            };
                        })()`,
                        returnByValue: true
                    }
                }));
            }, 3000);
        } else if (msg.id === 2) {
            console.log('RBAC State after logging in with admin credentials:', JSON.stringify(msg.result?.result?.value, null, 2));
            ws.send(JSON.stringify({
                id: 3,
                method: 'Page.captureScreenshot',
                params: { format: 'png' }
            }));
        } else if (msg.id === 3) {
            const buf = Buffer.from(msg.result.data, 'base64');
            fs.writeFileSync('scratch/rbac_live_success.png', buf);
            console.log('Saved scratch/rbac_live_success.png');
            ws.close();
        }
    };
}

main().catch(console.error);
