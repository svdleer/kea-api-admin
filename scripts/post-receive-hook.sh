#!/bin/bash

#
# Post-receive hook for kea-api-admin
# This script should be configured in Plesk Git settings as a post-receive hook
# 
# To set up in Plesk:
# 1. Go to Git > Your Repository > Additional Settings
# 2. Find "Post-receive hook" field
# 3. Copy and paste this script
# 4. Save changes

# Configuration
TARGET="/home/httpd/vhosts/kea.useless.nl/httpdocs"
GIT_DIR="/home/httpd/vhosts/kea.useless.nl/git"

echo "Deploying to production..."

# Checkout the latest code
cd $TARGET || exit
git --git-dir=$GIT_DIR --work-tree=$TARGET checkout -f main

# Regenerate Composer autoloader
echo "Regenerating Composer autoloader..."
cd $TARGET
composer dump-autoload --optimize --no-dev

echo "Deployment complete!"
