<?php

declare(strict_types=1);

namespace App\Menu;

use App\Menu\Event\MenuEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class MenuBuilder
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function buildMenu(): array
    {
        $event = new MenuEvent();

        // Dispatch the event to allow other parts of the application to modify the menu
        $this->eventDispatcher->dispatch($event, MenuEvent::NAME);

        // Sort the menu by priority
        $event->sortMenu();

        return $event->getMenuNodes();
    }

    public function getMenuNode(string $id): ?MenuNode
    {
        $event = new MenuEvent();
        $this->eventDispatcher->dispatch($event, MenuEvent::NAME);

        return $event->findNodeById($id);
    }
}
