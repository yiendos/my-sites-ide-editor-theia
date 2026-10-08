<?php

namespace Yiendos\MySitesIde\Editor\Theia\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Editor\Theia\Paths;

class TheiaWorkspaceCommand extends Command
{
    /**
     * The placeholder in stubs/workspace.theia-workspace replaced with the site name
     */
    private const PLACEHOLDER = '__PROJECT__';

    /**
     * The placeholder replaced with the site's application code, relative to
     * Repos/ - Paths::siteApp(), e.g. example/deploy
     */
    private const APP_PLACEHOLDER = '__APP_PATH__';

    /**
     * The ability to configure the console command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('editor:theia-workspace')
            ->setDescription("Create a site's Theia workspace (Repos/<site>/<site>.theia-workspace) - the site and Packages, Intelephense settings and Xdebug")
            ->addArgument('site', InputArgument::REQUIRED, 'The site folder under Repos/, e.g. example')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Write the workspace even if the site already has one')
        ;
    }

    /**
     * Run by ide:create-site and ide:repo-clone through the plugin's
     * site-created hook, or by hand for a site that predates the plugin.
     *
     * The workspace opens the site next to Packages/, points Intelephense at
     * the site's vendor/ and IDE helper files, and has an Xdebug launch
     * configuration mapping the php containers' /opt/repos/<site> and
     * /opt/Packages onto those folders. Open it in Theia with
     * File > Open Workspace.
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        $site = $input->getArgument('site');

        if (!is_dir(Paths::workspace($site))) {
            $io->error("Repos/{$site} does not exist - create or clone the site first.");
            return Command::FAILURE;
        }

        $workspace = Paths::workspace("{$site}/{$site}.theia-workspace");

        if (is_file($workspace) && !$input->getOption('force')) {
            $io->warning('Theia workspace already present, leaving it alone (--force to write the sample anyway): ' . Paths::relative($workspace));
            return Command::SUCCESS;
        }

        $sample = (string) file_get_contents(Paths::package('stubs/workspace.theia-workspace'));

        file_put_contents($workspace, strtr($sample, [self::APP_PLACEHOLDER => Paths::siteApp($site), self::PLACEHOLDER => $site]));

        $io->writeln('<info>Created ' . Paths::relative($workspace) . "</> - open it in Theia with File > Open Workspace (/home/project/{$site}/{$site}.theia-workspace).");

        return Command::SUCCESS;
    }
}
