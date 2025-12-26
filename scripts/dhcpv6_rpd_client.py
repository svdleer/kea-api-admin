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
        
        # Option 15: User Class - manually build with proper format
        # Format: outer option has list of user-class-data items
        # Each item: [2-byte length][data]
        # So for "RPD": [0x00, 0x03, 'R', 'P', 'D'] = total 5 bytes
        from scapy.fields import FieldLenField, StrLenField
        user_class_value = struct.pack('!H', 3) + b'RPD'
        dhcp6 /= DHCP6OptUserClass(userclassdata=user_class_value)
        
        # Option 17: Vendor-Specific Information, Suboption 2 with "RPD"
        # Kea checks: substring(option[17].option[2].hex,0,3) == 'RPD'
        # CableLabs enterprise number: 4491
        vendor_data = struct.pack('!HH', 2, 3) + b'RPD'  # Suboption 2, length 3, "RPD"
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
            # Build the complete SOLICIT packet first
            solicit_packet = UDP(sport=DHCPV6_CLIENT_PORT, dport=DHCPV6_SERVER_PORT) / dhcp6
            
            # Serialize it to bytes to ensure all options are properly encoded
            solicit_bytes = bytes(solicit_packet[DHCP6_Solicit])
            
            # Get the local IPv6 address for the interface using ip command
            import subprocess
            local_ipv6 = None
            try:
                result = subprocess.run(['ip', '-6', 'addr', 'show', self.interface], 
                                      capture_output=True, text=True, timeout=2)
                for line in result.stdout.split('\n'):
                    if 'inet6' in line and 'scope global' in line:
                        # Extract the IPv6 address
                        parts = line.strip().split()
                        if len(parts) >= 2:
                            local_ipv6 = parts[1].split('/')[0]
                            break
            except:
                pass
            
            # Fallback to link-local if no global address found
            if not local_ipv6:
                local_ipv6 = "fe80::250:56ff:fe89:56da"
                print(f"[WARNING] Could not find global IPv6 address on {self.interface}, using link-local")
            else:
                print(f"[INFO] Using local IPv6 address: {local_ipv6}")
            
            # Create RelayForward message
            relay = DHCP6_RelayForward(
                msgtype=12,  # RELAY-FORW
                hopcount=0,
                linkaddr=relay_address,  # Use the relay's actual address for subnet selection
                peeraddr=local_ipv6  # Use actual IPv6, not link-local
            )
            # Add Interface-ID option (option 18)
            relay /= DHCP6OptIfaceId(ifaceid=self.interface.encode())
            # Add the serialized client's message as option 9 (Relay Message)
            relay /= DHCP6OptRelayMsg(message=solicit_bytes)
            
            # Build final packet with relay - use actual source address
            ipv6 = IPv6(dst=relay_address, src=local_ipv6)
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
        
        # Try to find DHCPv6 layer - check for relay-reply wrapper first
        dhcp6 = None
        msg_type = "UNKNOWN"
        
        if DHCP6_RelayReply in packet:
            msg_type = "RELAY-REPLY"
            relay_reply = packet[DHCP6_RelayReply]
            print(f"\n[MESSAGE TYPE]: {msg_type}")
            print(f"[HOP COUNT]: {relay_reply.hopcount}")
            print(f"[LINK ADDRESS]: {relay_reply.linkaddr}")
            print(f"[PEER ADDRESS]: {relay_reply.peeraddr}")
            
            # Extract the encapsulated message from option 9
            if DHCP6OptRelayMsg in relay_reply:
                # Navigate through the layers to find the ADVERTISE
                # The structure is: RelayReply -> OptRelayMsg -> ADVERTISE
                current = relay_reply[DHCP6OptRelayMsg]
                
                # Look for DHCP6_Advertise in the layers
                while current:
                    if DHCP6_Advertise in current:
                        dhcp6 = current[DHCP6_Advertise]
                        msg_type = "ADVERTISE (in RELAY-REPLY)"
                        break
                    # Try to get the next payload
                    if hasattr(current, 'payload') and current.payload:
                        current = current.payload
                    else:
                        break
        elif DHCP6_Advertise in packet:
            msg_type = "ADVERTISE"
            dhcp6 = packet[DHCP6_Advertise]
        elif DHCP6_Reply in packet:
            msg_type = "REPLY"
            dhcp6 = packet[DHCP6_Reply]
        
        if not dhcp6:
            print(f"\n[WARNING] Could not extract DHCPv6 message")
            print("\n[FULL PACKET HEX DUMP]:")
            hexdump(packet)
            print("="*80)
            return
        
        print(f"\n[MESSAGE TYPE]: {msg_type}")
        print(f"[TRANSACTION ID]: 0x{dhcp6.trid:06x}")
        
        # Parse options - iterate through layers instead of .options
        print("\n[OPTIONS]:")
        current_layer = dhcp6.payload if hasattr(dhcp6, 'payload') else None
        while current_layer:
            print(f"\n  Layer: {current_layer.__class__.__name__}")
            
            if isinstance(current_layer, DHCP6OptServerId):
                print(f"    Server DUID: {current_layer.duid}")
                
            elif isinstance(current_layer, DHCP6OptClientId):
                print(f"    Client DUID: {current_layer.duid}")
                
            elif isinstance(current_layer, DHCP6OptIA_NA):
                print(f"    IAID: 0x{current_layer.iaid:08x}")
                print(f"    T1: {current_layer.T1}s")
                print(f"    T2: {current_layer.T2}s")
                
                # Look for IA Address
                ia_sub = current_layer.payload if hasattr(current_layer, 'payload') else None
                while ia_sub:
                    if isinstance(ia_sub, DHCP6OptIAAddress):
                        print(f"\n    ✓ ASSIGNED IPv6 ADDRESS: {ia_sub.addr}")
                        print(f"      Preferred lifetime: {ia_sub.preflft}s")
                        print(f"      Valid lifetime: {ia_sub.validlft}s")
                    ia_sub = ia_sub.payload if hasattr(ia_sub, 'payload') else None
            
            elif isinstance(current_layer, DHCP6OptVendorSpecificInfo):
                print(f"    Enterprise Number: {current_layer.enterprisenum}")
                if current_layer.enterprisenum == CABLELABS_VENDOR_ID:
                    print(f"    Vendor: CableLabs (4491)")
                
                vso_data = current_layer.vso
                if isinstance(vso_data, list):
                    # Parse list of vendor-specific option objects
                    print(f"    Vendor-Specific Options ({len(vso_data)} options):")
                    
                    opt_names = {
                        34: "CCAP-Cores",
                        37: "CCAP-Core-Subnet", 
                        38: "CCAP-Core-Mask",
                        61: "CCAP-Core-Address"
                    }
                    
                    for opt in vso_data:
                        opt_code = opt.optcode
                        opt_data = opt.optdata
                        opt_name = opt_names.get(opt_code, f"OPTION_{opt_code}")
                        
                        print(f"\n      {opt_name} ({len(opt_data)} bytes):")
                        
                        # Parse based on length
                        if len(opt_data) == 16:
                            # Single IPv6 address
                            try:
                                ipv6_addr = socket.inet_ntop(socket.AF_INET6, opt_data)
                                print(f"        {ipv6_addr}")
                            except:
                                print(f"        {opt_data.hex()}")
                        elif len(opt_data) == 32:
                            # Two IPv6 addresses
                            try:
                                ipv6_addr1 = socket.inet_ntop(socket.AF_INET6, opt_data[0:16])
                                ipv6_addr2 = socket.inet_ntop(socket.AF_INET6, opt_data[16:32])
                                print(f"        {ipv6_addr1}")
                                print(f"        {ipv6_addr2}")
                            except:
                                print(f"        {opt_data.hex()}")
                        elif len(opt_data) == 4:
                            # Likely a 32-bit number
                            value = struct.unpack('!I', opt_data)[0]
                            print(f"        {value} (0x{opt_data.hex()})")
                        else:
                            print(f"        {opt_data.hex()}")
                
                elif vso_data:
                    print(f"    Vendor-Specific Data ({len(vso_data)} bytes): {vso_data.hex()}")
                    
                    # Try to decode structured options
                    vendor_opts = self.decode_vendor_options(vso_data)
                    if vendor_opts:
                        for opt_name, opt_value in vendor_opts.items():
                            print(f"      {opt_name}: {opt_value}")
                else:
                    print(f"    Vendor-Specific Data: None")
            
            # Move to next layer
            current_layer = current_layer.payload if hasattr(current_layer, 'payload') else None
        
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
        
        # For relay mode, send at L2 and sniff
        if relay_address:
            # Build L2 packet with Ethernet header
            eth = Ether(dst="ff:ff:ff:ff:ff:ff", src=self.client_mac)
            l2_packet = eth / packet
            
            # START SNIFFER FIRST!
            print(f"Starting sniffer on {self.interface}...")
            from scapy.sendrecv import AsyncSniffer
            sniffer = AsyncSniffer(
                iface=self.interface,
                filter="udp and src port 547",
                count=1,
                prn=lambda x: print(f"Captured packet: {x.summary()}")
            )
            sniffer.start()
            
            # Wait a moment for sniffer to be ready
            time.sleep(0.5)
            
            # NOW send the packet
            print("Sniffer ready, sending packet...")
            sendp(l2_packet, iface=self.interface, verbose=0)
            
            # Wait for response with timeout
            print(f"Waiting up to {timeout} seconds for response...")
            time.sleep(timeout)
            
            # Get results - don't call stop() if already stopped
            if sniffer.running:
                packets = sniffer.stop()
            else:
                packets = sniffer.results
            
            if packets:
                print(f"Got {len(packets)} packet(s)")
                response = packets[0]
            else:
                response = None
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
