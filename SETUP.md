# Blockchain Centre Hub — Attendance NFT Setup Guide

## Prerequisites

- PHP 8.1+, WordPress 6.x, MySQL/MariaDB
- [Aiken](https://aiken-lang.org/installation-instructions) CLI installed
- [Composer](https://getcomposer.org) installed
- A Cardano wallet on **Preprod Testnet** (Eternl or Nami recommended)
- A free [Blockfrost](https://blockfrost.io) account — create a **Preprod** project

---

## Step 1 — Compile the Aiken Smart Contracts

```bash
cd wp-content/plugins/blockchain-centre-attendance/contracts/attendance
aiken build
```

After compilation, `plutus.json` is created. From it you need:
- **Policy ID** — the hash of the `attendance_policy` validator
- **Script hash** of `attendance_metadata` (spend validator)

Derive the validator address on Preprod:
```bash
cardano-cli address build \
  --payment-script-file plutus.json \
  --testnet-magic 1 \
  --out-file validator.addr

cat validator.addr   # → addr_test1...
```

---

## Step 2 — Get Your Admin Public Key Hash

In your Eternl/Nami wallet (Preprod mode), copy your payment address, then:
```bash
cardano-cli address key-hash --payment-verification-key-file payment.vkey
```
Or look it up via `cardano-cli address info --address addr_test1...`.

---

## Step 3 — Fund Your Wallet

Get free test ADA: https://docs.cardano.org/cardano-testnets/tools/faucet

---

## Step 4 — Install WordPress Plugin

```bash
# Install PHP dependencies
cd wp-content/plugins/blockchain-centre-attendance
composer install

# Activate the plugin in WordPress Admin → Plugins
```

---

## Step 5 — Configure Plugin Settings

Go to **BCH Attendance → Settings** in wp-admin and fill in:

| Setting | Value |
|---|---|
| Cardano Network | Preprod Testnet |
| Blockfrost Project ID | `preprod...` from blockfrost.io |
| Policy ID | From `plutus.json` (Step 1) |
| Validator Script Address | `addr_test1...` (Step 1) |
| Admin Public Key Hash | 56-char hex (Step 2) |
| Admin Hot Signing Key | CBOR hex of your signing key (for QR flow) |
| Claim Expiry | 72 hours (default) |

---

## Step 6 — Create the Claim Page

1. In WordPress, create a new **Page** with the slug `/claim-nft/`
2. Add the shortcode to the page body: `[bch_claim_nft]`
3. Publish the page

---

## Step 7 — Create Your First Event

1. Go to **BCH Attendance → Events → Add New Event**
2. Fill in the event title, date, venue, and IPFS banner URI
3. On the event detail page, check in attendees
4. For each attendee, either:
   - Enter their wallet address → click **Issue NFT** (Flow A)
   - Click **Generate QR Link** → share the link/QR (Flow B)

---

## Verifying on Cardano Preprod

- Explorer: https://preprod.cardanoscan.io
- Search by tx hash or policy ID
- Confirm two tokens minted: `000643b0...` (ref NFT) and `001bc280...` (user NFT)
- Click the ref NFT → view its datum to see the CIP-68 metadata

---

## CIP-68 Token Structure Recap

```
Policy ID  ← same for both tokens
├── 000643b0{suffix}  (Reference NFT, label 100)
│     locked at validator address
│     datum: { metadata_map, version=1, admin_pkh }
│
└── 001bc280{suffix}  (User NFT, label 222)
      sent to attendee wallet
```

To update metadata later: admin connects wallet in the Issue NFT page,
which builds a tx spending the ref NFT UTXO and producing it back with
the updated datum. The Aiken validator allows this when `tx.extra_signatories`
contains the admin PKH stored in `datum.extra`.
