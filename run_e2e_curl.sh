#!/bin/bash
# Run server
php artisan serve --port=8080 > /dev/null 2>&1 &
SERVER_PID=$!
sleep 2

curl -s -i http://localhost:8080/dashboard

kill $SERVER_PID
