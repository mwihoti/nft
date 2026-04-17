'use client';

import dynamic from 'next/dynamic';

// ClientApp imports @meshsdk/react + WASM — must never run on the server
const ClientApp = dynamic(() => import('./ClientApp'), { ssr: false });

export default function Page() {
  return <ClientApp />;
}
