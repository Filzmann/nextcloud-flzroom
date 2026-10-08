<?php

declare(strict_types=1);

foreach (['RoomRepository.php', 'BookingRepository.php'] as $file) {
    $source = file_get_contents(__DIR__ . '/../lib/Repository/' . $file);
    if ($source === false) throw new RuntimeException("Repository konnte nicht gelesen werden: {$file}");
    if (!str_contains($source, '$qb->getLastInsertId()') || str_contains($source, 'db->lastInsertId')) {
        throw new RuntimeException("Moderner QueryBuilder-ID-Vertrag fehlt: {$file}");
    }
}

echo "Filzmann Raumplaner repository contract test passed\n";
