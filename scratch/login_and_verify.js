const fs = require('fs');

async function main() {
    const res = await fetch('http://localhost:9222/json');
    const targets = await res.json();
    const target = targets.find(t => t.id === 'E5E4F4BD9CBC7D7AD8D92E1A1D1D662F');
    if (!target) return;

    const ws = new WebSocket(target.webSocketDebuggerUrl);

    ws.onopen = () => {
        // Click the Login button
        ws.send(JSON.stringify({
            id: 1,
            method: 'Runtime.evaluate',
            params: { expression: "document.querySelector('button[type=\"submit\"]').click();" }
        }));
    };

    ws.onmessage = (event) => {
        const msg = JSON.parse(event.data);
        if (msg.id === 1) {
            setTimeout(() => {
                // Navigate to rbac.php
                ws.send(JSON.stringify({
                    id: 2,
                    method: 'Page.navigate',
                    params: { url: 'https://act.active.lk/rbac.php' }
                }));
            }, 1500);
        } else if (msg.id === 2) {
            setTimeout(() => {
                const evalExpr = `(() => {
                    const table = document.getElementById('rbacMatrixTable');
                    const catBulkBtns = document.querySelectorAll('.btn-cat-bulk').length;
                    const rows = document.querySelectorAll('#rbacMatrixTable tbody tr').length;
                    const auditRows = document.querySelectorAll('.card table tbody tr').length;
                    const presetBtns = document.querySelectorAll('.btn-preset').length;
                    return {
                        title: document.title,
                        tableExists: !!table,
                        catBulkBtnsCount: catBulkBtns,
                        rowsCount: rows,
                        auditRowsCount: auditRows,
                        presetBtnsCount: presetBtns
                    };
                })()`;

                ws.send(JSON.stringify({
                    id: 3,
                    method: 'Runtime.evaluate',
                    params: { expression: evalExpr, returnByValue: true }
                }));
            }, 2000);
        } else if (msg.id === 3) {
            console.log('RBAC Evaluation After Login:', JSON.stringify(msg.result?.result?.value, null, 2));
            ws.send(JSON.stringify({
                id: 4,
                method: 'Page.captureScreenshot',
                params: { format: 'png' }
            }));
        } else if (msg.id === 4) {
            const buf = Buffer.from(msg.result.data, 'base64');
            fs.writeFileSync('scratch/rbac_improved_verified.png', buf);
            console.log('Saved scratch/rbac_improved_verified.png');
            ws.close();
        }
    };
}

main().catch(console.error);
