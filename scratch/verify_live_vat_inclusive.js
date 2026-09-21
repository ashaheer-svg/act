const fs = require('fs');

async function main() {
    const res = await fetch('http://localhost:9222/json');
    const targets = await res.json();
    const target = targets.find(t => t.url.includes('active.lk')) || targets[0];
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

        // 1. Check unpaid invoices
        console.log("Navigating to unpaid_invoices...");
        await sendCmd('Page.navigate', { url: 'https://act.active.lk/reports.php?type=unpaid_invoices' });
        await new Promise(r => setTimeout(r, 2000));
        const unpaidEval = await sendCmd('Runtime.evaluate', {
            expression: `(() => {
                return {
                    showing: document.querySelector('#unpaidInvoicesContainer')?.innerText?.split('\\n')?.slice(0, 3)?.join(' | '),
                    totalReceivables: document.querySelector('.metric-pill .metric-pill-val')?.innerText
                };
            })()`,
            returnByValue: true
        });
        console.log("Unpaid Invoices Result:", unpaidEval.result.value);

        // 2. Check vat_review.php
        console.log("Navigating to vat_review.php...");
        await sendCmd('Page.navigate', { url: 'https://act.active.lk/vat_review.php?era=taxable' });
        await new Promise(r => setTimeout(r, 2500));
        const vatReviewEval = await sendCmd('Runtime.evaluate', {
            expression: `(() => {
                const bodyText = document.body.innerText;
                const rows = Array.from(document.querySelectorAll('table tbody tr')).slice(0, 5).map(tr => tr.innerText.replace(/\\s+/g, ' '));
                const badges = Array.from(document.querySelectorAll('.badge, .status-badge, td span')).slice(0, 10).map(s => s.innerText);
                return {
                    title: document.title,
                    sampleRows: rows,
                    hasErrors: bodyText.includes('Fatal error') || bodyText.includes('Warning')
                };
            })()`,
            returnByValue: true
        });
        console.log("VAT Review Result:", vatReviewEval.result.value);

        // Take screenshot of vat_review
        const screenshot = await sendCmd('Page.captureScreenshot', { format: 'png' });
        if (screenshot && screenshot.data) {
            fs.writeFileSync('C:/Users/shahe/.gemini/antigravity-ide/brain/957a6651-ba76-425f-885a-fa596df71c1d/live_vat_review_verified.png', Buffer.from(screenshot.data, 'base64'));
            console.log("Saved screenshot to live_vat_review_verified.png");
        }

        // 3. Check reports.php?type=invoices
        console.log("Navigating to reports.php?type=invoices...");
        await sendCmd('Page.navigate', { url: 'https://act.active.lk/reports.php?type=invoices' });
        await new Promise(r => setTimeout(r, 2000));
        const invEval = await sendCmd('Runtime.evaluate', {
            expression: `(() => {
                const ribbonPills = Array.from(document.querySelectorAll('.metric-pill')).map(p => p.innerText.replace(/\\n/g, ' '));
                return {
                    title: document.title,
                    ribbonPills
                };
            })()`,
            returnByValue: true
        });
        console.log("Invoices Report Result:", invEval.result.value);

        const scrInvoices = await sendCmd('Page.captureScreenshot', { format: 'png' });
        if (scrInvoices && scrInvoices.data) {
            fs.writeFileSync('C:/Users/shahe/.gemini/antigravity-ide/brain/957a6651-ba76-425f-885a-fa596df71c1d/live_invoices_report_verified.png', Buffer.from(scrInvoices.data, 'base64'));
            console.log("Saved screenshot to live_invoices_report_verified.png");
        }

        ws.close();
    };
}

main().catch(console.error);
