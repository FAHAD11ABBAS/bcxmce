# Automatic Browser Opening for BCxMCE Project

This setup enables automatic opening of your BCxMCE WordPress project in the browser when you start the development server.

## Files Created:

1. **start_project.sh** - Shell script for macOS/Linux
2. **start_project.bat** - Batch file for Windows  
3. **open-browser.js** - Node.js script for cross-platform browser opening
4. **package.json** - Package configuration with start scripts
5. **AUTOSTART_INSTRUCTIONS.md** - Basic instructions

## How to Use:

### Method 1: Using Shell/Batch Scripts
1. Ensure LocalWP application is running
2. Start your BCxMCE site in LocalWP
3. Run the appropriate script:
   - **macOS/Linux**: `./start_project.sh`
   - **Windows**: Double-click `start_project.bat`

### Method 2: Using Node.js Script
1. Ensure LocalWP application is running
2. Start your BCxMCE site in LocalWP
3. In terminal, navigate to the project directory and run:
   ```bash
   npm start
   ```
   or
   ```bash
   npm run open
   ```

## What Happens:
- The scripts wait approximately 5 seconds for the server to start
- Automatically opens your default browser to: http://bcxmce.local:10009
- Shows confirmation that the browser was opened successfully

## Requirements:
- LocalWP application must be installed and running
- The BCxMCE site must be started in LocalWP before running these scripts
- Node.js must be installed if using the npm scripts

## Project URL:
Your project is accessible at: http://bcxmce.local/

## Troubleshooting:
- If the browser doesn't open, ensure that the LocalWP site is running first
- Check that port 10009 is available and not blocked
- Make sure the start_project.sh file has execute permissions on macOS/Linux (chmod +x start_project.sh)

Now your project will automatically open in the browser when you start it!