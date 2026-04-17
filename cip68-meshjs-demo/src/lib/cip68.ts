import { LABEL_100, LABEL_222 } from "./constants";

// CONVERTS a utf-8 string to its hex representation
export function strToHex(str: string): string {
    return Array.from(new TextEncoder().encode(str)).map((b) => b.toString(16).padStart(2, '0')).join('');
}

export interface MintParams {
    eventId: number;
    attendeeId: number;
    eventTitle: string;
    eventDate: string;
    venue: string;
    attendeeName: string;
    bannerIpfs: string;
}

/**
 * Build CIP-68 asset names
 * Structure: label & suffix
 */
export function buildAssetNames(eventId: number, attendeeId: number) {
    const suffix = eventId.toString(16).padStart(28, '0') + attendeeId.toString(16).padStart(28, '0');
    return {
        refName: LABEL_100 + suffix,
        userName: LABEL_222 + suffix,
    }
}

/**
 * Build CIP-68 datum
 * on-chain shape: { eventId, attendeeId, eventDate, venue, attendeeName, bannerIpfs }
 * datum is stored inline on the reference nft's utxo
 */
// Mesh.js toPlutusData format: plain hex strings → bytes, plain numbers → int, JS Map → map, arrays → list
export function buildCip68Datum(params: MintParams): object {
    return {
        alternative: 0,
        fields: [
            new Map<string, string>([
                [strToHex('name'),          strToHex(`Attendance \u2013 ${params.eventTitle}`)],
                [strToHex('image'),         strToHex(params.bannerIpfs)],
                [strToHex('event_name'),    strToHex(params.eventTitle)],
                [strToHex('venue'),         strToHex(params.venue)],
                [strToHex('attendee_name'), strToHex(params.attendeeName)],
                [strToHex('attendee_id'),   strToHex(String(params.attendeeId))],
                [strToHex('mediaType'),     strToHex('image/png')],
                [strToHex('issued_by'),     strToHex('Blockchain Centre Hub')],
            ]),
            1, // cip-68 version
        ],
    };
}
