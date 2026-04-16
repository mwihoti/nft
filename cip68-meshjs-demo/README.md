# CIP-68 NFT Demo with Mesh.js

A standalone Next.js 14 app that demonstrates how to mint **CIP-68 Cardano NFTs** directly using Mesh.js — no NMKR, no backend, no intermediary.

This demo is a companion to the [Blockchain Centre Hub Attendance NFT](../README.md) WordPress plugin. The plugin uses NMKR to handle minting on behalf of attendees. This demo shows what is happening at the Cardano transaction level when you do it yourself with Mesh.js.

---

## What is CIP-68?

CIP-68 is a Cardano NFT metadata standard. It differs from the older CIP-25 standard in one fundamental way:

| | CIP-25 | CIP-68 |
|---|---|---|
| Where metadata lives | Transaction metadata (off-chain after tx confirms) | Plutus datum on a UTxO (on-chain, always queryable) |
| Metadata updatable? | No | Yes — spend the Reference NFT, relock with new datum |
| Number of tokens | 1 per NFT | 2 per NFT (Reference + User) |

### The two tokens

Every CIP-68 NFT is two tokens minted in one transaction:

| Token | Asset Name Prefix | Where it goes | Purpose |
|---|---|---|---|
| Reference NFT | `000643b0` + suffix | Script address (or your wallet in this demo) | Holds the metadata as an **inline datum** |
| User NFT | `001bc280` + suffix | Recipient's wallet | What the owner actually holds |

The `000643b0` and `001bc280` prefixes are defined by CIP-67 (the label registry). On-chain indexers and wallets recognise these prefixes to know which token is which.

### Asset name structure

```
Full asset name = label prefix (4 bytes) + suffix (28 bytes) = 32 bytes

000643b0  +  [eventId padded to 14 bytes][attendeeId padded to 14 bytes]
```

Example for eventId=1, attendeeId=42:
```
000643b0  0000000000000001  000000000000002a
          └── event id ──┘  └─ attendee id ┘
```

### The datum structure

The Reference NFT's UTxO carries an inline datum in this Plutus shape:

```
Constructor 0 (
  Map [
    (bytes "name",          bytes "Attendance – Workshop"),
    (bytes "image",         bytes "ipfs://Qm..."),
    (bytes "event_name",    bytes "Workshop"),
    (bytes "event_date",    bytes "2026-04-15"),
    (bytes "venue",         bytes "Nairobi"),
    (bytes "attendee_name", bytes "Alice"),
    (bytes "attendee_id",   bytes "1"),
    (bytes "mediaType",     bytes "image/png"),
    (bytes "issued_by",     bytes "Blockchain Centre Hub"),
  ],
  Int 1   -- version
)
```

All keys and values are hex-encoded byte strings. `strToHex("name")` → `"6e616d65"`.

In Mesh.js this is written as:

```typescript
{
  alternative: 0,   // = Constructor 0
  fields: [
    {
      map: [
        { k: { bytes: "6e616d65" }, v: { bytes: "..." } },
        // ...
      ]
    },
    { int: 1 }
  ]
}
```

---

## Project structure

```
cip68-meshjs-demo/
├── .env.local.example          # Blockfrost key template
├── .gitignore
├── package.json
├── tsconfig.json
├── next.config.mjs             # transpilePackages for Mesh.js
├── tailwind.config.ts
├── postcss.config.mjs
│
└── src/
    ├── app/
    │   ├── layout.tsx          # MeshProvider wraps the whole app
    │   ├── page.tsx            # State management + page layout
    │   └── globals.css         # Tailwind base
    ├── lib/
    │   ├── constants.ts        # CIP-67 label prefixes, network URLs
    │   └── cip68.ts            # strToHex, buildAssetNames, buildCip68Datum
    └── components/
        ├── WalletPanel.tsx     # CardanoWallet connect button (CIP-30)
        ├── MintForm.tsx        # Metadata input form
        ├── MintButton.tsx      # Full mint orchestration
        └── TxResult.tsx        # Tx hash + Cardanoscan link
```

---

## Prerequisites

