const fs = require('fs');

async function main() {
    const res = await fetch('http://localhost:9222/json');
    const targets = await res.json();
    const target = targets.find(t => t.url.includes('reports.php?type=unpaid_invoices')) || targets[0];
    if (!target) {
        console.error("No browser target found");
        return;
    }

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
        console.log("Connected to browser session via CDP");
        
        // Navigate or reload
        await sendCmd('Page.navigate', { url: 'https://act.active.lk/reports.php?type=unpaid_invoices' });
        await new Promise(r => setTimeout(r, 2500));

        // Evaluate page contents
        const evalRes = await sendCmd('Runtime.evaluate', {
            expression: `(() => {
                const text = document.body.innerText;
                const hasDeprecated = text.includes('Deprecated') || text.includes('number_format');
                const showingLine = document.querySelector('#unpaidInvoicesContainer')?.innerText?.split('\\n')?.slice(0, 5)?.join(' | ') || '';
                const customerCards = document.querySelectorAll('#unpaidInvoicesContainer .card').length;
                const ribbonPills = Array.from(document.querySelectorAll('.metric-pill')).map(p => p.innerText.replace(/\\n/g, ' '));
                return {
                    hasDeprecated,
                    showingLine,
                    customerCards,
                    ribbonPills
                };
            })()`,
            returnByValue: true
        });

        console.log("Result:", JSON.stringify(evalRes.result.value, null, 2));

        // Take screenshot
        const screenshot = await sendCmd('Page.captureScreenshot', { format: 'png' });
        if (screenshot && screenshot.data) {
            fs.writeFileSync('C:/Users/shahe/.gemini/antigravity-ide/brain/957a6651-ba76-425f-885a-fa596df71c1d/unpaid_invoices_fixed.png', Buffer.from(screenshot.data, 'base64'));
            console.log("Saved screenshot to unpaid_invoices_fixed.png");
        }

        ws.close();
    };
}

main().catch(console.error);
