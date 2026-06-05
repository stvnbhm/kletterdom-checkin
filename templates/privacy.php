<?php
ob_start();
?>
<main class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12 prose">
    <h1>Datenschutzerklärung</h1>

    <p>Wir verarbeiten deine personenbezogenen Daten gemäß DSGVO ausschließlich für den
    Hallenbetrieb des Kletterdoms in Langenlois.</p>

    <h2>Welche Daten</h2>
    <ul>
        <li>Bei der Registrierung: Vorname, Nachname, Geburtsdatum, E-Mail, ggf. Adresse, Mitgliedsnummer.</li>
        <li>Bei Check-ins: Zeitpunkt und Status deines Besuchs.</li>
    </ul>

    <h2>Zweck</h2>
    <p>Die Daten dienen dem Hallendienst zur Identifikation, zur Prüfung von
    Mitgliedschaft und Schnupperlimits sowie zur Auswertung der Hallenauslastung.</p>

    <h2>Speicherdauer</h2>
    <p>Registrierungen ohne Check-in seit 2 Jahren werden automatisch entfernt.
    Inaktive Mitglieder werden auf Wunsch sofort gelöscht.</p>

    <h2>Rechte</h2>
    <p>Du hast jederzeit das Recht auf Auskunft, Berichtigung oder Löschung deiner
    Daten. Bitte wende dich an den Hallendienst oder das Vorstandsteam des ÖTK
    Langenlois.</p>
</main>
<?php
$body = ob_get_clean();
echo $view->render('layout', [
    'title'       => 'Datenschutz | Kletterdom',
    'body'        => $body,
    'authNavOnly' => true,
]);
