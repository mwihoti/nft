'use client';

import { useEffect, useState } from 'react';

type Status = 'checking' | 'ok' | 'error';

export function BlockfrostStatus() {
    const [status, setStatus] = useState<Status>('checking');
    const [detail, setDetail] = useState('');

    useEffect(() => {
        async function check() {
            const key = process.env.NEXT_PUBLIC_BLOCKFROST_KEY;
            if (!key || key === 'preprodKeyHere') {
                setStatus('error');
                setDetail('NEXT_PUBLIC_BLOCKFROST_KEY not set in .env.local');
                return;
            }
            try {
                const { BlockfrostProvider } = await import('@meshsdk/core');
                const bf = new BlockfrostProvider(key);
                const params = await bf.fetchProtocolParameters();
                setDetail(`epoch ${params.epoch ?? '—'} · min fee ${params.minFeeA ?? '—'} lovelace/byte`);
                setStatus('ok');
            } catch (e: unknown) {
                setStatus('error');
                setDetail(e instanceof Error ? e.message : String(e));
            }
        }
        check();
    }, []);

    const colours: Record<Status, string> = {
        checking: 'text-yellow-400 bg-yellow-400/10',
        ok:       'text-green-400  bg-green-400/10',
        error:    'text-red-400    bg-red-950',
    };

    const icons: Record<Status, string> = {
        checking: '⏳',
        ok:       '✓',
        error:    '✗',
    };

    return (
        <div className={`rounded-lg px-4 py-2 text-xs font-mono flex items-center gap-2 ${colours[status]}`}>
            <span>{icons[status]}</span>
            <span>
                Blockfrost ({status === 'checking' ? 'connecting…' : status})
                {detail && ` — ${detail}`}
            </span>
        </div>
    );
}
