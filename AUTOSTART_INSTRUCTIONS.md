# BCxMCE Project Auto-start Instructions

This project includes scripts to automatically open the website in your browser when starting.

## Usage

### For macOS/Linux:
1. Make sure LocalWP application is installed and running
2. Start your BCxMCE site in LocalWP
3. Run the start script:
   ```bash
   ./start_project.sh
   ```

### For Windows:
1. Make sure LocalWP application is installed and running
2. Start your BCxMCE site in LocalWP
3. Double-click the start script:
   ```
   start_project.bat
   ```

## What the script does:
- Waits for the LocalWP services to start
- Opens the project URL (http://bcxmce.local/) in your default browser

## Note:
- Ensure that the LocalWP site is running before executing the script
- The site must be started in LocalWP first before running these scripts