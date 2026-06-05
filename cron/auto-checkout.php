#!/usr/bin/env php
<?php
/**
 * Cron-Einstieg: schließt offene Check-ins nach 3 Stunden.
 * Beispiel (Host-crontab, alle 15 Minuten):
 *   0,15,30,45 * * * * cd /opt/kletterdom-checkin && docker compose exec -T app php cron/auto-checkout.php
 */
declare(strict_types=1);

require dirname(__DIR__) . '/bin/auto-checkout';
