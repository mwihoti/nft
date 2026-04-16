use aiken/list
use aiken/transaction.{OutputReference, Transaction}
use aiken/transaction/value.{PolicyId}

// ---------------------------------------------------------------------------
// CIP-68 Attendance NFT — Minting Policy
//
// Only the admin (identified by their public key hash) may mint or burn
// the reference NFT (label 100) and user NFT (label 222) for this policy.
// ---------------------------------------------------------------------------
validator attendance_policy(admin_pkh: ByteArray) {
  mint(_redeemer: Data, _policy_id: PolicyId, tx: Transaction) {
    list.has(tx.extra_signatories, admin_pkh)
  }

  else(_) {
    fail
  }
}

// ---------------------------------------------------------------------------
// CIP-68 Attendance NFT — Spend Validator (Metadata Update)
//
// The reference NFT (label 100) is locked at this validator's address.
// The datum follows CIP-68: Constr 0 [metadata_map, version, extra].
// The `extra` field stores the admin public key hash (as ByteArray).
//
// Spending is allowed only when the admin signs the transaction, enabling
// mutable metadata updates: spend the UTXO, produce a new output at the
// same address with an updated datum.
// ---------------------------------------------------------------------------

pub type Cip68Datum {
  metadata: Data,
  version: Int,
  extra: ByteArray,
}

validator attendance_metadata {
  spend(
    datum: Option<Cip68Datum>,
    _redeemer: Data,
    _output_reference: OutputReference,
    tx: Transaction,
  ) {
    when datum is {
      Some(d) -> list.has(tx.extra_signatories, d.extra)
      None -> fail
    }
  }

  else(_) {
    fail
  }
}
