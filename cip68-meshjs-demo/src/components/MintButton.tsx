'use client';

import { useState } from 'react';
import { useWallet } from '@meshsdk/react';
import { buildAssetNames, buildCip68Datum, type MintParams } from '@/lib/cip68';
import { USER_NFT_RECIPIENT } from '@/lib/constants';

type Status = 'idle' | 'building' | 'signing' | 'submitting' | 'error';

interface Props {
    params: MintParams;
    onSuccess?: (txHash: string) => void;

}


export function MintButton({ params, onSuccess }: Props) {
    const { wallet, connected } = useWallet();
    const [status, setStatus] = useState<Status>('idle');
    const [error, setError] = useState('');

    async function handleMint() {
        setError('');
        try {
            setStatus('building');
            const { ForgeScript, Transaction, BlockfrostProvider, resolvePaymentKeyHash, resolveNativeScriptHash } = await import('@meshsdk/core');

            const blockfrost = new BlockfrostProvider(process.env.NEXT_PUBLIC_BLOCKFROST_KEY!);

            const walletAddress = await wallet.getChangeAddress();
            const forgingScript = ForgeScript.withOneSignature(walletAddress);
            const { refName, userName } = buildAssetNames(params.eventId, params.attendeeId);
            const datum = buildCip68Datum(params);

            // Derive policyId without re-serialising the forge script through stringToHex
            const policyId = resolveNativeScriptHash({ type: 'sig', keyHash: resolvePaymentKeyHash(walletAddress) });

            const tx = new Transaction({ initiator: wallet, fetcher: blockfrost, evaluator: blockfrost });

            // Use txBuilder directly so the pre-built CIP-68 hex asset names are not
            // double-encoded by mintAsset's internal stringToHex() call.
            tx.txBuilder
                .mint('1', policyId, refName)
                .mintingScript(forgingScript)
                .txOut(walletAddress, [{ unit: policyId + refName, quantity: '1' }])
                .txOutInlineDatumValue(datum);

            tx.txBuilder
                .mint('1', policyId, userName)
                .mintingScript(forgingScript)
                .txOut(USER_NFT_RECIPIENT, [{ unit: policyId + userName, quantity: '1' }]);

            const unsignedTx = await tx.build();
            setStatus('signing');
            
            const signedTx = await wallet.signTx(unsignedTx, true);
            setStatus('submitting');
            const txHash = await wallet.submitTx(signedTx);
            onSuccess?.(txHash);
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