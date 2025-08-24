<?php

declare(strict_types=1);

namespace App\Menu\Event;

use App\Menu\MenuNode;
use Symfony\Contracts\EventDispatcher\Event;

class MenuEvent extends Event
{
    public const NAME = 'app.menu.build';

    private array $menuNodes = [];

    public function __construct()
    {
        $this->initializeDefaultMenu();
    }

    public function addMenuNode(MenuNode $node): self
    {
        $this->menuNodes[] = $node;

        return $this;
    }

    public function getMenuNodes(): array
    {
        return $this->menuNodes;
    }

    public function setMenuNodes(array $menuNodes): self
    {
        $this->menuNodes = $menuNodes;

        return $this;
    }

    public function findNodeById(string $id): ?MenuNode
    {
        foreach ($this->menuNodes as $node) {
            if ($node->getId() === $id) {
                return $node;
            }
            $found = $this->findNodeInChildren($node, $id);
            if ($found) {
                return $found;
            }
        }

        return null;
    }

    private function findNodeInChildren(MenuNode $node, string $id): ?MenuNode
    {
        foreach ($node->getChildren() as $child) {
            if ($child->getId() === $id) {
                return $child;
            }
            $found = $this->findNodeInChildren($child, $id);
            if ($found) {
                return $found;
            }
        }

        return null;
    }

    public function sortMenu(): self
    {
        foreach ($this->menuNodes as $node) {
            $node->sortChildren();
        }

        usort($this->menuNodes, function (MenuNode $a, MenuNode $b) {
            return $b->getPriority() <=> $a->getPriority();
        });

        return $this;
    }

    private function initializeDefaultMenu(): void
    {
        // Content Management
        $contentManagement = (new MenuNode('content_management', 'nav.content_management'))
            ->setIcon('fas fa-folder')
            ->setPriority(100)
            ->setTranslationKey('nav.content_management');

        $contentManagement->addChild(
            (new MenuNode('libraries', 'nav.libraries'))
                ->setRoute('library_index')
                ->setIcon('fas fa-database')
                ->setPriority(100)
                ->setTranslationKey('nav.libraries')
        );

        $contentManagement->addChild(
            (new MenuNode('artists', 'nav.artists'))
                ->setRoute('artist_index')
                ->setIcon('fas fa-user')
                ->setPriority(90)
                ->setTranslationKey('nav.artists')
        );

        $contentManagement->addChild(
            (new MenuNode('unmatched_tracks', 'nav.unmatched_tracks'))
                ->setRoute('unmatched_tracks_index')
                ->setIcon('fas fa-exclamation-triangle')
                ->setPriority(80)
                ->setTranslationKey('nav.unmatched_tracks')
        );

        // Configuration
        $configuration = (new MenuNode('configuration', 'nav.configuration'))
            ->setIcon('fas fa-cog')
            ->setPriority(80)
            ->setTranslationKey('nav.configuration');

        $configuration->addChild(
            (new MenuNode('association_config', 'nav.association_config'))
                ->setRoute('association_config_index')
                ->setIcon('fas fa-link')
                ->setPriority(100)
                ->setTranslationKey('nav.association_config')
        );

        $configuration->addChild(
            (new MenuNode('album_import_config', 'nav.album_import_config'))
                ->setRoute('album_import_config_index')
                ->setIcon('fas fa-compact-disc')
                ->setPriority(90)
                ->setTranslationKey('nav.album_import_config')
        );

        $configuration->addChild(
            (new MenuNode('metadata_config', 'nav.metadata'))
                ->setRoute('metadata_config_index')
                ->setIcon('fas fa-images')
                ->setPriority(70)
                ->setTranslationKey('nav.metadata')
        );

        // System
        $system = (new MenuNode('system', 'nav.system'))
            ->setIcon('fas fa-tools')
            ->setPriority(70)
            ->setTranslationKey('nav.system');

        $system->addChild(
            (new MenuNode('tasks', 'nav.tasks'))
                ->setRoute('tasks_index')
                ->setIcon('fas fa-tasks')
                ->setPriority(100)
                ->setTranslationKey('nav.tasks')
        );

        $system->addChild(
            (new MenuNode('plugins', 'nav.plugins'))
                ->setRoute('admin_plugins_index')
                ->setIcon('fas fa-puzzle-piece')
                ->setPriority(90)
                ->setTranslationKey('nav.plugins')
        );

        $this->addMenuNode($contentManagement);
        $this->addMenuNode($configuration);
        $this->addMenuNode($system);
    }
}
