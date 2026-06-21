# TenderAI on Hostinger

Target path: `/home/u319040066/domains/ahmadalhashaykeh.com/public_html/tenderai`

Public URL: `https://ahmadalhashaykeh.com/tenderai`

## One-time SSH setup

1. Open **hPanel → Advanced → SSH Access** and note host, port (usually `65002`), and username `u319040066`.

2. SSH from your machine:

```bash
ssh -p 65002 u319040066@ahmadalhashaykeh.com
```

3. On the server, create DB credentials in hPanel → **Databases**, then:

```bash
cd /home/u319040066/domains/ahmadalhashaykeh.com/public_html/tenderai
cp deploy/hostinger.env.example deploy/hostinger.env
nano deploy/hostinger.env   # set DB_DATABASE, DB_USERNAME, DB_PASSWORD
```

4. Run the deploy script:

```bash
chmod +x deploy/hostinger-deploy.sh
./deploy/hostinger-deploy.sh
```

## Cron (hPanel → Cron Jobs)

Use **PHP 8.2** CLI (not default 8.1):

```
* * * * * /opt/alt/php82/usr/bin/php /home/u319040066/domains/ahmadalhashaykeh.com/public_html/tenderai/artisan schedule:run >> /dev/null 2>&1
```

## Release deploy (commit f978629+)

After `git pull`, run the automated release script on the server:

```bash
cd /home/u319040066/domains/ahmadalhashaykeh.com/public_html/tenderai
bash deploy/hostinger-production-release.sh f978629
```

Or run the same steps manually (always `/opt/alt/php82/usr/bin/php` for artisan/composer).

## Document root

Ideal: point subdomain/folder to `.../tenderai/public`.

If the URL serves `.../tenderai` (not `public`), the repo root `.htaccess` forwards requests into `public/`. The deploy script sets `RewriteBase /tenderai/public/` in `public/.htaccess`.

## Manual steps (without script)

See the deployment checklist in your project handoff; the script automates clone/pull, composer, npm build, `.env`, artisan commands, and permissions.
