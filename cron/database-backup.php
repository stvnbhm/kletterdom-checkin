#!/usr/bin/env php
<?php
/**
 * Cron-Einstieg: MySQL-Backup nach backups/.
 * Beispiel (Host-crontab, täglich 03:00):
 *   0 3 * * * cd /opt/kletterdom-checkin && docker compose exec -T app php cron/database-backup.php
 */
declare(strict_types=1);

require dirname(__DIR__) . '/bin/backup-db';
