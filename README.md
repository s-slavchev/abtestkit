# ABTestKit — unofficial mirror with proposed patches

This is an **unofficial** mirror of the [ABTestKit](https://wordpress.org/plugins/abtestkit/)
WordPress plugin (GPL-2.0-or-later), created to share proposed improvements with the
plugin author in an easy-to-review form.

- Official plugin: https://wordpress.org/plugins/abtestkit/
- Author's website: https://www.abtestkit.io/

The `main` branch contains the pristine v1.5.0 release as published on wordpress.org.
Each proposed change lives in its own branch, so the diff against `main` shows exactly
one improvement:

| Branch | Change |
|--------|--------|
| `fix/store-currency` | Show revenue in the store's WooCommerce currency instead of hardcoded GBP |

All credit for the plugin goes to its author. This repository only exists to propose
changes back upstream.
