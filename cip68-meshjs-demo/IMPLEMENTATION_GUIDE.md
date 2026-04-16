# CIP-68 Mesh.js — Implementation Guide

All files in creation order. Add config files first, run `npm install`, then write the `src/` files.

---

## Creation order

```
1.  .env.local.example
2.  .gitignore
3.  package.json
4.  tsconfig.json
5.  next.config.mjs
6.  tailwind.config.ts
7.  postcss.config.mjs
        ↓ run: npm install
8.  src/lib/constants.ts
9.  src/lib/cip68.ts
10. src/app/globals.css
11. src/app/layout.tsx
12. src/app/page.tsx
13. src/components/TxResult.tsx
14. src/components/WalletPanel.tsx
15. src/components/MintForm.tsx
16. src/components/MintButton.tsx
```

---

## Config files

### 1. `.env.local.example`

```
# Get a free Preprod project at https://blockfrost.io
NEXT_PUBLIC_BLOCKFROST_KEY=preprodYourKeyHere
```

Copy this to `.env.local` and paste your real key. The `NEXT_PUBLIC_` prefix exposes it to the browser — required because Mesh.js runs client-side.

---

### 2. `.gitignore`

```
node_modules/
.next/
.env.local
.env.*.local
.DS_Store
```

---

### 3. `package.json`

```json
{
  "name": "cip68-meshjs-demo",
  "version": "0.1.0",
  "private": true,
  "scripts": {
    "dev": "next dev",
    "build": "next build",
    "start": "next start"
  },
  "dependencies": {
    "@meshsdk/core": "^1.7.2",
    "@meshsdk/react": "^1.4.5",
    "next": "14.2.5",
    "react": "^18.3.1",
    "react-dom": "^18.3.1"
  },
  "devDependencies": {
    "@types/node": "^20.14.9",
    "@types/react": "^18.3.3",
    "@types/react-dom": "^18.3.0",
    "autoprefixer": "^10.4.19",
    "postcss": "^8.4.39",
    "tailwindcss": "^3.4.4",
    "typescript": "^5.5.3"
  }
}
```

Two Mesh.js packages: `@meshsdk/core` has the transaction-building logic; `@meshsdk/react` has the wallet context and `CardanoWallet` UI component.

---

### 4. `tsconfig.json`

```json
{
  "compilerOptions": {
    "lib": ["dom", "dom.iterable", "esnext"],
    "allowJs": true,
    "skipLibCheck": true,
    "strict": true,
    "noEmit": true,
    "esModuleInterop": true,
    "module": "esnext",
    "moduleResolution": "bundler",
    "resolveJsonModule": true,
    "isolatedModules": true,
    "jsx": "preserve",
    "incremental": true,
    "plugins": [{ "name": "next" }],
    "paths": { "@/*": ["./src/*"] }
  },
  "include": ["next-env.d.ts", "**/*.ts", "**/*.tsx", ".next/types/**/*.ts"],
  "exclude": ["node_modules"]
}
```

The `"@/*": ["./src/*"]` path alias lets you write `import { ... } from '@/lib/cip68'` instead of `../../lib/cip68`.

---

### 5. `next.config.mjs`

```js
/** @type {import('next').NextConfig} */
const nextConfig = {
  transpilePackages: ['@meshsdk/core', '@meshsdk/react'],
};

export default nextConfig;
```

**Critical.** Mesh.js ships as ESM modules. Without `transpilePackages`, Next.js's bundler fails to process it and you get a `SyntaxError: Cannot use import statement` crash on startup.

---

### 6. `tailwind.config.ts`

```ts
import type { Config } from 'tailwindcss';

const config: Config = {
  content: [
    './src/pages/**/*.{js,ts,jsx,tsx,mdx}',
    './src/components/**/*.{js,ts,jsx,tsx,mdx}',
    './src/app/**/*.{js,ts,jsx,tsx,mdx}',
  ],
  theme: {
    extend: {},
  },
  plugins: [],
};

export default config;
```

---

### 7. `postcss.config.mjs`

```js
const config = {
  plugins: {
    tailwindcss: {},
    autoprefixer: {},
  },
};

export default config;
```

---

> **Run `npm install` now before creating any `src/` files.**

---

## Library files

### 8. `src/lib/constants.ts`

```ts
// CIP-67 label prefixes — defined by the standard, do not change
export const LABEL_100 = '000643b0'; // Reference NFT
export const LABEL_222 = '001bc280'; // User NFT

export const NETWORK = 'preprod';
export const CARDANOSCAN_BASE = 'https://preprod.cardanoscan.io/transaction/';
```

