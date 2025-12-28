# Ubuntu MOTD Installation Instructions

## Quick Installation

On each Ubuntu server (DHCP/RADIUS), run:

```bash
# Download the MOTD script
sudo wget -O /etc/update-motd.d/99-daa-notice \
  https://raw.githubusercontent.com/svdleer/kea-api-admin/main/docs/ubuntu-motd.sh

# Make it executable
sudo chmod +x /etc/update-motd.d/99-daa-notice

# Test it
/etc/update-motd.d/99-daa-notice
```

## Manual Installation

1. Copy the content from `docs/ubuntu-motd.sh`
2. Create file on server:
   ```bash
   sudo nano /etc/update-motd.d/99-daa-notice
   ```
3. Paste the content
4. Make executable:
   ```bash
   sudo chmod +x /etc/update-motd.d/99-daa-notice
   ```
5. Disable default Ubuntu MOTD (optional):
   ```bash
   sudo chmod -x /etc/update-motd.d/10-help-text
   sudo chmod -x /etc/update-motd.d/50-motd-news
   ```

## Testing

Preview the MOTD without logging out:
```bash
run-parts /etc/update-motd.d/
```

Or just:
```bash
/etc/update-motd.d/99-daa-notice
```

## Customization

Edit the file to customize:
- Server-specific URLs
- Contact information
- Log file paths
- Additional warnings

## Deployment to Multiple Servers

Use a script to deploy to all servers:

```bash
#!/bin/bash
SERVERS="dhcp1.gt.local dhcp2.gt.local radius1.gt.local radius2.gt.local"

for server in $SERVERS; do
  echo "Deploying MOTD to $server..."
  scp docs/ubuntu-motd.sh $server:/tmp/99-daa-notice
  ssh $server 'sudo mv /tmp/99-daa-notice /etc/update-motd.d/ && sudo chmod +x /etc/update-motd.d/99-daa-notice'
  echo "✓ $server done"
done
```

## Verification

SSH into each server and verify the MOTD displays correctly with all information visible.
