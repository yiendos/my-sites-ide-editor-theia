<?php

namespace Yiendos\MySitesIde\Editor\Theia\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Editor\Theia\Paths;
use Yiendos\MySitesIde\Editor\Theia\Traits\InteractsWithTheia;

class TheiaStartCommand extends Command
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
            ->setName('editor:theia-start')
            ->setDescription('Start the Theia container - or recreate it if its compose config changed')
        ;
    }

    /**
     * Always runs `up -d --build`, even when theia is already running: it's a
     * no-op for an up-to-date container, and recreates one whose compose
     * config has changed - e.g. a new THEIA_PORT, or a container created
     * before theia became a plugin. Settings and extensions live in
     * storage/plugins/theia/, so recreating loses nothing.
     *
     * --build because ${NAMESPACE}_theia is also the name the IDE's built-in
     * Theia gave its image: plain `up` would run that old image rather than
     * build this one, and it picks up Dockerfile changes after an update.
     * An unchanged Dockerfile builds from cache in seconds.
     *
     * The first start also installs the default extensions. Xdebug's launch
     * configuration comes with each site's workspace (editor:theia-workspace).
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        foreach (Paths::STORAGE_FOLDERS as $folder) {
            if (!is_dir(Paths::storage($folder))) {
                mkdir(Paths::storage($folder), 0755, true);
            }
        }

        if (!is_dir(Paths::extensions())) {
            $io->writeln('First start - installing the default extensions');
            $this->getApplication()?->find('editor:theia-extensions')->run(new ArrayInput([]), $output);
        }

        if ($this->compose($output, 'up -d --build theia') !== 0) {
            $io->error('Theia did not start - see above.');
            return Command::FAILURE;
        }

        $io->success('Theia started - ' . $this->theiaUrl());

        if (getenv('XDEBUG_CLIENT_HOST') !== 'theia') {
            $io->text([
                'To debug PHP in Theia, point Xdebug at it in the IDE\'s .env, then restart the IDE:',
                '',
                '    XDEBUG_CLIENT_HOST=theia',
                '',
            ]);
        }

        return Command::SUCCESS;
    }
}