These hex prefixes are how on-chain indexers know which token is the Reference NFT vs the User NFT. `000643b0` = decimal label 100, `001bc280` = decimal label 222 in the CIP-67 encoding scheme.

---

### 9. `src/lib/cip68.ts`

```ts
import { LABEL_100, LABEL_222 } from './constants';

// Converts a UTF-8 string to its hex representation.
// Every key and value in the CIP-68 datum map is stored as hex bytes.
export function strToHex(str: string): string {
  return Array.from(new TextEncoder().encode(str))
    .map((b) => b.toString(16).padStart(2, '0'))
    .join('');
}

export interface MintParams {
  eventId: number;
  attendeeId: number;
  eventTitle: string;
  eventDate: string;
  venue: string;
  attendeeName: string;
  bannerIpfs: string;
  recipientAddress: string; // wallet that receives the User NFT (label 222)
}

/**
 * Build CIP-68 asset names.
 *
 * Structure: label prefix (4 bytes) + suffix (28 bytes) = 32 bytes total
 * Suffix:    eventId zero-padded to 14 bytes + attendeeId zero-padded to 14 bytes
 *
 * Mirrors BCH_NFT::make_suffix() in the parent plugin's class-bch-nft.php.
 * Both produce identical names for the same IDs.
 */
export function buildAssetNames(eventId: number, attendeeId: number) {
  const suffix =
    eventId.toString(16).padStart(28, '0') +
    attendeeId.toString(16).padStart(28, '0');
  return {
    refName: LABEL_100 + suffix,  // 000643b0 + suffix
    userName: LABEL_222 + suffix, // 001bc280 + suffix
  };
}

/**
 * Build CIP-68 datum.
 *
 * On-chain shape (Plutus):
 *   Constructor 0 (
 *     Map [ (bytes, bytes), ... ],   -- metadata key-value pairs
 *     Int 1                          -- version number
 *   )
 *
 * Mesh.js represents this as { alternative: 0, fields: [...] }
 * where alternative: 0 = Constructor 0.
 *
 * This datum is stored INLINE on the Reference NFT's UTxO.
 * That is the defining feature of CIP-68: metadata lives on-chain in a datum,
 * not in transaction metadata like CIP-25.
 */
export function buildCip68Datum(params: MintParams): object {
  return {
    alternative: 0,
    fields: [
      {
        map: [
          { k: { bytes: strToHex('name') },          v: { bytes: strToHex(`Attendance \u2013 ${params.eventTitle}`) } },
          { k: { bytes: strToHex('image') },         v: { bytes: strToHex(params.bannerIpfs) } },
          { k: { bytes: strToHex('event_name') },    v: { bytes: strToHex(params.eventTitle) } },
          { k: { bytes: strToHex('event_date') },    v: { bytes: strToHex(params.eventDate) } },
          { k: { bytes: strToHex('venue') },         v: { bytes: strToHex(params.venue) } },
          { k: { bytes: strToHex('attendee_name') }, v: { bytes: strToHex(params.attendeeName) } },
          { k: { bytes: strToHex('attendee_id') },   v: { bytes: strToHex(String(params.attendeeId)) } },
          { k: { bytes: strToHex('mediaType') },     v: { bytes: strToHex('image/png') } },
          { k: { bytes: strToHex('issued_by') },     v: { bytes: strToHex('Blockchain Centre Hub') } },
        ],
      },
      { int: 1 }, // CIP-68 version field
    ],
  };
}
```

**Key concept:** Every key and value in the map is hex-encoded bytes. `strToHex('name')` produces `'6e616d65'`. The datum is a Plutus constructor — `alternative: 0` is Mesh.js's way of saying "Constructor 0".

---

## App shell

### 10. `src/app/globals.css`

```css
@tailwind base;
@tailwind components;
@tailwind utilities;
```

---

### 11. `src/app/layout.tsx`

```tsx
'use client';

import { MeshProvider } from '@meshsdk/react';
import './globals.css';

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="en">
      <body className="bg-gray-950 text-gray-100 min-h-screen">
        <MeshProvider>{children}</MeshProvider>
      </body>
    </html>
  );
}
```

`MeshProvider` sets up the wallet context for the whole app. The `'use client'` directive is required because `MeshProvider` uses browser APIs internally. All child components that call `useWallet()` read from this context.

---

### 12. `src/app/page.tsx`

```tsx
'use client';

import { useState } from 'react';
import type { MintParams } from '@/lib/cip68';
import { WalletPanel } from '@/components/WalletPanel';
import { MintForm } from '@/components/MintForm';
import { MintButton } from '@/components/MintButton';
import { TxResult } from '@/components/TxResult';

const defaultParams: MintParams = {
  eventId: 1,
  attendeeId: 1,
  eventTitle: 'Blockchain Centre Workshop',
  eventDate: '2026-04-15',
  venue: 'Nairobi',
  attendeeName: 'Alice',
  bannerIpfs: 'ipfs://QmPlaceholder',
  recipientAddress: '',
};

export default function Home() {
  const [params, setParams] = useState<MintParams>(defaultParams);
  const [txHash, setTxHash] = useState<string | null>(null);

  return (
    <main className="max-w-2xl mx-auto px-4 py-12 space-y-8">
      <div>
        <h1 className="text-3xl font-bold text-white">CIP-68 NFT Demo</h1>
        <p className="text-gray-400 mt-1">
          Mint a CIP-68 attendance NFT directly with Mesh.js — no NMKR.
        </p>
      </div>

      <WalletPanel />
      <MintForm value={params} onChange={setParams} />
      <MintButton params={params} onSuccess={setTxHash} />
      {txHash && (
        <TxResult
          txHash={txHash}
          onReset={() => { setTxHash(null); setParams(defaultParams); }}
        />
      )}
    </main>
  );
}
```

This page owns all the state. `params` flows down to `MintForm` (for editing) and `MintButton` (for minting). When minting succeeds, `txHash` is set and `TxResult` appears.

---

## Components

### 13. `src/components/TxResult.tsx`

```tsx
'use client';

import { CARDANOSCAN_BASE } from '@/lib/constants';

interface Props {
  txHash: string;
  onReset: () => void;
}

export function TxResult({ txHash, onReset }: Props) {
  return (
    <section className="bg-gray-900 border border-green-500/30 rounded-xl p-6 space-y-4">
      <h2 className="text-lg font-semibold text-green-400">Minted successfully</h2>

      <div>
        <p className="text-xs text-gray-400 mb-1">Transaction hash</p>
        <p className="font-mono text-xs bg-gray-800 rounded p-3 break-all">{txHash}</p>
      </div>

      <a
        href={`${CARDANOSCAN_BASE}${txHash}`}
        target="_blank"
        rel="noopener noreferrer"
        className="block text-center bg-green-700 hover:bg-green-600 text-white text-sm font-semibold py-2 rounded-lg transition-colors"
      >
        View on Cardanoscan Preprod →
      </a>

      <div className="bg-gray-800 rounded p-3 text-xs text-gray-400 space-y-2">
        <p className="font-semibold text-gray-300">What to look for on Cardanoscan:</p>
        <ol className="list-decimal list-inside space-y-1">
          <li>Click the <strong>Assets</strong> tab — two tokens minted in one tx</li>
          <li>The <span className="text-blue-400 font-mono">000643b0…</span> token is the <strong>Reference NFT</strong></li>
          <li>Click it → open the UTxO → click <strong>Datum</strong></li>
          <li>You will see <strong>Constructor 0</strong> with a map — that is the CIP-68 on-chain metadata</li>
          <li>The <span className="text-purple-400 font-mono">001bc280…</span> token is the <strong>User NFT</strong> at the recipient</li>
        </ol>
      </div>

      <button
        onClick={onReset}
        className="w-full border border-gray-700 hover:border-gray-500 text-gray-400 text-sm py-2 rounded-lg transition-colors"
      >
        Mint another
      </button>
    </section>
  );
}
```

---

### 14. `src/components/WalletPanel.tsx`

```tsx
'use client';

import { CardanoWallet, useWallet } from '@meshsdk/react';

export function WalletPanel() {
  const { connected, name } = useWallet();

  return (
    <section className="bg-gray-900 rounded-xl p-6 space-y-3">
      <h2 className="text-lg font-semibold">Step 1 — Connect Wallet</h2>
      <p className="text-gray-400 text-sm">
        Use a wallet set to <strong>Preprod testnet</strong> with some test ADA.
      </p>

      <CardanoWallet />

      {connected && (
        <p className="text-green-400 text-sm">
          Connected: <span className="font-mono">{name}</span>
        </p>
      )}
    </section>
  );
}
```

`CardanoWallet` is a pre-built Mesh.js component that detects every installed CIP-30 wallet (Eternl, Nami, Lace, Flint) and shows a connect button for each. `useWallet()` reads the connected wallet state from `MeshProvider` context.

