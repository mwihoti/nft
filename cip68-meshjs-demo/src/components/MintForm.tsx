'use client';

import type { MintParams } from "@/lib/cip68";

interface Props {
    value: MintParams;
    onChange: (updated: MintParams) => void;
}

export function MintForm({ value, onChange}: Props) {
    function set(field: keyof MintParams, val: string | number) {
        onChange({ ...value, [field]: val});
    }

    return (
        <section className="bg-gray-900 rounded-xl p-6 space-y-4">
            <h2 className="text-lg font-semibold">Step 2 - NFT Metadata</h2>
            <p className="text-gray-400 text-sm">
                These fields are encoded into the CIP-68 datum stored on the reference nft UTXO.
            </p>
            <div className="grid grid-cols-2 gap-4">
                <label className="space-y-1">
                    <span className="text-xs text-gray-400">Event ID</span>
                    <input type="number" value={value.eventId} onChange={e => set('eventId', Number(e.target.value))}
                    className="w-full bg-gray-800 rounded px-3 py-2 text-sm"/>
                    
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

            </div>
        </section>
    )
}