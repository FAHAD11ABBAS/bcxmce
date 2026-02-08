# BCxMCE WordPress Project - Auto Browser Opening Setup

This project has been configured with automatic browser opening functionality when starting the development server.

## Overview

The system has been enhanced with multiple methods to automatically open your WordPress site in the browser after starting the LocalWP services.

## Files Added

1. **start_project.sh** - Shell script for macOS/Linux that waits for the server to be ready and then opens the browser
2. **start_project.bat** - Batch script for Windows with port availability checking
3. **wait-for-it.sh** - Utility script to wait for a TCP port to become available
4. **open-browser.js** - Node.js script for cross-platform browser opening
5. **package.json** - Configuration for npm scripts
6. **node_modules/** - Dependencies including the 'open' package
7. **AUTOSTART_INSTRUCTIONS.md** - Basic instructions
8. **AUTO_BROWSER_OPEN.md** - Comprehensive instructions

## How It Works

1. The scripts first check if the LocalWP services are running and available on port 10009
2. Once the service is detected as available, the default browser automatically opens to:
   - http://bcxmce.local:10009
3. Success message is displayed confirming the browser opened

## Usage Methods

### Method 1: Shell/Batch Scripts
- **macOS/Linux**: Run `./start_project.sh` (requires LocalWP running)
- **Windows**: Run `start_project.bat` (requires LocalWP running)

### Method 2: NPM Scripts
- Navigate to the project directory
- Run `npm start` or `npm run open`

## Features

- ✅ Cross-platform compatibility (macOS, Windows, Linux)
- ✅ Port readiness checking (waits for service availability)
- ✅ Timeout handling (fails gracefully after 30 seconds)
- ✅ Error handling and informative messages
- ✅ Uses native OS commands for browser opening

## Requirements

- LocalWP application must be installed and running
- BCxMCE site must be started in LocalWP
- Node.js (for npm script method)

## Site Access

Your WordPress site is accessible at: **http://bcxmce.local/**

## Notes

- The system waits for the service to be fully available before attempting to open the browser
- This ensures the site is ready to load when the browser opens
- All scripts include proper error handling and user feedback

Now your project will automatically open in your browser when you start it, providing a seamless development experience!