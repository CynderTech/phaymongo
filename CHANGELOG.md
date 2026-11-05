# Changelog

## 2.0.0

### Breaking
- Package renamed from `micahbule/phaymongo` to `cyndertech/phaymongo` (`replace`s the old name). The `Paymongo\Phaymongo` namespace is unchanged.
- Requires PHP `^7.4 || ^8.0` and `guzzlehttp/guzzle ^7.4.5`.
- `client_ops` now only accepts `unwrap` and `return_response`; any other key throws `InvalidArgumentException`. Previously any property (including `secret_key`) could be overwritten.
- Requests now resolve under `https://api.paymongo.com/v1/`. The old base URI plus absolute paths silently dropped the `/v1` prefix. Verify behaviour against your Paymongo account before upgrading.
- Amounts are converted with `(int) round($amount * 100)` instead of `$amount * 100`, so `19.99` is sent as `1999` rather than `1998.9999999999998`. `Link::create` is unchanged and still sends the amount as given.

### Security
- URL-encode all resource IDs placed in request paths.
- Default HTTP timeouts (30s total, 10s connect); override through `$guzzle_ops`.
- API keys are masked in `var_dump()` / `print_r()` output.
- JSON encoding failures throw instead of sending an empty body.
- 5xx and non-JSON error responses no longer crash error handling; `PaymongoException` now carries the HTTP status code, the previous exception, and `getErrors()`.
- Minimum Guzzle and `guzzlehttp/psr7` versions include fixes for known CVEs.

### Tooling
- CI matrix for PHP 7.4–8.5, `composer audit`, and a Pest-free smoke test for PHP < 8.2.
- `composer.lock` is no longer committed (library).
