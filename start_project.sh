#!/bin/bash

# Script to start the BCxMCE project and open it in the browser automatically

echo "Starting BCxMCE project..."

# Wait for the service to be available on port 10009
echo "Waiting for services to be ready on port 10009..."
./wait-for-it.sh localhost:10009 -t 30

if [ $? -eq 0 ] ; then
    echo "Services are ready! Opening BCxMCE project in browser..."
    open "http://bcxmce.local/"
    echo "Project opened in browser. The URL is: http://bcxmce.local/"
else
    echo "Timeout: Services did not start within 30 seconds."
    exit 1
fi