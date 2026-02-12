#!/bin/bash
echo "Restarting services..."
sudo systemctl restart nginx
sudo systemctl restart php7.4-fpm
sudo systemctl restart mysql
sudo systemctl restart redis-server
echo "All services restarted"
