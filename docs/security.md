# Security

- Capabilities: `querynova_manage_settings`, `querynova_view_analytics`, `querynova_manage_seo`, `querynova_manage_woocommerce_seo`, `querynova_run_analysis`, `querynova_manage_integrations`, `querynova_view_logs`, `querynova_manage_debug`.
- Roles: Administrator receives all of them. SEO Manager, Content Editor, Commerce Manager, and Developer receive the subsets in `RoleMap`. Custom roles are created on registration if they do not exist.
- Every private REST route has a `permission_callback`. A nonce is not authorization. Cookie-authenticated REST still gets WordPress nonce checks from core, in addition to the capability check.
- Input is validated before use. SQL values go through `$wpdb->prepare()`. Table and column identifiers are restricted to `[A-Za-z0-9_]`. Output is escaped at the edge.
- `SsrfGuard` allows only http and https, blocks localhost, `.local`, `.internal`, metadata hostnames, non-public ports, and any resolved IP in a private or reserved range.
- Secrets are encrypted at rest and redacted in logs. The plugin does not send telemetry.
- Customer name, email, phone, address, and payment data are not stored. Commerce analytics are aggregated.
- Uninstall retains data unless the site sets `querynova_delete_data_on_uninstall` to `yes`.
- Safe mode can be enabled with the `QUERYNOVA_SAFE_MODE` constant.
