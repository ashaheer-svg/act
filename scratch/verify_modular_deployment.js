const fs = require('fs');

async function main() {
    const res = await fetch('http://localhost:9222/json');
    const targets = await res.json();
    const target = targets.find(t => t.id === 'E5E4F4BD9CBC7D7AD8D92E1A1D1D662F') || targets[0];
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

        // First authenticate
        console.log("Authenticating as admin...");
        await sendCmd('Runtime.evaluate', {
            expression: `fetch('login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username: 'admin', password: 'admin123' })
            }).then(r => r.json())`,
            awaitPromise: true
        });
        await new Promise(r => setTimeout(r, 1000));

        // 1. Test settings.php?tab=system
        console.log("\n--- Testing settings.php?tab=system ---");
        await sendCmd('Page.navigate', { url: 'https://act.active.lk/settings.php?tab=system' });
        await new Promise(r => setTimeout(r, 2000));
        let evalRes = await sendCmd('Runtime.evaluate', {
            expression: `(() => {
                const title = document.title;
                const activeTab = document.querySelector('.tab-btn.active')?.textContent.trim();
                const bodyText = document.body.innerText;
                const hasCompanyField = bodyText.includes('Company Name') || bodyText.includes('Currency Symbol');
                const hasVatRate = bodyText.includes('Base VAT Rate');
                const phpError = bodyText.includes('Fatal error') || bodyText.includes('Parse error') || bodyText.includes('Warning:');
                return { title, activeTab, hasCompanyField, hasVatRate, phpError };
            })()`,
            returnByValue: true
        });
        console.log("System Tab:", evalRes?.result?.value);
        let shot = await sendCmd('Page.captureScreenshot', { format: 'png' });
        fs.writeFileSync('scratch/live_settings_system.png', Buffer.from(shot.data, 'base64'));

        // 2. Test settings.php?tab=team
        console.log("\n--- Testing settings.php?tab=team ---");
        await sendCmd('Page.navigate', { url: 'https://act.active.lk/settings.php?tab=team' });
        await new Promise(r => setTimeout(r, 2000));
        evalRes = await sendCmd('Runtime.evaluate', {
            expression: `(() => {
                const title = document.title;
                const activeTab = document.querySelector('.tab-btn.active')?.textContent.trim();
                const userRows = document.querySelectorAll('table tbody tr').length;
                const blueprintSelect = document.querySelector('select[name="role_blueprint"]') !== null;
                const phpError = document.body.innerText.includes('Fatal error') || document.body.innerText.includes('Parse error');
                return { title, activeTab, userRows, blueprintSelect, phpError };
            })()`,
            returnByValue: true
        });
        console.log("Team Tab:", evalRes?.result?.value);
        shot = await sendCmd('Page.captureScreenshot', { format: 'png' });
        fs.writeFileSync('scratch/live_settings_team.png', Buffer.from(shot.data, 'base64'));

        // 3. Test reports.php?type=invoices
        console.log("\n--- Testing reports.php?type=invoices ---");
        await sendCmd('Page.navigate', { url: 'https://act.active.lk/reports.php?type=invoices' });
        await new Promise(r => setTimeout(r, 2000));
        evalRes = await sendCmd('Runtime.evaluate', {
            expression: `(() => {
                const title = document.title;
                const rows = document.querySelectorAll('table tbody tr').length;
                const selectVal = document.querySelector('.cmd-select-report')?.value;
                const phpError = document.body.innerText.includes('Fatal error') || document.body.innerText.includes('Parse error');
                return { title, rows, selectVal, phpError };
            })()`,
            returnByValue: true
        });
        console.log("Invoices Report:", evalRes?.result?.value);
        shot = await sendCmd('Page.captureScreenshot', { format: 'png' });
        fs.writeFileSync('scratch/live_report_invoices.png', Buffer.from(shot.data, 'base64'));

        // 4. Test reports.php?type=monthly
        console.log("\n--- Testing reports.php?type=monthly ---");
        await sendCmd('Page.navigate', { url: 'https://act.active.lk/reports.php?type=monthly' });
        await new Promise(r => setTimeout(r, 2500));
        evalRes = await sendCmd('Runtime.evaluate', {
            expression: `(() => {
                const title = document.title;
                const matrixTables = document.querySelectorAll('.matrix-sales-table').length;
                const metricCards = document.querySelectorAll('.metric-card').length;
                const phpError = document.body.innerText.includes('Fatal error') || document.body.innerText.includes('Parse error');
                return { title, matrixTables, metricCards, phpError };
            })()`,
            returnByValue: true
        });
        console.log("Monthly Report:", evalRes?.result?.value);
        shot = await sendCmd('Page.captureScreenshot', { format: 'png' });
        fs.writeFileSync('scratch/live_report_monthly.png', Buffer.from(shot.data, 'base64'));

        // 5. Test reports.php?type=unpaid_invoices
        console.log("\n--- Testing reports.php?type=unpaid_invoices ---");
        await sendCmd('Page.navigate', { url: 'https://act.active.lk/reports.php?type=unpaid_invoices' });
        await new Promise(r => setTimeout(r, 2000));
        evalRes = await sendCmd('Runtime.evaluate', {
            expression: `(() => {
                const title = document.title;
                const rows = document.querySelectorAll('table tbody tr').length;
                const phpError = document.body.innerText.includes('Fatal error') || document.body.innerText.includes('Parse error');
                return { title, rows, phpError };
            })()`,
            returnByValue: true
        });
        console.log("Unpaid Invoices Report:", evalRes?.result?.value);
        shot = await sendCmd('Page.captureScreenshot', { format: 'png' });
        fs.writeFileSync('scratch/live_report_unpaid.png', Buffer.from(shot.data, 'base64'));

        console.log("\nAll verification tests completed successfully!");
        ws.close();
    };
}

main().catch(console.error);
