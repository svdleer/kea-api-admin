# DHCPv6 RPD Client Simulator

## Description

This script simulates a Remote PHY Device (RPD) client for testing DHCPv6 configuration with Kea DHCP server. It sends DHCPv6 Solicit messages with the RPD client class and displays detailed packet information from the server's response.

## Requirements

```bash
pip install scapy
```

## Usage

### Basic Usage (Multicast)
```bash
sudo python3 dhcpv6_rpd_client.py -i eth0
```

### With Relay Agent
```bash
sudo python3 dhcpv6_rpd_client.py -i eth0 -r 2001:db8::1
```

### Custom MAC Address
```bash
sudo python3 dhcpv6_rpd_client.py -i eth0 -m 00:11:22:33:44:55
```

### All Options
```bash
sudo python3 dhcpv6_rpd_client.py \
  --interface eth0 \
  --relay 2001:db8::1 \
  --mac 00:11:22:33:44:55 \
  --timeout 10
```

## Options

- `-i, --interface` - Network interface to use (default: eth0)
- `-r, --relay` - Relay agent IPv6 address (optional, uses multicast ff02::1:2 if not specified)
- `-m, --mac` - Client MAC address (optional, uses interface MAC if not specified)
- `-t, --timeout` - Response timeout in seconds (default: 5)

## What It Does

1. **Generates DUID** - Creates a unique DUID-LLT identifier for the client
2. **Sends Solicit** - Sends DHCPv6 Solicit message with:
   - Client Class: "RPD" (matches Kea client-class configuration)
   - Option Request for DNS, Vendor options
   - IA_NA (Identity Association for Non-temporary Address)
3. **Receives Response** - Captures DHCPv6 Advertise or Reply
4. **Decodes Packets** - Displays detailed information including:
   - Assigned IPv6 address
   - Lease timers (T1, T2, preferred lifetime, valid lifetime)
   - DNS servers
   - **CableLabs vendor-specific options** (enterprise 4491):
     - SUB-OPT 34: CCAP-Cores
     - SUB-OPT 37: CCAP-Core-Subnet
     - SUB-OPT 38: CCAP-Core-Mask
     - SUB-OPT 61: CCAP-Core-Address
   - Full hex dump of packets

## Example Output

```
================================================================================
SENDING DHCPv6 SOLICIT
================================================================================
Interface: eth0
Client MAC: 00:0c:29:12:34:56
Client DUID: 00010001abcd1234000c29123456
Transaction ID: 0x123abc
Destination: ff02::1:2
Client Class: RPD

[SOLICIT PACKET]:
###[ IPv6 ]###
  ...

================================================================================
DHCPv6 RESPONSE RECEIVED
================================================================================

[MESSAGE TYPE]: ADVERTISE
[TRANSACTION ID]: 0x123abc

[OPTIONS]:

  Option: DHCP6OptServerId
    Server DUID: 00010001abcd9876001122334455

  Option: DHCP6OptIA_NA
    IAID: 0x12345678
    T1: 1000s
    T2: 2000s
    Assigned Address: 2001:db8:1::2
    Preferred Lifetime: 3600s
    Valid Lifetime: 7200s

  Option: DHCP6OptVendorSpecificInfo
    Vendor Enterprise Number: 4491
    Vendor: CableLabs (4491)
      CCAP-Core-Address (SUB-OPT 61): 2001:db8:2::1
      CCAP-Cores (SUB-OPT 34): ...

[FULL PACKET HEX DUMP]:
...
```

## Testing Your Kea Configuration

This script is perfect for testing:
- DHCPv6 subnet pools
- Relay agent forwarding
- CCAP core addresses (vendor option 61)
- Client class filtering (RPD class)
- Lease timers
- DNS options

## Troubleshooting

**No response received:**
- Check firewall allows UDP ports 546/547
- Verify Kea server is running
- Check relay agent is configured correctly
- Ensure subnet exists for the client class "RPD"

**Permission denied:**
- Must run with sudo/root privileges for raw sockets

**Interface not found:**
- Use `ip link show` or `ifconfig` to list available interfaces
- Specify correct interface with `-i` option
