#!/bin/bash
echo "Waiting for MySQL to be ready..."
maxTries=10
while [ "$maxTries" -gt 0 ]; do
    if ping -c 1 mi-mysql > /dev/null 2>&1; then
        break
    fi
    maxTries=$(($maxTries - 1))
    sleep 3
done

if [ "$maxTries" -eq 0 ]; then
    echo "Could not connect to MySQL"
    exit 1
fi

echo "MySQL is ready"
/usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf