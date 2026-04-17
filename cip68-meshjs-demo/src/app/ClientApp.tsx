'use client';

import { useState } from 'react';
import { MeshProvider } from '@meshsdk/react';
import type { MintParams } from '@/lib/cip68';
import { WalletPanel } from '@/components/WalletPanel';
import { MintForm } from '@/components/MintForm';
import { MintButton } from '@/components/MintButton';
import { TxResult } from '@/components/TxResult';
import { BlockfrostStatus } from '@/components/BlockfrostStatus';

const defaultParams: MintParams = {
  eventId: 1,
  attendeeId: 1,
  eventTitle: 'Blockchain Centre Workshop',
  eventDate: '2026-04-15',
  venue: 'Nairobi',
  attendeeName: 'Dan',
  bannerIpfs: 'ipfs://Qmplaceholder',
};

export default function ClientApp() {
  const [params, setParams] = useState<MintParams>(defaultParams);
  const [txHash, setTxHash] = useState<string | null>(null);

  return (
    <MeshProvider>
      <main className="max-w-2xl mx-auto px-4 py-12 space-y-8">
        <div>
          <h1 className="text-3xl font-bold text-white">CIP-68 NFT Demo</h1>
          <p className="text-gray-400 mt-1">
            Mint a CIP-68 attendance NFT directly with Mesh.js - no NMKR
          </p>
        </div>
        <BlockfrostStatus />
        <WalletPanel />
        <MintForm value={params} onChange={setParams} />
        <MintButton
          params={params}
          onSuccess={(txHash) => {
            setTxHash(txHash);
            setParams(p => ({ ...p, attendeeId: p.attendeeId + 1 }));
          }}
        />
        {txHash && (
          <TxResult
            txHash={txHash}
            onReset={() => setTxHash(null)}
          />
        )}
      </main>
    </MeshProvider>
  );
}
