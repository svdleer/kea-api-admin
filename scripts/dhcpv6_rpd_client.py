#!/usr/bin/env python3
"""
DHCPv6 RPD Client Simulator
Simulates a Remote PHY Device (RPD) requesting DHCPv6 configuration from Kea server
"""

from scapy.all import *
from scapy.layers.dhcp6 import *
from scapy.layers.inet6 import IPv6, UDP
from scapy.layers.l2 import Ether
import time
import sys
import struct

# DHCPv6 Constants
DHCPV6_SERVER_PORT = 547
DHCPV6_CLIENT_PORT = 546
DHCPV6_MULTICAST = "ff02::1:2"

# Vendor-specific constants for CableLabs (vendor-id 4491)
CABLELABS_VENDOR_ID = 4491

class DHCPv6RPDClient:
    def __init__(self, interface="eth0", client_mac=None):
        self.interface = interface
        self.client_mac = client_mac or get_if_hwaddr(interface)
        self.transaction_id = random.randint(0, 0xFFFFFF)
        self.duid = self.generate_duid()
        
    def generate_duid(self):
        """Generate DUID-LLT (Link-layer address plus time)"""
        # Type 1 = DUID-LLT
        # Hardware type 1 = Ethernet
        # Time: seconds since January 1, 2000
        epoch_2000 = int(time.time() - 946684800)
        mac_bytes = bytes.fromhex(self.client_mac.replace(':', ''))
        duid = struct.pack('!HHI', 1, 1, epoch_2000) + mac_bytes
        return duid
    
    def create_solicit(self, relay_address=None):
        """Create DHCPv6 Solicit message with RPD client class"""
        
        # Build DHCPv6 Solicit packet with options using layer chaining
        dhcp6 = DHCP6_Solicit(trid=self.transaction_id)
        
        # Option 1: Client Identifier (DUID) - create DUID_LLT
        duid_llt = DUID_LLT(
            hwtype=1,  # Ethernet
            timeval=int(time.time() - 946684800),  # Seconds since Jan 1, 2000
            lladdr=self.client_mac
        )
        dhcp6 /= DHCP6OptClientId(duid=duid_llt)
        
        # Option 8: Elapsed Time
        dhcp6 /= DHCP6OptElapsedTime(elapsedtime=0)
        
        # Option 3: Identity Association for Non-temporary Address (IA_NA)
        dhcp6 /= DHCP6OptIA_NA(
            iaid=0x12345678,
            T1=1000,
            T2=2000
        )
        
        # Option 17: Vendor-Specific Information, Suboption 15 with "RPD"
        # CableLabs enterprise number: 4491
        # Suboption format: [option-code (2 bytes)][length (2 bytes)][data]
        vendor_data = struct.pack('!HH', 15, 3) + b'RPD'
        dhcp6 /= DHCP6OptVendorSpecificInfo(
            enterprisenum=4491,
            vso=vendor_data
        )
        
        # Option 6: Option Request (ORO) - Request specific options
        # 23 = DNS Recursive Name Server
        # 24 = Domain Search List
        # 17 = Vendor-specific Information
        dhcp6 /= DHCP6OptOptReq(reqopts=[23, 24, 17])
        
        # If relay address specified, wrap in RelayForward
        if relay_address:
            # Create RelayForward message
            relay = DHCP6_RelayForward(
                msgtype=12,  # RELAY-FORW
                hopcount=0,
                linkaddr="fe80::1",  # Link address (relay's link-local)
                peeraddr="fe80::250:56ff:fe89:56da"  # Client's link-local
            )
            # Add Interface-ID option (option 18) - identifies the interface
            relay /= DHCP6OptIfaceId(ifaceid=b'ens224')
            # Add the client's message as option 9 (Relay Message)
            relay /= DHCP6OptRelayMsg(message=bytes(dhcp6))
            
            # Build packet with relay
            ipv6 = IPv6(dst=relay_address, src="fe80::250:56ff:fe89:56da")
            packet = ipv6 / UDP(sport=DHCPV6_CLIENT_PORT, dport=DHCPV6_SERVER_PORT) / relay
        else:
            # Direct multicast
            ipv6 = IPv6(dst=DHCPV6_MULTICAST)
            packet = ipv6 / UDP(sport=DHCPV6_CLIENT_PORT, dport=DHCPV6_SERVER_PORT) / dhcp6
        
        return packet
    
    def decode_vendor_options(self, vendor_data):
        """Decode CableLabs vendor-specific options"""
        vendor_opts = {}
        offset = 0
        
        while offset < len(vendor_data):
            if offset + 4 > len(vendor_data):
                break
                
            opt_code = struct.unpack('!H', vendor_data[offset:offset+2])[0]
            opt_len = struct.unpack('!H', vendor_data[offset+2:offset+4])[0]
            opt_data = vendor_data[offset+4:offset+4+opt_len]
            
            # CableLabs option codes
            opt_names = {
                34: "CCAP-Cores (SUB-OPT 34)",
                37: "CCAP-Core-Subnet (SUB-OPT 37)", 
                38: "CCAP-Core-Mask (SUB-OPT 38)",
                61: "CCAP-Core-Address (SUB-OPT 61)"
            }
            
            opt_name = opt_names.get(opt_code, f"Unknown option {opt_code}")
            
            # Try to decode as IPv6 address
            if opt_len == 16:
                try:
                    ipv6_addr = socket.inet_ntop(socket.AF_INET6, opt_data)
                    vendor_opts[opt_name] = ipv6_addr
                except:
                    vendor_opts[opt_name] = opt_data.hex()
            else:
                vendor_opts[opt_name] = opt_data.hex()
            
            offset += 4 + opt_len
        
        return vendor_opts
    
    def parse_response(self, packet):
        """Parse and display DHCPv6 response in detail"""
        print("\n" + "="*80)
        print("DHCPv6 RESPONSE RECEIVED")
        print("="*80)
        
        # Show full packet first
        print("\n[RAW PACKET STRUCTURE]:")
        packet.show()
        
        # Try to find DHCPv6 layer
        dhcp6 = None
        msg_type = "UNKNOWN"
        
        if DHCP6_Advertise in packet:
            msg_type = "ADVERTISE"
            dhcp6 = packet[DHCP6_Advertise]
        elif DHCP6_Reply in packet:
            msg_type = "REPLY"
            dhcp6 = packet[DHCP6_Reply]
        elif DHCP6_RelayReply in packet:
            msg_type = "RELAY-REPLY"
            dhcp6 = packet[DHCP6_RelayReply]
        elif DHCP6_Confirm in packet:
            msg_type = "CONFIRM"
            dhcp6 = packet[DHCP6_Confirm]
        else:
            print(f"\n[WARNING] Unknown DHCPv6 message type")
            print("\n[FULL PACKET HEX DUMP]:")
            hexdump(packet)
            print("="*80)
            return
        
        print(f"\n[MESSAGE TYPE]: {msg_type}")
        print(f"[TRANSACTION ID]: 0x{dhcp6.trid:06x}")
        
        # Parse options
        print("\n[OPTIONS]:")
        for opt in dhcp6.options:
            print(f"\n  Option: {opt.__class__.__name__}")
            
            if isinstance(opt, DHCP6OptServerId):
                print(f"    Server DUID: {opt.duid.hex()}")
                
            elif isinstance(opt, DHCP6OptClientId):
                print(f"    Client DUID: {opt.duid.hex()}")
                
            elif isinstance(opt, DHCP6OptIA_NA):
                print(f"    IAID: 0x{opt.iaid:08x}")
                print(f"    T1: {opt.T1}s")
                print(f"    T2: {opt.T2}s")
                
                # Parse IA_NA options
                for ia_opt in opt.ianaopts:
                    if isinstance(ia_opt, DHCP6OptIAAddress):
                        print(f"    Assigned Address: {ia_opt.addr}")
                        print(f"    Preferred Lifetime: {ia_opt.preflft}s")
                        print(f"    Valid Lifetime: {ia_opt.validlft}s")
                        
            elif isinstance(opt, DHCP6OptVendorSpecificInfo):
                print(f"    Vendor Enterprise Number: {opt.enterprisenum}")
                
                if opt.enterprisenum == CABLELABS_VENDOR_ID:
                    print(f"    Vendor: CableLabs (4491)")
                    vendor_opts = self.decode_vendor_options(opt.vso)
                    
                    for opt_name, opt_value in vendor_opts.items():
                        print(f"      {opt_name}: {opt_value}")
                else:
                    print(f"    Vendor Data: {opt.vso.hex()}")
                    
            elif isinstance(opt, DHCP6OptDNSServers):
                print(f"    DNS Servers: {', '.join(opt.dnsservers)}")
                
            elif isinstance(opt, DHCP6OptDNSDomains):
                print(f"    DNS Domains: {', '.join(opt.dnsdomains)}")
                
            else:
                print(f"    Raw data: {bytes(opt).hex()}")
        
        print("\n" + "="*80)
        
        # Show full packet hex dump
        print("\n[FULL PACKET HEX DUMP]:")
        hexdump(packet)
        print("="*80)
    
    def send_solicit(self, relay_address=None, timeout=5):
        """Send DHCPv6 Solicit and wait for response"""
        
        packet = self.create_solicit(relay_address)
        
        print("="*80)
        print("SENDING DHCPv6 SOLICIT")
        print("="*80)
        print(f"Interface: {self.interface}")
        print(f"Client MAC: {self.client_mac}")
        print(f"Transaction ID: 0x{self.transaction_id:06x}")
        print(f"Destination: {relay_address or DHCPV6_MULTICAST}")
        print(f"Mode: {'Relay Forward' if relay_address else 'Direct Multicast'}")
        print(f"Client Class: RPD")
        
        print("\nSending packet and waiting for response...")
        
        # For relay mode, use L3 unicast
        if relay_address:
            response = sr1(
                packet,
                timeout=timeout,
                verbose=0
            )
        else:
            # For multicast, we need to use L2 (Ethernet) layer
            # Build L2 packet with Ethernet header for multicast
            # Multicast MAC for ff02::1:2 is 33:33:00:01:00:02
            eth = Ether(dst="33:33:00:01:00:02", src=self.client_mac)
            l2_packet = eth / packet
            
            # Send at L2 and sniff for response
            sendp(l2_packet, iface=self.interface, verbose=0)
            
            # Sniff for DHCPv6 response
            response = sniff(
                iface=self.interface,
                filter="udp port 546",
                timeout=timeout,
                count=1
            )
            
            if response:
                response = response[0]
            else:
                response = None
        
        if response:
            self.parse_response(response)
            return response
        else:
            print("\n[TIMEOUT] No response received from server")
            return None