---

### 15. `src/components/MintForm.tsx`

```tsx
'use client';

import type { MintParams } from '@/lib/cip68';

interface Props {
  value: MintParams;
  onChange: (updated: MintParams) => void;
}

export function MintForm({ value, onChange }: Props) {
  function set(field: keyof MintParams, val: string | number) {
    onChange({ ...value, [field]: val });
  }

  return (
    <section className="bg-gray-900 rounded-xl p-6 space-y-4">
      <h2 className="text-lg font-semibold">Step 2 — NFT Metadata</h2>
      <p className="text-gray-400 text-sm">
        These fields are encoded into the CIP-68 datum stored on the Reference NFT UTxO.
      </p>

      <div className="grid grid-cols-2 gap-4">
        <label className="space-y-1">
          <span className="text-xs text-gray-400">Event ID</span>
          <input type="number" value={value.eventId}
            onChange={e => set('eventId', Number(e.target.value))}
            className="w-full bg-gray-800 rounded px-3 py-2 text-sm" />
        </label>

        <label className="space-y-1">
          <span className="text-xs text-gray-400">Attendee ID</span>
          <input type="number" value={value.attendeeId}
            onChange={e => set('attendeeId', Number(e.target.value))}
            className="w-full bg-gray-800 rounded px-3 py-2 text-sm" />
        </label>

        <label className="col-span-2 space-y-1">
          <span className="text-xs text-gray-400">Event Title</span>
          <input type="text" value={value.eventTitle}
            onChange={e => set('eventTitle', e.target.value)}
            className="w-full bg-gray-800 rounded px-3 py-2 text-sm" />
        </label>

        <label className="space-y-1">
          <span className="text-xs text-gray-400">Event Date</span>
          <input type="date" value={value.eventDate}
            onChange={e => set('eventDate', e.target.value)}
            className="w-full bg-gray-800 rounded px-3 py-2 text-sm" />
        </label>

        <label className="space-y-1">
          <span className="text-xs text-gray-400">Venue</span>
          <input type="text" value={value.venue}
            onChange={e => set('venue', e.target.value)}
            className="w-full bg-gray-800 rounded px-3 py-2 text-sm" />
        </label>

        <label className="col-span-2 space-y-1">
          <span className="text-xs text-gray-400">Attendee Name</span>
          <input type="text" value={value.attendeeName}
            onChange={e => set('attendeeName', e.target.value)}
            className="w-full bg-gray-800 rounded px-3 py-2 text-sm" />
        </label>

        <label className="col-span-2 space-y-1">
          <span className="text-xs text-gray-400">Banner IPFS URI</span>
          <input type="text" value={value.bannerIpfs}
            placeholder="ipfs://Qm..."
            onChange={e => set('bannerIpfs', e.target.value)}
            className="w-full bg-gray-800 rounded px-3 py-2 text-sm" />
        </label>

        <label className="col-span-2 space-y-1">
          <span className="text-xs text-gray-400">Recipient Wallet Address (receives the User NFT)</span>
          <input type="text" value={value.recipientAddress}
            placeholder="addr_test1..."
            onChange={e => set('recipientAddress', e.target.value)}
            className="w-full bg-gray-800 rounded px-3 py-2 text-sm font-mono" />
        </label>
      </div>
    </section>
  );
}
```

Pure controlled component — it never holds state itself, just calls `onChange` and lets the parent (`page.tsx`) own the data.

---

### 16. `src/components/MintButton.tsx`

