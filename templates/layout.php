<?php
/**
 * @var \Kletterdom\Http\View      $view
 * @var \Kletterdom\Auth\Auth      $auth
 * @var \Kletterdom\Http\Csrf      $csrf
 * @var \Kletterdom\Http\Flash     $flash
 * @var string                     $title
 * @var string                     $body
 * @var bool                       $hideNav    Standard: false
 * @var bool                       $authNavOnly Wenn true: Nav nur sichtbar wenn eingeloggt
 */
$hideNav     = $hideNav     ?? false;
$authNavOnly = $authNavOnly ?? false;
$showNav     = ! $hideNav && (! $authNavOnly || $auth->check());
$success     = $flash->peek('success');
$error       = $flash->peek('error');
$warning     = $flash->peek('warning');
$errors      = $flash->errors();
?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf->token(), ENT_QUOTES) ?>">
    <title><?= htmlspecialchars($title ?? 'Kletterdom Check-in', ENT_QUOTES) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="font-sans antialiased bg-gray-50 text-gray-900">

<?php if ($showNav): ?>
    <?php $view->partial('nav'); ?>
<?php endif; ?>

<?php if ($success !== null): ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
        <div class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
            <?= htmlspecialchars((string) $success, ENT_QUOTES) ?>
        </div>
    </div>
<?php endif; ?>
<?php if ($error !== null): ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
        <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
            <?= htmlspecialchars((string) $error, ENT_QUOTES) ?>
        </div>
    </div>
<?php endif; ?>
<?php if ($warning !== null): ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
        <div class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
            <?= htmlspecialchars((string) $warning, ENT_QUOTES) ?>
        </div>
    </div>
<?php endif; ?>
<?php if ($errors !== []): ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
        <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc pl-5 m-0">
                <?php foreach ($errors as $msg): ?>
                    <li class="my-1"><?= htmlspecialchars((string) $msg, ENT_QUOTES) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<?= $body ?>

<?php $flash->clear(); ?>
</body>
</html>
