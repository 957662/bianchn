#!/bin/bash
echo "Stopping services..."
sudo systemctl stop nginx
sudo systemctl stop php7.4-fpm
echo "Services stopped"