```tsx
'use client';

import { useState } from 'react';
import { useWallet } from '@meshsdk/react';
import { buildAssetNames, buildCip68Datum, type MintParams } from '@/lib/cip68';

type Status = 'idle' | 'building' | 'signing' | 'submitting' | 'error';

interface Props {
  params: MintParams;
  onSuccess: (txHash: string) => void;
}

export function MintButton({ params, onSuccess }: Props) {
  const { wallet, connected } = useWallet();
  const [status, setStatus] = useState<Status>('idle');
  const [error, setError] = useState('');

  async function handleMint() {
    setError('');
    try {
      // ── 1. Dynamic import avoids Next.js SSR crash
      //       (Mesh.js uses browser-only APIs like WebCrypto)
      setStatus('building');
      const { ForgeScript, Transaction } = await import('@meshsdk/core');

      // ── 2. Native script minting policy
      //       "allow minting only when this wallet address signs"
      //       The policy ID is derived from this script — it changes per wallet.
      //       In production you would use a deployed Aiken/Plutus script for a fixed policy ID.
      const walletAddress = await wallet.getChangeAddress();
      const forgingScript = ForgeScript.withOneSignature(walletAddress);

      // ── 3. CIP-68 asset names
      //       refName  = 000643b0 + suffix  ← Reference NFT (holds metadata)
      //       userName = 001bc280 + suffix  ← User NFT (what the attendee owns)
      const { refName, userName } = buildAssetNames(params.eventId, params.attendeeId);

      // ── 4. CIP-68 datum
      //       Constructor 0 ( metadata_map, version_int )
      //       This is what makes CIP-68 different from CIP-25:
      //       metadata lives in a Plutus datum on-chain, not in tx metadata.
      const datum = buildCip68Datum(params);

      // ── 5. Build transaction — both tokens minted in one tx
      const tx = new Transaction({ initiator: wallet });

      // Reference NFT → back to our wallet WITH the inline datum attached
      tx.mintAsset(forgingScript, {
        assetName: refName,
        assetQuantity: '1',
        recipient: {
          address: walletAddress,
          datum: { value: datum, inline: true }, // ← the CIP-68 part
        },
      });

      // User NFT → recipient wallet, no datum needed
      tx.mintAsset(forgingScript, {
        assetName: userName,
        assetQuantity: '1',
        recipient: params.recipientAddress,
      });

      const unsignedTx = await tx.build();

      // ── 6. Sign — opens the wallet popup
      setStatus('signing');
      const signedTx = await wallet.signTx(unsignedTx, true);

      // ── 7. Submit via the wallet's own CIP-30 submitTx
      setStatus('submitting');
      const txHash = await wallet.submitTx(signedTx);

      onSuccess(txHash);
      setStatus('idle');
    } catch (err: unknown) {
      setStatus('error');
      setError(err instanceof Error ? err.message : String(err));
    }
  }

  const labels: Record<Status, string> = {
    idle: 'Mint NFT',
    building: 'Building transaction...',
    signing: 'Waiting for wallet signature...',
    submitting: 'Submitting to Preprod...',
    error: 'Retry',
  };

  const busy = status === 'building' || status === 'signing' || status === 'submitting';

  return (
    <section className="bg-gray-900 rounded-xl p-6 space-y-3">
      <h2 className="text-lg font-semibold">Step 3 — Mint</h2>

      <div className="bg-gray-800 rounded p-3 text-xs text-gray-400 space-y-1">
        <p>
          <span className="text-blue-400 font-mono">000643b0…</span>
          {' '}Reference NFT → your wallet, with metadata as inline datum
        </p>
        <p>
          <span className="text-purple-400 font-mono">001bc280…</span>
          {' '}User NFT → recipient address
        </p>
      </div>

      <button
        onClick={handleMint}
        disabled={!connected || busy}
        className="w-full bg-blue-600 hover:bg-blue-500 disabled:bg-gray-700 disabled:text-gray-500 text-white font-semibold py-3 rounded-lg transition-colors"
      >
        {labels[status]}
      </button>

      {!connected && (
        <p className="text-yellow-400 text-xs">Connect your wallet first.</p>
      )}
      {status === 'error' && error && (
        <p className="text-red-400 text-xs font-mono bg-red-950 p-3 rounded">{error}</p>
      )}
    </section>
  );
}
```

This is the heart of the demo. The seven numbered steps in the comments are the full minting flow — read them top to bottom.

---

## Run it

```bash
cd cip68-meshjs-demo
npm install
cp .env.local.example .env.local
# paste your Blockfrost preprod key into .env.local
npm run dev
# → open http://localhost:3000
```

---

## Three things that trip people up

**1. Skip `next.config.mjs` and the app crashes on startup**
Mesh.js is ESM. `transpilePackages` in `next.config.mjs` is not optional.

**2. Static import of `@meshsdk/core` at the top of a file**
Use dynamic `await import('@meshsdk/core')` inside the handler function. Top-level static imports break during Next.js server-side rendering.

**3. Forgetting `MeshProvider` in `layout.tsx`**
Without it, `useWallet()` and `CardanoWallet` throw a context error. The `'use client'` directive on `layout.tsx` is also required.

---

## Verify on Cardanoscan

After a successful mint, open the Cardanoscan link and:

1. **Assets tab** → two tokens in one transaction
2. Click `000643b0…` → UTxO → **Datum** tab
3. You will see **Constructor 0** with a key-value map — that is the CIP-68 metadata stored on-chain
4. `001bc280…` token appears in the recipient wallet
