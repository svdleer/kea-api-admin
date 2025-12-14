#!/bin/bash
# Setup NAT forwarding for Docker to reach Kea servers on different subnets
# This allows the Docker container to access Kea servers at 172.16.7.222 and 172.17.7.222

echo "Setting up NAT rules for Docker to access Kea servers..."

# Enable IP forwarding
echo 1 > /proc/sys/net/ipv4/ip_forward

# Get Docker bridge network
DOCKER_BRIDGE=$(docker network inspect kea-api-admin_kea-network -f '{{range .IPAM.Config}}{{.Subnet}}{{end}}')
DOCKER_GW=$(docker network inspect kea-api-admin_kea-network -f '{{range .IPAM.Config}}{{.Gateway}}{{end}}')

echo "Docker network: $DOCKER_BRIDGE"
echo "Docker gateway: $DOCKER_GW"

# Add FORWARD rules to allow traffic from Docker to Kea servers
iptables -A FORWARD -s $DOCKER_BRIDGE -d 172.16.7.222 -j ACCEPT
iptables -A FORWARD -s $DOCKER_BRIDGE -d 172.17.7.222 -j ACCEPT
iptables -A FORWARD -d $DOCKER_BRIDGE -s 172.16.7.222 -j ACCEPT
iptables -A FORWARD -d $DOCKER_BRIDGE -s 172.17.7.222 -j ACCEPT

# Add POSTROUTING rules for NAT
iptables -t nat -A POSTROUTING -s $DOCKER_BRIDGE -d 172.16.7.0/24 -j MASQUERADE
iptables -t nat -A POSTROUTING -s $DOCKER_BRIDGE -d 172.17.7.0/24 -j MASQUERADE

echo "NAT rules configured successfully!"
echo ""
echo "To make these persistent across reboots, install iptables-persistent:"
echo "  sudo apt-get install iptables-persistent"
echo "  sudo netfilter-persistent save"
