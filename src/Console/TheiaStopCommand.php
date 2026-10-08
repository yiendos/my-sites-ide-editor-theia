<?php

namespace Yiendos\MySitesIde\Editor\Theia\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Editor\Theia\Traits\InteractsWithTheia;

class TheiaStopCommand extends Command
{
    use InteractsWithTheia;

    /**
     * The ability to configure the console command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('editor:theia-stop')
            ->setDescription('Stop the Theia container, leaving the rest of the IDE running')
        ;
    }

    /**
     * Stopped rather than removed, so editor:theia-start brings back the
     * same container. Settings and extensions are kept in storage/plugins/theia/
     * either way.
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        if (!$this->theiaRunning()) {
            $io->writeln('Theia is not running - nothing to stop.');
            return Command::SUCCESS;
        }

        if ($this->compose($output, 'stop theia') !== 0) {
            $io->error('Theia did not stop - see above.');
            return Command::FAILURE;
        }

        $io->success('Theia stopped.');

        return Command::SUCCESS;
    }
}
