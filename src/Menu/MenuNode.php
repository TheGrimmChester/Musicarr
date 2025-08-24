<?php

declare(strict_types=1);

namespace App\Menu;

class MenuNode
{
    private string $id;
    private ?string $route = null;
    private array $routeParams = [];
    private ?string $icon = null;
    private string $label;
    private int $priority = 0;
    private array $attributes = [];
    private array $children = [];
    private ?string $parentId = null;
    private bool $visible = true;
    private ?string $translationKey = null;
    private bool $isDivider = false;

    public function __construct(string $id, string $label)
    {
        $this->id = $id;
        $this->label = $label;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }

    public function setRoute(?string $route): self
    {
        $this->route = $route;

        return $this;
    }

    public function getRouteParams(): array
    {
        return $this->routeParams;
    }

    public function setRouteParams(array $routeParams): self
    {
        $this->routeParams = $routeParams;

        return $this;
    }

    public function addRouteParam(string $key, mixed $value): self
    {
        $this->routeParams[$key] = $value;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function setAttributes(array $attributes): self
    {
        $this->attributes = $attributes;

        return $this;
    }

    public function addAttribute(string $key, mixed $value): self
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function addChild(self $child): self
    {
        $child->setParentId($this->id);
        $this->children[] = $child;

        return $this;
    }

    public function removeChild(self $child): self
    {
        $key = array_search($child, $this->children, true);
        if (false !== $key) {
            unset($this->children[$key]);
            $this->children = array_values($this->children);
        }

        return $this;
    }

    public function hasChildren(): bool
    {
        return !empty($this->children);
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(?string $parentId): self
    {
        $this->parentId = $parentId;

        return $this;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): self
    {
        $this->visible = $visible;

        return $this;
    }

    public function getTranslationKey(): ?string
    {
        return $this->translationKey;
    }

    public function setTranslationKey(?string $translationKey): self
    {
        $this->translationKey = $translationKey;

        return $this;
    }

    public function isDivider(): bool
    {
        return $this->isDivider;
    }

    public function setIsDivider(bool $isDivider): self
    {
        $this->isDivider = $isDivider;

        return $this;
    }

    public function sortChildren(): self
    {
        usort($this->children, function (MenuNode $a, MenuNode $b) {
            return $b->getPriority() <=> $a->getPriority();
        });

        return $this;
    }

    public function toArray(): array
    {
        $children = [];
        foreach ($this->children as $child) {
            $children[] = $child->toArray();
        }

        return [
            'id' => $this->id,
            'route' => $this->route,
            'routeParams' => $this->routeParams,
            'icon' => $this->icon,
            'label' => $this->label,
            'priority' => $this->priority,
            'attributes' => $this->attributes,
            'children' => $children,
            'visible' => $this->visible,
            'translationKey' => $this->translationKey,
            'isDivider' => $this->isDivider,
        ];
    }
}
