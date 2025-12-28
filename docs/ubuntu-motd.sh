#!/bin/bash
# MOTD for DHCPv6/RADIUS servers
# Place this file in /etc/update-motd.d/99-daa-notice
# Make executable: chmod +x /etc/update-motd.d/99-daa-notice

cat << 'EOF'

╔══════════════════════════════════════════════════════════════════════════════╗
║                                                                              ║
║                   ⚠️  DHCP / RADIUS ADMINISTRATION NOTICE                    ║
║                                                                              ║
╚══════════════════════════════════════════════════════════════════════════════╝

  📋 ADMINISTRATION HAS MOVED:
  
     All DHCP and RADIUS configuration is now managed through:
     
     🌐  DAA Infrastructure Management
         http://daa.gt.local
     
  ⚠️  DO NOT MANUALLY EDIT CONFIGURATION FILES
  
     • Kea DHCPv6 configuration is managed via web interface
     • RADIUS NAS clients are automatically synced
     • Manual changes will be overwritten by automated sync
  
  📁 LOG FILES FOR DEBUGGING:
  
     Kea DHCPv6 Server:
       /var/log/kea/kea-dhcp6.log
       journalctl -u kea-dhcp6-server
     
     FreeRADIUS:
       /var/log/freeradius/radius.log
       journalctl -u freeradius
       
     View live logs:
       tail -f /var/log/kea/kea-dhcp6.log
       tail -f /var/log/freeradius/radius.log
  
  🔧 COMMON TASKS:
  
     • Add/modify DHCP subnets    → http://daa.gt.local/dhcp
     • Search for leases          → http://daa.gt.local/dhcp/search
     • Manage switches & BVIs     → http://daa.gt.local/switches
     • View RADIUS logs           → http://daa.gt.local/radius/logs
     • Admin tools                → http://daa.gt.local/admin/tools
  
  📞 SUPPORT:
  
     For issues or questions, contact the network team
     or check documentation at http://daa.gt.local/docs
  
╔══════════════════════════════════════════════════════════════════════════════╗
║  Questions? Visit: http://daa.gt.local                                       ║
╚══════════════════════════════════════════════════════════════════════════════╝

EOF
