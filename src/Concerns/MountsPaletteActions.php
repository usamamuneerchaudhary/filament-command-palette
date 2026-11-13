<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\CommandPalette\Concerns;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Facades\Filament;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Usamamuneerchaudhary\CommandPalette\Events\CommandExecuted;
use Usamamuneerchaudhary\CommandPalette\Exceptions\CommandNotAuthorized;
use Usamamuneerchaudhary\CommandPalette\Support\CommandItem;
use Usamamuneerchaudhary\CommandPalette\Support\PaletteContext;

/**
 * Hosts native Filament actions inside the palette overlay.
 *
 * Cached actions do not survive between Livewire requests, so each resolve
 * rebuilds the Action from the locked command id through the registry.
 */
trait MountsPaletteActions
{
    use InteractsWithActions {
        resolveAction as resolveCachedFilamentAction;
    }
    use InteractsWithSchemas;

    public const string PALETTE_ACTION = 'palette';

    #[Locked]
    public ?string $paletteActionId = null;

    #[Locked]
    public ?string $paletteActionQuery = null;

    #[Locked]
    public ?string $paletteActionPageClass = null;

    #[Locked]
    public mixed $paletteActionRecordKey = null;

    #[Locked]
    public ?string $paletteActionParentId = null;

    protected ?CommandItem $resolvedPaletteCommand = null;

    /**
     * Authorizes the command, then mounts its Filament action on this component.
     */
    public function mountPaletteAction(
        string $id,
        ?string $query = null,
        ?string $pageClass = null,
        mixed $recordKey = null,
        ?string $parentId = null,
    ): mixed {
        $command = $this->authorizedFilamentCommand($id, $query, $pageClass, $recordKey, $parentId);
        $action = $this->actionFromCommand($command);

        $this->paletteActionId = $id;
        $this->paletteActionQuery = $query;
        $this->paletteActionPageClass = $pageClass;
        $this->paletteActionRecordKey = $recordKey;
        $this->paletteActionParentId = $parentId;
        $this->resolvedPaletteCommand = $command;

        $this->cacheAction($action);

        CommandExecuted::dispatch(
            $command->toArray(),
            $this->requirePanel()->getId(),
            $query,
            Filament::auth()->id(),
        );

        return $this->mountAction(self::PALETTE_ACTION);
    }

    public function getDefaultActionRecord(Action $action): ?Model
    {
        $record = $this->resolvedPaletteCommand?->getBoundRecord();

        return $record instanceof Model ? $record : null;
    }

    /**
     * Rebuilds the mounted palette action from the locked command id.
     *
     * @param  array<string, mixed>  $action
     * @param  array<int, Action>  $parentActions
     */
    protected function resolveAction(array $action, array $parentActions): ?Action
    {
        if ($parentActions === [] && ($action['name'] ?? null) === self::PALETTE_ACTION) {
            return $this->cachedActions[self::PALETTE_ACTION] ?? $this->rebuildPaletteAction();
        }

        return $this->resolveCachedFilamentAction($action, $parentActions);
    }

    /**
     * Rebuilds and re-authorizes the Action for the locked command id.
     *
     * Returns null when the id is missing or the user can no longer run it.
     */
    protected function rebuildPaletteAction(): ?Action
    {
        if (! filled($this->paletteActionId)) {
            return null;
        }

        try {
            $command = $this->authorizedFilamentCommand(
                $this->paletteActionId,
                $this->paletteActionQuery,
                $this->paletteActionPageClass,
                $this->paletteActionRecordKey,
                $this->paletteActionParentId,
            );
        } catch (CommandNotAuthorized) {
            return null;
        }

        $this->resolvedPaletteCommand = $command;

        return $this->cacheAction($this->actionFromCommand($command));
    }

    /**
     * Resolves a Filament-action command and refuses anything else.
     */
    protected function authorizedFilamentCommand(
        string $id,
        ?string $query,
        ?string $pageClass,
        mixed $recordKey,
        ?string $parentId,
    ): CommandItem {
        $panel = $this->requirePanel();
        $context = PaletteContext::resolve($panel, $pageClass, $recordKey);
        $command = $this->registry()->find($id, $panel, $query, $context, $parentId);

        if (! $command instanceof CommandItem || ! $command->isAuthorized()) {
            throw CommandNotAuthorized::make($id);
        }

        if ($command->getType() !== CommandItem::TYPE_FILAMENT_ACTION) {
            throw CommandNotAuthorized::make($id);
        }

        if ($command->needsRecord() && $command->getBoundRecord() === null) {
            $command->bindRecord($context->record());
        }

        if ($command->needsRecord() && $command->getBoundRecord() === null) {
            throw CommandNotAuthorized::make($id);
        }

        return $command;
    }

    /**
     * Turns the command's Action into the single mounted `palette` action.
     */
    protected function actionFromCommand(CommandItem $command): Action
    {
        $action = $command->resolveFilamentAction();

        if (! $action instanceof Action) {
            throw CommandNotAuthorized::make($command->getId());
        }

        $action->name(self::PALETTE_ACTION);
        $action->livewire($this);

        return $action;
    }
}
