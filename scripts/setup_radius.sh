#!/bin/bash
#
# RADIUS/802.1X Integration Setup Script
# Run this script to set up the RADIUS client database and sync existing BVI interfaces
#

echo "========================================="
echo "RADIUS/802.1X Integration Setup"
echo "========================================="
echo ""

# Database credentials
read -p "Enter MySQL username [dhcp_admin]: " DB_USER
DB_USER=${DB_USER:-dhcp_admin}

read -sp "Enter MySQL password: " DB_PASS
echo ""

read -p "Enter database name [dhcpdb]: " DB_NAME
DB_NAME=${DB_NAME:-dhcpdb}

echo ""
echo "Step 1: Creating RADIUS clients table (nas)..."
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < database/migrations/create_radius_clients_table.sql

if [ $? -eq 0 ]; then
    echo "✓ Table created successfully"
else
    echo "✗ Failed to create table. Please check your credentials."
    exit 1
fi

echo ""
echo "Step 2: Checking for existing BVI interfaces..."
BVI_COUNT=$(mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -sN -e "SELECT COUNT(*) FROM cin_switch_bvi_interfaces;")
echo "Found $BVI_COUNT BVI interface(s)"

if [ "$BVI_COUNT" -gt 0 ]; then
    echo ""
    read -p "Do you want to sync these BVI interfaces to RADIUS clients? (y/n): " SYNC_CHOICE
    
    if [ "$SYNC_CHOICE" = "y" ] || [ "$SYNC_CHOICE" = "Y" ]; then
        echo ""
        echo "You can sync via the web UI at: https://kea.useless.nl/radius"
        echo "Or via API:"
        echo ""
        read -p "Enter your API key (or press Enter to skip): " API_KEY
        
        if [ -n "$API_KEY" ]; then
            echo "Syncing BVI interfaces..."
            RESPONSE=$(curl -s -X POST https://kea.useless.nl/api/radius/sync \
                -H "X-API-Key: $API_KEY")
            echo "$RESPONSE"
        fi
    fi
fi

echo ""
echo "========================================="
echo "Setup Complete!"
echo "========================================="
echo ""
echo "Next Steps:"
echo "1. Visit https://kea.useless.nl/radius to manage RADIUS clients"
echo "2. Configure FreeRADIUS (see docs/RADIUS_INTEGRATION.md)"
echo "3. Update your network switches with the RADIUS secrets"
echo ""
echo "For 802.1X configuration:"
echo "- Each BVI interface IPv6 address is now a RADIUS NAS client"
echo "- Secrets are randomly generated (view/edit in web UI)"
echo "- Clients auto-sync when BVI interfaces are added/modified/deleted"
echo ""
