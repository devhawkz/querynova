# Production

Production is quiet. The default log level is INFO. People see an error reference, not a stack trace. Source maps are omitted from `npm run build`. Dev dependencies are omitted from `composer install --no-dev --optimize-autoloader`.

Do not ship `node_modules`, tests, `.git`, GitHub workflows, `.env`, credentials, or development source maps in the release ZIP. `npm run package` is the release path.

Core SEO must keep working when a provider, the crawler, or a future QueryNova Cloud endpoint is down. Optional module failures are isolated. Public requests must not wait on SERP, backlink, LLM, crawl, analytics aggregation, or rank refresh calls.

DEBUG in production is a timed window: 15 minutes, 1 hour, 6 hours, or 24 hours. It turns itself off.
