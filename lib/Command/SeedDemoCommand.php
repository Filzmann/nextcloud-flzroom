<?php

declare(strict_types=1);

namespace OCA\FlzRoom\Command;

use OCA\FlzRoom\Service\RoomDemoPackService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Zweck: Stellt den sicheren Raum-Demo-Pack für automatisierte Staging-Setups bereit. */
final class SeedDemoCommand extends Command {
    public function __construct(private RoomDemoPackService $demoPack) { parent::__construct(); }
    protected function configure(): void { $this->setName('flzroom:demo:seed')->setDescription('Erzeugt synthetische Raumplaner-Demodaten.'); }
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $result = $this->demoPack->install();
        $output->writeln("<info>{$result['rooms']} Räume synchronisiert; {$result['createdBookings']} Beispielbuchungen erzeugt.</info>");
        return self::SUCCESS;
    }
}