- **Node.js 18+**
- **A Cardano wallet browser extension** — Eternl or Nami, switched to **Preprod testnet**
- **Test ADA** — get free tADA from the [Cardano testnet faucet](https://docs.cardano.org/cardano-testnet/tools/faucet/)
- **A Blockfrost account** — create a free Preprod project at [blockfrost.io](https://blockfrost.io) and copy the project ID

---

## Setup

```bash
# 1. Install dependencies
npm install

# 2. Configure environment
cp .env.local.example .env.local
# Open .env.local and paste your Blockfrost Preprod project ID

# 3. Start the dev server
npm run dev
```

Open [http://localhost:3000](http://localhost:3000).

---

## How to use

1. **Connect wallet** — click the wallet button, select your Preprod wallet (Eternl/Nami)
2. **Fill in the form** — event details, attendee name, recipient wallet address
3. **Click Mint NFT** — the wallet popup opens for you to sign
4. **Approve the transaction** — two tokens are minted in one tx
5. **Click the Cardanoscan link** — verify on-chain

### Verifying on Cardanoscan

1. Go to `https://preprod.cardanoscan.io/transaction/<txHash>`
2. Click the **Assets** tab — two tokens minted
3. Click the `000643b0…` token → open the UTxO → click **Datum**
4. You will see **Constructor 0** with the metadata map — that is CIP-68
5. The `001bc280…` token appears in the recipient's wallet

---

## How the mint flow works

The full flow is inside `src/components/MintButton.tsx`. Seven steps:

```
Step 1 — Dynamic import @meshsdk/core
         (avoids Next.js SSR crash — Mesh uses browser-only APIs)

Step 2 — ForgeScript.withOneSignature(walletAddress)
         Creates a native script minting policy:
         "allow minting only when this wallet signs"
         The policy ID is derived from this script.

Step 3 — buildAssetNames(eventId, attendeeId)
         Returns: refName (000643b0 + suffix)
                  userName (001bc280 + suffix)

Step 4 — buildCip68Datum(params)
         Returns the Plutus Constructor 0 datum object
         with all metadata fields hex-encoded

Step 5 — new Transaction({ initiator: wallet })
           .mintAsset(forgingScript, { assetName: refName, recipient: { address, datum } })
           .mintAsset(forgingScript, { assetName: userName, recipient: recipientAddress })
         Both tokens in one transaction.

Step 6 — wallet.signTx(unsignedTx, true)
         Opens the wallet popup. User approves.

Step 7 — wallet.submitTx(signedTx)
         Submits directly to the Cardano Preprod network via the wallet.
         Returns the tx hash.
```

---

## Design decisions

### Native script vs Plutus minting policy

This demo uses `ForgeScript.withOneSignature(walletAddress)` — a native script that allows minting when the connected wallet signs. This means:

- No Aiken contract deployment needed
- The policy ID changes per wallet (not fixed)
- In production you would use the compiled Aiken `attendance_policy` from `../contracts/attendance/plutus.json` to get a fixed, reusable policy ID

### Reference NFT sent to your own wallet

In a full CIP-68 implementation the Reference NFT is locked at a **script address** (the `attendance_metadata` Aiken validator in this project). This demo sends it back to your own wallet for simplicity. The datum is still inline and fully on-chain — you can read it on Cardanoscan either way.

To lock it at the real script address instead, change one line in `MintButton.tsx`:

```typescript
// Change this:
recipient: { address: walletAddress, datum: { value: datum, inline: true } }

// To this:
recipient: { address: 'addr_test1...your_validator_address...', datum: { value: datum, inline: true } }
```

The validator address comes from `../contracts/attendance/plutus.json` after running `aiken build`.

### No backend

This app is entirely client-side. The Blockfrost key is `NEXT_PUBLIC_` (browser-visible). This is acceptable for Preprod testnet — the key has no mainnet value. For a production app, proxy Blockfrost requests through a Next.js API route to keep the key server-side.

---

## Key files reference

| File | What it does |
|---|---|
| `src/lib/constants.ts` | CIP-67 label prefixes and network URLs |
| `src/lib/cip68.ts` | `strToHex`, `buildAssetNames`, `buildCip68Datum` — pure logic, no React |
| `src/app/layout.tsx` | Wraps app with `MeshProvider` (wallet context) |
| `src/app/page.tsx` | Owns `params` and `txHash` state, composes the four components |
| `src/components/WalletPanel.tsx` | `CardanoWallet` component — CIP-30 wallet connection |
| `src/components/MintForm.tsx` | Controlled form for all `MintParams` fields |
| `src/components/MintButton.tsx` | The mint orchestration — all 7 steps live here |
| `src/components/TxResult.tsx` | Displays tx hash + Cardanoscan verification guide |

---

## Relationship to the parent plugin

The CIP-68 logic in this demo is a TypeScript port of the parent WordPress plugin:

| This demo | Parent plugin |
|---|---|
| `src/lib/cip68.ts` → `buildAssetNames()` | `includes/class-bch-nft.php` → `BCH_NFT::make_suffix()` |
| `src/lib/cip68.ts` → `buildCip68Datum()` | `assets/js/admin-mint.js` (lines 77–96) |
| `src/components/MintButton.tsx` | `assets/js/admin-mint.js` → `doMint()` |
| `ForgeScript.withOneSignature` | Same in `admin-mint.js` (Flow A) |

The parent plugin's **Flow B** (QR claim via NMKR) does the same thing server-side in PHP using the NMKR API. This demo shows the equivalent browser-side path without NMKR.
