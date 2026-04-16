#!/usr/bin/env python3
"""
Derive the payment key hash (PKH) from a Cardano bech32 address.
Works for addr1... (mainnet) and addr_test1... (preprod/testnet).

Usage:
    python3 tools/get-pkh.py
    # paste your wallet address when prompted
"""

CHARSET = "qpzry9x8gf2tvdw0s3jn54khce6mua7l"

def bech32_decode(bech: str) -> bytes:
    bech = bech.lower().strip()
    pos  = bech.rfind('1')
    data = [CHARSET.find(x) for x in bech[pos + 1:]]

    # convert 5-bit groups → 8-bit bytes (drop 6-byte checksum)
    acc, bits, result = 0, 0, []
    for val in data[:-6]:
        acc   = ((acc << 5) | val) & 0xffffffff
        bits += 5
        if bits >= 8:
            bits -= 8
            result.append((acc >> bits) & 0xff)
    return bytes(result)

def get_pkh(address: str) -> str:
    payload = bech32_decode(address)
    header  = payload[0]

    # Header bits 5-4 determine credential type:
    #   00 = key hash payment  (most wallet addresses)
    #   01 = script hash payment
    cred_type = (header >> 4) & 0x3
    if cred_type not in (0, 1):
        raise ValueError(f"Unsupported address type (header=0x{header:02x})")

    # Bytes 1–28 = 28-byte payment credential hash
    pkh = payload[1:29].hex()
    label = "Payment Key Hash" if cred_type == 0 else "Script Hash"
    return pkh, label

if __name__ == "__main__":
    addr = input("Paste your Preprod wallet address (addr_test1...): ").strip()
    try:
        pkh, label = get_pkh(addr)
        print(f"\n{label} (56 hex chars):")
        print(f"  {pkh}")
        print(f"\nPaste this into NMKR → CIP68 Metadata Extra field")
        print(f"Also save it in the plugin Settings → Admin PKH field (for future use)")
    except Exception as e:
        print(f"Error: {e}")
