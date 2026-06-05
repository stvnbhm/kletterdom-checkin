<?php
/** @var array<string,int> $stats */
?>
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center">
        <div class="text-3xl font-bold text-teal-600"><?= (int) ($stats['checkedInToday'] ?? 0) ?></div>
        <div class="text-sm text-gray-500 mt-1">Heute eingecheckt</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center">
        <div class="text-3xl font-bold text-blue-500"><?= (int) ($stats['guestsToday'] ?? 0) ?></div>
        <div class="text-sm text-gray-500 mt-1">Davon Gäste</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center">
        <div class="text-3xl font-bold text-indigo-600"><?= (int) ($stats['membersToday'] ?? 0) ?></div>
        <div class="text-sm text-gray-500 mt-1">Davon Mitglieder</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center">
        <div class="text-3xl font-bold text-gray-700"><?= (int) ($stats['totalRegistrations'] ?? 0) ?></div>
        <div class="text-sm text-gray-500 mt-1">Registrierungen gesamt</div>
    </div>
</div>
