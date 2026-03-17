# Simple DAL - Data Integrity Plugin

Data integrity checksums and signatures plugin for Simple DAL. Transparently computes and verifies hashes and signatures on records and attachments, ensuring data has not been tampered with.

Part of the [Simple DAL](https://opensource.duma.sh/libraries/php/simple-dal) project.

## Installation

```bash
composer require kduma/simple-dal-data-integrity
```

Requires a hashing algorithm provider package (e.g. `kduma/simple-dal-data-integrity-sodium`). An optional signing algorithm can be provided for signature-based verification.

## Documentation

See the [full documentation](https://opensource.duma.sh/libraries/php/simple-dal).

## License

MIT
