<?php

declare(strict_types=1);

namespace App\Menu\Twig;

use App\Menu\MenuBuilder;
use App\Menu\MenuNode;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFunction;

class MenuExtension
{
    public function __construct(
        private MenuBuilder $menuBuilder,
        private RouterInterface $router,
        private TranslatorInterface $translator
    ) {
    }

    #[AsTwigFunction('render_menu', isSafe: ['html'])]
    public function renderMenu(): string
    {
        $menuNodes = $this->menuBuilder->buildMenu();

        $html = '<ul class="navbar-nav me-auto">';
        foreach ($menuNodes as $node) {
            $html .= $this->renderMenuNode($node);
        }
        $html .= '</ul>';

        return $html;
    }

    #[AsTwigFunction('render_menu_node', isSafe: ['html'])]
    public function renderMenuNode(MenuNode $node): string
    {
        if (!$node->isVisible()) {
            return '';
        }

        $label = $node->getTranslationKey()
            ? $this->translator->trans($node->getTranslationKey())
            : $node->getLabel();

        if ($node->hasChildren()) {
            return $this->renderDropdownNode($node, $label);
        }

        return $this->renderSimpleNode($node, $label);
    }

    private function renderDropdownNode(MenuNode $node, string $label): string
    {
        $icon = $node->getIcon() ? '<i class="' . $node->getIcon() . ' me-1"></i>' : '';

        $html = '<li class="nav-item dropdown">';
        $html .= '<a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">';
        $html .= $icon . $label;
        $html .= '</a>';
        $html .= '<ul class="dropdown-menu">';

        foreach ($node->getChildren() as $child) {
            if (!$child->isVisible()) {
                continue;
            }

            // Handle divider nodes
            if ($child->isDivider()) {
                $html .= '<li><hr class="dropdown-divider"></li>';

                continue;
            }

            $childLabel = $child->getTranslationKey()
                ? $this->translator->trans($child->getTranslationKey())
                : $child->getLabel();

            $childIcon = $child->getIcon() ? '<i class="' . $child->getIcon() . ' me-1"></i>' : '';

            if ($child->getRoute()) {
                $url = $this->router->generate($child->getRoute(), $child->getRouteParams());
                $html .= '<li><a class="dropdown-item" href="' . $url . '">' . $childIcon . $childLabel . '</a></li>';
            } else {
                $html .= '<li><span class="dropdown-item-text">' . $childIcon . $childLabel . '</span></li>';
            }
        }

        $html .= '</ul></li>';

        return $html;
    }

    private function renderSimpleNode(MenuNode $node, string $label): string
    {
        $icon = $node->getIcon() ? '<i class="' . $node->getIcon() . ' me-1"></i>' : '';

        if ($node->getRoute()) {
            $url = $this->router->generate($node->getRoute(), $node->getRouteParams());

            return '<li class="nav-item"><a class="nav-link" href="' . $url . '">' . $icon . $label . '</a></li>';
        }

        return '<li class="nav-item"><span class="nav-link">' . $icon . $label . '</span></li>';
    }
}
