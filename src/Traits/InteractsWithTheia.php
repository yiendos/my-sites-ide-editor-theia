<?php

namespace Yiendos\MySitesIde\Editor\Theia\Traits;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Drives the theia compose service from the host. Every `docker compose`
 * call runs from the IDE root, as the my-sites-ide CLI is run there.
 */
trait InteractsWithTheia
{
    /**
     * Whether the theia container is up
     *
     * @return bool
     */
    protected function theiaRunning(): bool
    {
        return trim((string) shell_exec('docker compose ps -q --status running theia 2>/dev/null')) !== '';
    }

    /**
     * The web UI, as reached from the host
     *
     * @return string
     */
    protected function theiaUrl(): string
    {
        return 'http://localhost:' . (getenv('THEIA_PORT') ?: '3001');
    }

    /**
     * Runs `docker compose <arguments>`, echoing it first like the core commands do
     *
     * @param OutputInterface $output
     * @param string $arguments
     * @return int the exit code
     */
    protected function compose(OutputInterface $output, string $arguments): int
    {
        $output->writeLn("docker compose {$arguments}");
        passthru("docker compose {$arguments}", $code);

        return $code;
    }
}
