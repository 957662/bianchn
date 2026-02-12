#!/bin/bash
echo "=== Service Status ==="
systemctl status nginx --no-pager | grep Active
systemctl status php7.4-fpm --no-pager | grep Active
systemctl status mysql --no-pager | grep Active
systemctl status redis-server --no-pager | grep Active
echo ""
echo "=== Listening Ports ==="
ss -tuln | grep -E ':(80|3306|6379)' || netstat -tuln | grep -E ':(80|3306|6379)'
