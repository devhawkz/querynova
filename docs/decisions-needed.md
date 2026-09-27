# Decision needed

### Problem

The Definition of Done still requires three runs that this checkout cannot execute: an end-to-end browser pass of the admin app, a boot of a real WordPress release, and a boot of a real WooCommerce release. `wp`, `mysql`, `docker`, and `playwright` are not installed. This task forbids installing Docker, WordPress, MySQL, or a browser on this Mac. PHPUnit and Vitest do not boot those environments, so the runs stay untested.

### Option A

Leave the three runs unrun. Keep the admin app and the test-category rows in progress, and keep the Definition of Done open. Do not install the missing tools on this Mac.

### Option B

Add the three runs later on a CI runner that already has MySQL, the WordPress test library, and a browser driver. Do not install those tools on this Mac. Mark a WordPress release, a WooCommerce release, or the browser pass as tested only after that job has actually passed.

### Option C

Treat the current PHPUnit and Vitest results as enough to call the product complete, and drop the real WordPress boot, the real WooCommerce boot, and the browser pass from the Definition of Done.

### Recommendation

Follow Option A until a CI job exists, then follow Option B. Do not install Docker, WordPress, MySQL, or a browser here. Do not mark the three runs as tested. QueryNova stays incomplete.

### Decision required

Choose whether a future CI job should boot a WordPress release, a WooCommerce release, and an end-to-end browser pass, or leave the product incomplete until those environments are run somewhere else.
