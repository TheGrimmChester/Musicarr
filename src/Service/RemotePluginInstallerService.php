<?php

declare(strict_types=1);

namespace App\Service;

use Exception;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class RemotePluginInstallerService
{
    private const PLUGINS_DIR = 'plugins';

    public function __construct(
        private Filesystem $filesystem
    ) {
    }

    /**
     * Install a plugin from a remote repository.
     *
     * @param string               $repositoryUrl The repository URL (HTTPS or SSH)
     * @param string               $pluginName    The name for the plugin directory
     * @param string|null          $branch        The branch to checkout (default: main)
     * @param OutputInterface|null $output        For progress feedback
     *
     * @return array{success: bool, message: string, pluginPath?: string, error?: string}
     */
    public function installPlugin(
        string $repositoryUrl,
        string $pluginName,
        ?string $branch = null,
        ?OutputInterface $output = null
    ): array {
        try {
            $output?->writeln("Starting installation of plugin: {$pluginName}");

            // Validate repository URL
            if (!$this->isValidRepositoryUrl($repositoryUrl)) {
                return [
                    'success' => false,
                    'error' => 'Invalid repository URL format',
                ];
            }

            // Prepare installation path
            $pluginPath = $this->getPluginPath($pluginName);

            // Check if plugin already exists
            if ($this->filesystem->exists($pluginPath)) {
                return [
                    'success' => false,
                    'error' => "Plugin directory '{$pluginName}' already exists",
                ];
            }

            // Clone the repository
            $output?->writeln('Cloning repository...');
            $success = $this->cloneRepository($repositoryUrl, $pluginPath, $branch, $output);

            if (!$success) {
                return [
                    'success' => false,
                    'error' => 'Failed to clone repository',
                ];
            }

            // Validate plugin structure
            if (!$this->validatePluginStructure($pluginPath)) {
                $this->cleanupFailedInstallation($pluginPath);

                return [
                    'success' => false,
                    'error' => 'Invalid plugin structure - missing required files',
                ];
            }

            // Install dependencies if composer.json exists
            if ($this->filesystem->exists($pluginPath . '/composer.json')) {
                $output?->writeln('Installing plugin dependencies...');
                $this->installComposerDependencies($pluginPath, $output);
            }

            $output?->writeln("Plugin '{$pluginName}' installed successfully!");

            return [
                'success' => true,
                'message' => "Plugin '{$pluginName}' installed successfully",
                'pluginPath' => $pluginPath,
            ];
        } catch (Exception $e) {
            $output?->writeln('Error during installation: ' . $e->getMessage());

            // Cleanup on failure
            if (isset($pluginPath) && $this->filesystem->exists($pluginPath)) {
                $this->cleanupFailedInstallation($pluginPath);
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Validate repository URL format.
     */
    private function isValidRepositoryUrl(string $url): bool
    {
        return false !== filter_var($url, \FILTER_VALIDATE_URL)
               || preg_match('/^git@github\.com:[^\/]+\/[^\/]+\.git$/', $url);
    }

    /**
     * Clone repository to plugin directory.
     */
    private function cloneRepository(
        string $repositoryUrl,
        string $pluginPath,
        ?string $branch,
        ?OutputInterface $output
    ): bool {
        $branch = $branch ?: 'main';

        $output?->writeln("Cloning from: {$repositoryUrl}");
        $output?->writeln("Target branch: {$branch}");

        $process = new Process([
            'git', 'clone',
            '--branch', $branch,
            '--single-branch',
            '--depth', '1',
            $repositoryUrl,
            $pluginPath,
        ]);

        $process->setTimeout(300); // 5 minutes timeout

        try {
            $process->run(function ($type, $buffer) use ($output) {
                if ($output) {
                    if (Process::ERR === $type) {
                        $output->writeln("<error>{$buffer}</error>");
                    } else {
                        $output->writeln($buffer);
                    }
                }
            });

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            return true;
        } catch (ProcessFailedException $e) {
            $output?->writeln('Git clone failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Validate plugin structure.
     */
    private function validatePluginStructure(string $pluginPath): bool
    {
        $requiredFiles = ['plugin.json'];

        foreach ($requiredFiles as $file) {
            if (!$this->filesystem->exists($pluginPath . '/' . $file)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Install Composer dependencies.
     */
    private function installComposerDependencies(string $pluginPath, ?OutputInterface $output): void
    {
        $process = new Process(['composer', 'install', '--no-dev', '--optimize-autoloader']);
        $process->setWorkingDirectory($pluginPath);
        $process->setTimeout(600); // 10 minutes timeout

        try {
            $process->run(function ($type, $buffer) use ($output) {
                if ($output) {
                    if (Process::ERR === $type) {
                        $output->writeln("<error>{$buffer}</error>");
                    } else {
                        $output->writeln($buffer);
                    }
                }
            });

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }
        } catch (ProcessFailedException $e) {
            $output?->writeln('Composer install failed: ' . $e->getMessage());

            throw $e;
        }
    }

    /**
     * Change branch/tag/release for an existing remote plugin.
     *
     * @param string               $pluginName    The name of the plugin
     * @param string               $reference     The branch, tag, or commit hash to checkout
     * @param string               $referenceType The type of reference: 'branch', 'tag', or 'commit'
     * @param OutputInterface|null $output        For progress feedback
     *
     * @return array{success: bool, message: string, error?: string}
     */
    public function changePluginReference(
        string $pluginName,
        string $reference,
        string $referenceType = 'branch',
        ?OutputInterface $output = null
    ): array {
        try {
            $output?->writeln("Changing {$referenceType} for plugin: {$pluginName}");

            $pluginPath = $this->getPluginPath($pluginName);

            if (!$this->filesystem->exists($pluginPath)) {
                return [
                    'success' => false,
                    'error' => "Plugin '{$pluginName}' not found",
                ];
            }

            if (!$this->isGitRepository($pluginPath)) {
                return [
                    'success' => false,
                    'error' => "Plugin '{$pluginName}' is not a git repository",
                ];
            }

            // Fetch latest changes
            $output?->writeln('Fetching latest changes...');
            if (!$this->fetchLatestChanges($pluginPath, $output)) {
                return [
                    'success' => false,
                    'error' => 'Failed to fetch latest changes',
                ];
            }

            // Checkout the new reference
            $output?->writeln("Checking out {$referenceType}: {$reference}");
            if (!$this->checkoutReference($pluginPath, $reference, $referenceType, $output)) {
                return [
                    'success' => false,
                    'error' => "Failed to checkout {$referenceType} '{$reference}'",
                ];
            }

            // Install/update dependencies if composer.json exists
            if ($this->filesystem->exists($pluginPath . '/composer.json')) {
                $output?->writeln('Updating plugin dependencies...');
                $this->installComposerDependencies($pluginPath, $output);
            }

            $output?->writeln("Successfully changed {$referenceType} to '{$reference}' for plugin '{$pluginName}'");

            return [
                'success' => true,
                'message' => "Successfully changed {$referenceType} to '{$reference}' for plugin '{$pluginName}'",
            ];
        } catch (Exception $e) {
            $output?->writeln("Error changing {$referenceType}: " . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get available branches for a plugin.
     *
     * @param string $pluginName The name of the plugin
     *
     * @return array{success: bool, branches?: array, error?: string}
     */
    public function getAvailableBranches(string $pluginName): array
    {
        try {
            $pluginPath = $this->getPluginPath($pluginName);

            if (!$this->filesystem->exists($pluginPath)) {
                return [
                    'success' => false,
                    'error' => "Plugin '{$pluginName}' not found",
                ];
            }

            if (!$this->isGitRepository($pluginPath)) {
                return [
                    'success' => false,
                    'error' => "Plugin '{$pluginName}' is not a git repository",
                ];
            }

            // Fetch latest changes first
            if (!$this->fetchLatestChanges($pluginPath)) {
                return [
                    'success' => false,
                    'error' => 'Failed to fetch latest changes',
                ];
            }

            // Get local branches
            $localBranches = $this->getLocalBranches($pluginPath);

            // Get remote branches
            $remoteBranches = $this->getRemoteBranches($pluginPath);

            // Get tags
            $tags = $this->getTags($pluginPath);

            // Get current branch
            $currentBranch = $this->getCurrentBranch($pluginPath);

            return [
                'success' => true,
                'branches' => [
                    'local' => $localBranches,
                    'remote' => $remoteBranches,
                    'tags' => $tags,
                    'current' => $currentBranch,
                ],
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if a directory is a git repository.
     */
    private function isGitRepository(string $path): bool
    {
        return $this->filesystem->exists($path . '/.git');
    }

    /**
     * Fetch latest changes from remote.
     */
    private function fetchLatestChanges(string $pluginPath, ?OutputInterface $output = null): bool
    {
        $process = new Process(['git', 'fetch', '--all', '--tags']);
        $process->setWorkingDirectory($pluginPath);
        $process->setTimeout(300); // 5 minutes timeout

        try {
            $process->run(function ($type, $buffer) use ($output) {
                if ($output) {
                    if (Process::ERR === $type) {
                        $output->writeln("<error>{$buffer}</error>");
                    } else {
                        $output->writeln($buffer);
                    }
                }
            });

            return $process->isSuccessful();
        } catch (Exception $e) {
            $output?->writeln('Git fetch failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Checkout a specific reference (branch, tag, or commit).
     */
    private function checkoutReference(
        string $pluginPath,
        string $reference,
        string $_referenceType,
        ?OutputInterface $output = null
    ): bool {
        $process = new Process(['git', 'checkout', $reference]);
        $process->setWorkingDirectory($pluginPath);
        $process->setTimeout(300); // 5 minutes timeout

        try {
            $process->run(function ($type, $buffer) use ($output) {
                if ($output) {
                    if (Process::ERR === $type) {
                        $output->writeln("<error>{$buffer}</error>");
                    } else {
                        $output->writeln($buffer);
                    }
                }
            });

            return $process->isSuccessful();
        } catch (Exception $e) {
            $output?->writeln('Git checkout failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Get local branches.
     */
    private function getLocalBranches(string $pluginPath): array
    {
        $process = new Process(['git', 'branch', '--format=%(refname:short)']);
        $process->setWorkingDirectory($pluginPath);

        try {
            $process->run();
            if ($process->isSuccessful()) {
                $output = mb_trim($process->getOutput());

                return $output ? explode("\n", $output) : [];
            }
        } catch (Exception $e) {
            // Ignore errors
        }

        return [];
    }

    /**
     * Get remote branches.
     */
    private function getRemoteBranches(string $pluginPath): array
    {
        $process = new Process(['git', 'branch', '-r', '--format=%(refname:short)']);
        $process->setWorkingDirectory($pluginPath);

        try {
            $process->run();
            if ($process->isSuccessful()) {
                $output = mb_trim($process->getOutput());
                if ($output) {
                    $branches = explode("\n", $output);

                    // Remove 'origin/' prefix and filter out HEAD
                    return array_filter(array_map(function ($branch) {
                        return str_replace('origin/', '', $branch);
                    }, $branches), function ($branch) {
                        return 'HEAD' !== $branch;
                    });
                }
            }
        } catch (Exception $e) {
            // Ignore errors
        }

        return [];
    }

    /**
     * Get tags.
     */
    private function getTags(string $pluginPath): array
    {
        $process = new Process(['git', 'tag', '--sort=-version:refname']);
        $process->setWorkingDirectory($pluginPath);

        try {
            $process->run();
            if ($process->isSuccessful()) {
                $output = mb_trim($process->getOutput());

                return $output ? explode("\n", $output) : [];
            }
        } catch (Exception $e) {
            // Ignore errors
        }

        return [];
    }

    /**
     * Get current branch.
     */
    private function getCurrentBranch(string $pluginPath): ?string
    {
        $process = new Process(['git', 'branch', '--show-current']);
        $process->setWorkingDirectory($pluginPath);

        try {
            $process->run();
            if ($process->isSuccessful()) {
                $output = mb_trim($process->getOutput());

                return $output ?: null;
            }
        } catch (Exception $e) {
            // Ignore errors
        }

        return null;
    }

    /**
     * Get plugin installation path.
     */
    private function getPluginPath(string $pluginName): string
    {
        return self::PLUGINS_DIR . '/' . $pluginName;
    }

    /**
     * Cleanup failed installation.
     */
    private function cleanupFailedInstallation(string $pluginPath): void
    {
        try {
            $this->filesystem->remove($pluginPath);
        } catch (Exception $e) {
            // Log cleanup failure but don't throw
        }
    }

    /**
     * Update an existing plugin.
     */
    public function updatePlugin(
        string $pluginName,
        ?string $branch = null,
        ?OutputInterface $output = null
    ): array {
        $pluginPath = $this->getPluginPath($pluginName);

        if (!$this->filesystem->exists($pluginPath)) {
            return [
                'success' => false,
                'error' => "Plugin '{$pluginName}' not found",
            ];
        }

        $output?->writeln("Updating plugin: {$pluginName}");

        try {
            $process = new Process(['git', 'fetch', 'origin']);
            $process->setWorkingDirectory($pluginPath);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $branch = $branch ?: 'main';
            $process = new Process(['git', 'checkout', $branch]);
            $process->setWorkingDirectory($pluginPath);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $process = new Process(['git', 'pull', 'origin', $branch]);
            $process->setWorkingDirectory($pluginPath);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            // Update composer dependencies if needed
            if ($this->filesystem->exists($pluginPath . '/composer.json')) {
                $output?->writeln('Updating plugin dependencies...');
                $this->installComposerDependencies($pluginPath, $output);
            }

            return [
                'success' => true,
                'message' => "Plugin '{$pluginName}' updated successfully",
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Remove a plugin.
     */
    public function removePlugin(string $pluginName): array
    {
        $pluginPath = $this->getPluginPath($pluginName);

        if (!$this->filesystem->exists($pluginPath)) {
            return [
                'success' => false,
                'error' => "Plugin '{$pluginName}' not found",
            ];
        }

        try {
            $this->filesystem->remove($pluginPath);

            return [
                'success' => true,
                'message' => "Plugin '{$pluginName}' removed successfully",
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
