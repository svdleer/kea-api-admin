#!/bin/bash
# Add routing rules so Docker can reach Kea servers on different subnets

echo "Adding routes for Docker to reach Kea server networks..."

# Get the default gateway (where the host routes to 172.16.7.0/24 and 172.17.7.0/24)
# This assumes your host already knows how to route to these networks
DEFAULT_GW_16=$(ip route | grep "172.16.7.0" | awk '{print $3}')
DEFAULT_GW_17=$(ip route | grep "172.17.7.0" | awk '{print $3}')

if [ -z "$DEFAULT_GW_16" ]; then
    echo "WARNING: No route found for 172.16.7.0/24 on host"
    echo "Please check: ip route | grep 172.16"
    DEFAULT_GW_16=$(ip route | grep default | awk '{print $3}')
fi

if [ -z "$DEFAULT_GW_17" ]; then
    echo "WARNING: No route found for 172.17.7.0/24 on host"
    echo "Please check: ip route | grep 172.17"
    DEFAULT_GW_17=$(ip route | grep default | awk '{print $3}')
fi

echo "Gateway for 172.16.7.0/24: $DEFAULT_GW_16"
echo "Gateway for 172.17.7.0/24: $DEFAULT_GW_17"

# Get Docker bridge interface
DOCKER_IFACE=$(docker network inspect kea-api-admin_kea-network -f '{{index .Options "com.docker.network.bridge.name"}}')
if [ -z "$DOCKER_IFACE" ]; then
    DOCKER_IFACE="br-$(docker network inspect kea-api-admin_kea-network -f '{{.Id}}' | cut -c1-12)"
fi

echo "Docker bridge interface: $DOCKER_IFACE"

# Add routes via the Docker bridge (so Docker containers can route out)
# These routes tell the system that traffic TO these networks FROM Docker should go via the default gateway
ip route add 172.16.7.0/24 via $DEFAULT_GW_16 dev $(ip route get $DEFAULT_GW_16 | grep -oP 'dev \K\S+') 2>/dev/null || echo "Route to 172.16.7.0/24 already exists"
ip route add 172.17.7.0/24 via $DEFAULT_GW_17 dev $(ip route get $DEFAULT_GW_17 | grep -oP 'dev \K\S+') 2>/dev/null || echo "Route to 172.17.7.0/24 already exists"

echo "Routes configured!"
echo ""
echo "Current routes:"
ip route | grep -E "(172.16.7|172.17.7)"
echo ""
echo "To make persistent, add to /etc/network/interfaces or use netplan"
