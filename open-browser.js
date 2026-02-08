const { default: open } = require('open');

async function openBrowser() {
    const url = 'http://bcxmce.local/';
    
    console.log(`Opening ${url} in default browser...`);
    
    try {
        await open(url);
        console.log('Browser opened successfully!');
    } catch (error) {
        console.error(`Error opening browser: ${error}`);
    }
}

// Wait a bit for the server to start, then open the browser
setTimeout(openBrowser, 5000);

console.log('Waiting for services to start before opening browser...');