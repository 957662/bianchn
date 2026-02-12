#!/bin/bash
echo "Starting services..."
sudo systemctl start nginx
sudo systemctl start php7.4-fpm
sudo systemctl start mysql
sudo systemctl start redis-server
echo "All services started"
echo "Visit http://localhost to access your blog"
