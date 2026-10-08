<?php

namespace Yiendos\MySitesIde\Editor\Theia\Console;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Editor\Theia\Paths;
use Yiendos\MySitesIde\Editor\Theia\Traits\InteractsWithTheia;
use ZipArchive;

class TheiaExtensionsCommand extends Command
{
    use InteractsWithTheia;

    private const OPEN_VSX = 'https://open-vsx.org/api';

    /**
     * The ability to configure the console command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('editor:theia-extensions')
            ->setDescription("Install Theia's extensions from Open VSX - picked up on Theia's next start")
            ->addOption('force', null, InputOption::VALUE_NONE, 'Install every extension again, even ones already there')
        ;
    }

    /**
     * Downloads each pinned .vsix in the list from Open VSX and unpacks it into
     * storage/plugins/theia/config/plugins/<namespace>.<name>/, a folder Theia
     * loads unpacked extensions from when it starts. It all happens on the host,
     * so the image never needs rebuilding for an extension.
     *
     * The folder belongs to this command: a changed version replaces the
     * extension, and one taken off the list is removed. Extensions installed
     * from Theia's Extensions panel live elsewhere and are left alone.
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        if (!class_exists(ZipArchive::class)) {
            $io->error("PHP's zip extension is needed to unpack extensions - install or enable it for the PHP that runs my-sites-ide.");
            return Command::FAILURE;
        }

        $list = $this->listFile();
        $extensions = $this->parse($list, $io);

        if ($extensions === null) {
            return Command::FAILURE;
        }

        $directory = Paths::extensions();
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $io->writeln('Extensions from ' . Paths::relative($list) . ' into ' . Paths::relative($directory) . '/');

        $changed = 0;
        $failed = 0;

        foreach ($extensions as [$namespace, $name, $version]) {
            $id = "{$namespace}.{$name}";

            if ($this->installedVersion("{$directory}/{$id}") === $version && !$input->getOption('force')) {
                $io->writeln("  {$id}@{$version} - already installed");
                continue;
            }

            if (!$this->install("{$namespace}/{$name}/{$version}/file/{$id}-{$version}.vsix", "{$directory}/{$id}")) {
                $io->writeln("  <error>{$id}@{$version} - download failed</error>");
                $failed++;
                continue;
            }

            $io->writeln("  {$id}@{$version} - installed");
            $changed++;
        }

        $changed += $this->removeUnlisted($directory, array_map(fn (array $extension): string => "{$extension[0]}.{$extension[1]}", $extensions), $io);

        if ($failed > 0) {
            $io->error("{$failed} extension(s) did not install - check the names and versions on https://open-vsx.org");
        }

        if ($changed === 0) {
            return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
        }

        if ($this->theiaRunning()) {
            $this->compose($output, 'restart theia');
            $io->success('Extensions updated - Theia restarted to load them, reload the browser tab.');
        } else {
            $io->success('Extensions updated - Theia loads them when it next starts.');
        }

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * The user's own list in the plugin's storage when there is one, otherwise the default
     *
     * @return string
     */
    private function listFile(): string
    {
        $own = Paths::storage('extensions.txt');

        return is_file($own) ? $own : Paths::package('extensions.txt');
    }

    /**
     * Reads <namespace>.<name>@<version> lines, skipping blanks and # comments
     *
     * @param string $list
     * @param SymfonyStyle $io
     * @return array<int, array{string, string, string}>|null null when a line can't be read
     */
    private function parse(string $list, SymfonyStyle $io): ?array
    {
        $extensions = [];

        foreach (file($list, FILE_IGNORE_NEW_LINES) ?: [] as $number => $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!preg_match('/^([\w-]+)\.([\w.-]+)@([\w.+-]+)$/', $line, $matches)) {
                $io->error(Paths::relative($list) . ':' . ($number + 1) . " - expected <namespace>.<name>@<version>, got \"{$line}\"");
                return null;
            }

            $extensions[] = [$matches[1], $matches[2], $matches[3]];
        }

        return $extensions;
    }

    /**
     * The version of an unpacked extension, from its package.json
     *
     * @param string $folder
     * @return string|null null when it isn't installed
     */
    private function installedVersion(string $folder): ?string
    {
        $package = json_decode((string) @file_get_contents("{$folder}/extension/package.json"), true);

        return is_array($package) && is_string($package['version'] ?? null) ? $package['version'] : null;
    }

    /**
     * Downloads a .vsix from Open VSX and unpacks it in place of whatever
     * version was there - only once the download is complete
     *
     * @param string $path
     * @param string $folder
     * @return bool
     */
    private function install(string $path, string $folder): bool
    {
        $context = stream_context_create(['http' => ['timeout' => 60, 'ignore_errors' => false]]);
        $body = @file_get_contents(self::OPEN_VSX . "/{$path}", false, $context);

        if ($body === false || $body === '') {
            return false;
        }

        $vsix = tempnam(sys_get_temp_dir(), 'vsix');
        file_put_contents($vsix, $body);

        $zip = new ZipArchive();
        if ($zip->open($vsix) !== true) {
            unlink($vsix);
            return false;
        }

        $this->remove($folder);
        $unpacked = $zip->extractTo($folder);
        $zip->close();
        unlink($vsix);

        return $unpacked;
    }

    /**
     * Removes the extensions that are no longer in the list
     *
     * @param string $directory
     * @param array<int, string> $listed
     * @param SymfonyStyle $io
     * @return int how many were removed
     */
    private function removeUnlisted(string $directory, array $listed, SymfonyStyle $io): int
    {
        $removed = 0;

        foreach (glob("{$directory}/*", GLOB_ONLYDIR) ?: [] as $folder) {
            if (!in_array(basename($folder), $listed, true)) {
                $this->remove($folder);
                $io->writeln('  ' . basename($folder) . ' - removed, no longer in the list');
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Deletes a folder and everything in it
     *
     * @param string $folder
     * @return void
     */
    private function remove(string $folder): void
    {
        if (!is_dir($folder)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($folder);
    }
}
