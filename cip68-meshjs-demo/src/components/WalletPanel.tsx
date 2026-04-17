'use client';

import { CardanoWallet, useWallet, useWalletList } from "@meshsdk/react";

export function WalletPanel() {
  const { connected, name, connect, error } = useWallet();
  const wallets = useWalletList();

  const hasEternl = wallets.some(w => w.name.toLowerCase() === 'eternl');

  async function connectEternl() {
    try {
      // Direct CIP-0030 enable — bypasses the Mesh UI but hits the same Eternl API
      await connect('eternl');
    } catch (e) {
      console.error('Eternl connect error:', e);
    }
  }

  return (
    <section className="bg-gray-900 rounded-xl p-6 space-y-3">
      <h2 className="text-lg font-semibold">Step 1 — Connect wallet</h2>
      <p className="text-gray-400 text-sm">
        Use a wallet set to <strong>Preprod testnet</strong> with some test ADA.
      </p>

      {wallets.length === 0 ? (
        <p className="text-yellow-400 text-sm bg-yellow-400/10 rounded p-3">
          No Cardano wallet extension detected. Install Nami, Lace, Eternl, or Flint and reload.
        </p>
      ) : (
        <div className="space-y-2">
          <CardanoWallet />
          {hasEternl && !connected && (
            <button
              onClick={connectEternl}
              className="w-full border border-blue-700 hover:border-blue-500 text-blue-400 text-sm py-2 rounded-lg transition-colors"
            >
              Connect Eternl directly
            </button>
          )}
        </div>
      )}

      {connected && (
        <p className="text-green-400 text-sm">
          Connected: <span className="font-mono">{name}</span>
        </p>
      )}

      {error && (
        <p className="text-red-400 text-xs font-mono bg-red-950 p-3 rounded">
          {String(error)}
        </p>
      )}

      {hasEternl && !connected && (
        <div className="text-yellow-500 text-xs bg-yellow-500/10 rounded p-3 space-y-1">
          <p className="font-semibold">Eternl on localhost requires manual allowlisting:</p>
          <ol className="list-decimal list-inside space-y-1">
            <li>Open Eternl → click the <strong>menu (☰)</strong> or settings gear</li>
            <li>Go to <strong>Settings → dApp Connector</strong> (or Trusted Sites)</li>
            <li>Add <code className="bg-black/30 px-1 rounded">localhost</code> or <code className="bg-black/30 px-1 rounded">http://localhost:3000</code></li>
            <li>Save and reload this page, then try connecting again</li>
          </ol>
        </div>
      )}
    </section>
  );
}
