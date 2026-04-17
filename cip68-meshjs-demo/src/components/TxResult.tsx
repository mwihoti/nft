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