def main():
    import argparse
    
    parser = argparse.ArgumentParser(
        description='DHCPv6 RPD Client Simulator - Simulate RPD device requesting DHCPv6 configuration'
    )
    parser.add_argument('-i', '--interface', default='eth0',
                      help='Network interface to use (default: eth0)')
    parser.add_argument('-r', '--relay', 
                      help='Relay agent IPv6 address (optional, uses multicast if not specified)')
    parser.add_argument('-m', '--mac',
                      help='Client MAC address (optional, uses interface MAC if not specified)')
    parser.add_argument('-t', '--timeout', type=int, default=5,
                      help='Response timeout in seconds (default: 5)')
    
    args = parser.parse_args()
    
    print("\n" + "="*80)
    print("DHCPv6 RPD CLIENT SIMULATOR")
    print("="*80 + "\n")
    
    try:
        client = DHCPv6RPDClient(
            interface=args.interface,
            client_mac=args.mac
        )
        
        response = client.send_solicit(
            relay_address=args.relay,
            timeout=args.timeout
        )
        
        if response:
            print("\n✓ Successfully received DHCPv6 response")
            sys.exit(0)
        else:
            print("\n✗ Failed to receive DHCPv6 response")
            sys.exit(1)
            
    except PermissionError:
        print("\n✗ ERROR: This script requires root/administrator privileges")
        print("   Run with: sudo python3 dhcpv6_rpd_client.py")
        sys.exit(1)
    except Exception as e:
        print(f"\n✗ ERROR: {e}")
        import traceback
        traceback.print_exc()
        sys.exit(1)


if __name__ == "__main__":
    main()
