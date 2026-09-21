const fs = require('fs');

async function main() {
    const res = await fetch('http://localhost:9222/json');
    const targets = await res.json();
    const target = targets.find(t => t.id === 'E5E4F4BD9CBC7D7AD8D92E1A1D1D662F') || targets[0];
    if (!target) return;

    const ws = new WebSocket(target.webSocketDebuggerUrl);

    function sendCmd(method, params = {}) {
        return new Promise((resolve) => {
            const id = Math.floor(Math.random() * 1000000);
            const handler = (evt) => {
                const data = JSON.parse(evt.data);
                if (data.id === id) {
                    ws.removeEventListener('message', handler);
                    resolve(data.result);
                }
            };
            ws.addEventListener('message', handler);
            ws.send(JSON.stringify({ id, method, params }));
        });
    }

    ws.onopen = async () => {
        // Authenticate
        await sendCmd('Runtime.evaluate', {
            expression: `fetch('login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username: 'admin', password: 'admin123' })
            }).then(r => r.json())`,
            awaitPromise: true
        });
        await new Promise(r => setTimeout(r, 1000));

        // 1. Invoices report with ASN filter
        console.log("Navigating to reports.php?type=invoices&search=ASN...");
        await sendCmd('Page.navigate', { url: 'https://act.active.lk/reports.php?type=invoices&search=ASN' });
        await new Promise(r => setTimeout(r, 2000));

        let evalRes = await sendCmd('Runtime.evaluate', {
            expression: `(() => {
                const rows = Array.from(document.querySelectorAll('table tbody tr')).map(tr => {
                    const inv = tr.querySelector('.dense-doc-num')?.textContent.trim();
                    const cust = tr.querySelector('td:nth-child(3)')?.textContent.trim();
                    const status = tr.querySelector('.dense-badge-settled, .dense-badge-unpaid')?.textContent.trim();
                    return { inv, cust: cust?.slice(0, 25), status };
                }).filter(r => r.inv);
                return { count: rows.length, sample: rows.slice(0, 5) };
            })()`,
            returnByValue: true
        });
        console.log("Invoices Report ASN check:", JSON.stringify(evalRes?.result?.value, null, 2));

        let shot = await sendCmd('Page.captureScreenshot', { format: 'png' });
        fs.writeFileSync('scratch/live_asn_invoices.png', Buffer.from(shot.data, 'base64'));

        // 2. Settings tax tab
        console.log("Navigating to settings.php?tab=tax...");
        await sendCmd('Page.navigate', { url: 'https://act.active.lk/settings.php?tab=tax' });
        await new Promise(r => setTimeout(r, 2000));

        evalRes = await sendCmd('Runtime.evaluate', {
            expression: `(() => {
                const text = document.body.innerText;
                const hasObsoleteRule = text.includes('New Seq AS 18% VAT');
                const hasAsnRule = text.includes('New Seq ASN 18% VAT');
                return { hasObsoleteRule, hasAsnRule };
            })()`,
            returnByValue: true
        });
        console.log("Tax Rules check:", JSON.stringify(evalRes?.result?.value, null, 2));

        shot = await sendCmd('Page.captureScreenshot', { format: 'png' });
        fs.writeFileSync('scratch/live_tax_rules_cleaned.png', Buffer.from(shot.data, 'base64'));

        console.log("Live verification complete!");
        ws.close();
    };
}

main().catch(console.error);
