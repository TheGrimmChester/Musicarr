<?php

declare(strict_types=1);

namespace App\Controller;

use App\Plugin\PluginManager;
use App\Service\PluginInfoService;
use App\Service\PluginStatusManager;
use App\Service\RemotePluginInstallerService;
use App\Task\TaskFactory;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/plugins')]
class PluginController extends AbstractController
{
    public function __construct(
        private PluginManager $pluginManager,
        private PluginInfoService $pluginInfoService,
        private PluginStatusManager $pluginStatusManager,
        private TaskFactory $taskFactory,
        private RemotePluginInstallerService $remotePluginInstaller,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: 'admin_plugins_index', methods: ['GET'])]
    public function index(): Response
    {
        $plugins = $this->pluginManager->getPlugins();
        $pluginInfo = $this->pluginInfoService->getPluginInfoForDisplay($plugins);

        return $this->render('admin/plugins/index.html.twig', [
            'plugins' => $pluginInfo,
            'pluginInfoService' => $this->pluginInfoService,
        ]);
    }

    #[Route('/{name}/install', name: 'admin_plugin_install', methods: ['POST'])]
    public function install(string $name): JsonResponse
    {
        try {
            $plugin = $this->pluginManager->getPlugin($name);

            // If plugin not found locally, check if it's a remote plugin
            if (!$plugin) {
                $remotePlugin = $this->pluginInfoService->getRemotePlugins()[$name] ?? null;
                if ($remotePlugin) {
                    // This is a remote plugin - install it from repository
                    $repositoryUrl = $remotePlugin['repository_url'];
                    $branch = 'main'; // Default branch

                    $task = $this->taskFactory->createRemotePluginInstallTask(
                        $repositoryUrl,
                        $name,
                        $branch
                    );

                    // Persist and flush the task
                    $this->entityManager->persist($task);
                    $this->entityManager->flush();

                    return $this->json([
                        'success' => true,
                        'message' => "Remote plugin installation task created for '{$name}'",
                        'taskId' => $task->getId(),
                        'status' => 'task_created',
                        'repository_url' => $repositoryUrl,
                        'plugin_name' => $name,
                        'branch' => $branch,
                    ]);
                }

                return $this->json(['error' => "Plugin '{$name}' not found"], 404);
            }

            $bundleClass = $plugin['bundle_class'] ?? null;
            if (!$bundleClass) {
                return $this->json(['error' => "Plugin '{$name}' has no bundle class defined"], 400);
            }

            // Create installation task for local plugin
            $task = $this->taskFactory->createPluginInstallTask($name, [], 5);

            // Persist and flush the task
            $this->entityManager->persist($task);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => "Plugin installation task created for '{$name}'",
                'taskId' => $task->getId(),
                'status' => 'task_created',
            ]);
        } catch (Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/{name}/uninstall', name: 'admin_plugin_uninstall', methods: ['POST'])]
    public function uninstall(string $name): JsonResponse
    {
        try {
            $plugin = $this->pluginManager->getPlugin($name);
            if (!$plugin) {
                return $this->json(['error' => "Plugin '{$name}' not found"], 404);
            }

            $bundleClass = $plugin['bundle_class'] ?? null;
            if (!$bundleClass) {
                return $this->json(['error' => "Plugin '{$name}' has no bundle class defined"], 400);
            }

            // Create uninstallation task
            $task = $this->taskFactory->createPluginUninstallTask($name, [], 5);

            // Persist and flush the task
            $this->entityManager->persist($task);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => "Plugin uninstallation task created for '{$name}'",
                'taskId' => $task->getId(),
                'status' => 'task_created',
            ]);
        } catch (Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/{name}/enable', name: 'admin_plugin_enable', methods: ['POST'])]
    public function enable(string $name): JsonResponse
    {
        try {
            $plugin = $this->pluginManager->getPlugin($name);
            if (!$plugin) {
                return $this->json(['error' => "Plugin '{$name}' not found"], 404);
            }

            $bundleClass = $plugin['bundle_class'] ?? null;
            if (!$bundleClass) {
                return $this->json(['error' => "Plugin '{$name}' has no bundle class defined"], 400);
            }

            // Create enable task
            $task = $this->taskFactory->createPluginEnableTask($name, [], 5);

            // Persist and flush the task
            $this->entityManager->persist($task);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => "Plugin enable task created for '{$name}'",
                'taskId' => $task->getId(),
                'status' => 'task_created',
            ]);
        } catch (Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/{name}/disable', name: 'admin_plugin_disable', methods: ['POST'])]
    public function disable(string $name): JsonResponse
    {
        try {
            $plugin = $this->pluginManager->getPlugin($name);
            if (!$plugin) {
                return $this->json(['error' => "Plugin '{$name}' not found"], 404);
            }

            $bundleClass = $plugin['bundle_class'] ?? null;
            if (!$bundleClass) {
                return $this->json(['error' => "Plugin '{$name}' has no bundle class defined"], 400);
            }

            // Create disable task
            $task = $this->taskFactory->createPluginDisableTask($name, [], 5);

            // Persist and flush the task
            $this->entityManager->persist($task);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => "Plugin disable task created for '{$name}'",
                'taskId' => $task->getId(),
                'status' => 'task_created',
            ]);
        } catch (Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/{name}/upgrade', name: 'admin_plugin_upgrade', methods: ['POST'])]
    public function upgrade(string $name, Request $request): JsonResponse
    {
        try {
            $plugin = $this->pluginManager->getPlugin($name);
            if (!$plugin) {
                return $this->json(['error' => "Plugin '{$name}' not found"], 404);
            }

            $bundleClass = $plugin['bundle_class'] ?? null;
            if (!$bundleClass) {
                return $this->json(['error' => "Plugin '{$name}' has no bundle class defined"], 400);
            }

            $data = json_decode($request->getContent(), true) ?? [];
            $targetVersion = $data['target_version'] ?? null;

            // Create upgrade task
            $task = $this->taskFactory->createPluginUpgradeTask($name, $targetVersion, [], 5);

            // Persist and flush the task
            $this->entityManager->persist($task);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => "Plugin upgrade task created for '{$name}'",
                'taskId' => $task->getId(),
                'status' => 'task_created',
                'targetVersion' => $targetVersion,
            ]);
        } catch (Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/remote/install', name: 'admin_plugins_remote_install', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function installRemotePlugin(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $repositoryUrl = $data['repository_url'] ?? null;
        $pluginName = $data['plugin_name'] ?? null;
        $branch = $data['branch'] ?? 'main';

        if (!$repositoryUrl || !$pluginName) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Repository URL and plugin name are required',
            ], 400);
        }

        $task = $this->taskFactory->createRemotePluginInstallTask(
            $repositoryUrl,
            $pluginName,
            $branch
        );

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Remote plugin installation task created',
            'task_id' => $task->getId(),
        ]);
    }

    #[Route('/{name}/branches', name: 'admin_plugins_get_branches', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getPluginBranches(string $name): JsonResponse
    {
        $result = $this->remotePluginInstaller->getAvailableBranches($name);

        if (!$result['success']) {
            return new JsonResponse([
                'success' => false,
                'error' => $result['error'],
            ], 400);
        }

        return new JsonResponse([
            'success' => true,
            'branches' => $result['branches'],
        ]);
    }

    #[Route('/{name}/change-reference', name: 'admin_plugins_change_reference', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function changePluginReference(string $name, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $reference = $data['reference'] ?? null;
        $referenceType = $data['reference_type'] ?? 'branch';

        if (!$reference) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Reference is required',
            ], 400);
        }

        $task = $this->taskFactory->createPluginReferenceChangeTask(
            $name,
            $reference,
            $referenceType
        );

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Plugin reference change task created',
            'task_id' => $task->getId(),
        ]);
    }

    #[Route('/{name}/status', name: 'admin_plugin_status', methods: ['GET'])]
    public function status(string $name): JsonResponse
    {
        try {
            $plugin = $this->pluginManager->getPlugin($name);
            if (!$plugin) {
                return $this->json(['error' => "Plugin '{$name}' not found"], 404);
            }

            $bundleClass = $plugin['bundle_class'] ?? null;
            $installed = $bundleClass ? $this->pluginStatusManager->isPluginEnabled($bundleClass) : false;

            return $this->json([
                'name' => $name,
                'installed' => $installed,
                'enabled' => $installed,
                'version' => $plugin['version'] ?? 'Unknown',
                'author' => $plugin['author'] ?? 'Unknown',
                'description' => $plugin['description'] ?? 'No description available',
            ]);
        } catch (Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}
