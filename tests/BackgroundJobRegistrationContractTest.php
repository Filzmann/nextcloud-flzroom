<?php

declare(strict_types=1);

$appRoot = dirname(__DIR__);
$jobClass = 'OCA\\FlzRoom\\BackgroundJob\\InterventionNotificationJob';

$infoXml = file_get_contents($appRoot . '/appinfo/info.xml');
if ($infoXml === false)
{
    throw new RuntimeException('appinfo/info.xml konnte nicht gelesen werden.');
}
$info = simplexml_load_string($infoXml);
if ($info === false)
{
    throw new RuntimeException('appinfo/info.xml ist kein gültiges XML.');
}

// The native install/update path reads this declaration even after migrations ran.
$declared = [];
foreach ($info->{'background-jobs'}->job ?? [] as $job)
{
    $declared[] = trim((string)$job);
}
if (!in_array($jobClass, $declared, true))
{
    throw new RuntimeException('InterventionNotificationJob ist nicht in info.xml <background-jobs> registriert.');
}

// Negativ: Jede deklarierte Job-Klasse muss tatsächlich existieren, damit ein
// Tippfehler nicht zu einem stillen Registrierungsfehler führt.
foreach ($declared as $class)
{
    $relative = str_replace('\\', '/', preg_replace('/^OCA\\\\FlzRoom\\\\/', '', $class));
    if (!is_file($appRoot . '/lib/' . $relative . '.php'))
    {
        throw new RuntimeException("Deklarierte Job-Klasse {$class} hat keine Klassendatei unter lib/.");
    }
}

echo "Background job registration contract test passed\n";
