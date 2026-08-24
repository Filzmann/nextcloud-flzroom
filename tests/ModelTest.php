<?php

declare(strict_types=1);

use OCA\AdRoom\Model\Booking;
use OCA\AdRoom\Model\Room;

$room=Room::get(['id'=>'2','name'=>' Konferenzraum ','description'=>' Gross ','sortOrder'=>'30']);
if ($room?->toArray()!==['id'=>2,'name'=>'Konferenzraum','description'=>'Gross','sortOrder'=>30]) throw new RuntimeException('Room-Hydration ist fehlerhaft.');
$booking=Booking::get(['id'=>1,'roomId'=>2,'userUid'=>'admin','userName'=>'Admin','purpose'=>'Sitzung','title'=>'Büroteam','startsAt'=>'2026-07-13T08:00:00+00:00','endsAt'=>'2026-07-13T09:00:00+00:00']);
if ($booking?->roomId()!==2 || $booking->toArray()['purpose']!=='Sitzung' || $booking->title()!=='Büroteam') throw new RuntimeException('Booking-Hydration ist fehlerhaft.');
try { $booking->save(); throw new RuntimeException('Nicht persistierbares Modell darf save nicht erlauben.'); } catch (LogicException) {}
echo "AD Raumplaner model tests passed\n";
