<?php

namespace App\Filament\Resources\Categorias\Pages;

use App\Filament\Resources\Categorias\CategoriaResource;
use App\Models\Categoria;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use BackedEnum;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ListCategories extends Page
{
    protected static string $resource = CategoriaResource::class;

    protected static ?string $title = 'Categorías';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Categorías';

    protected static string|UnitEnum|null $navigationGroup = 'Catálogo';

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->can('gestionar_productos') ?? false;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.admin.pages.categorias.tree-view'),
        ]);
    }

    public function getBreadcrumb(): ?string
    {
        return 'Categorías';
    }

    public function getGroupsProperty()
    {
        return Categoria::query()
            ->with(['children' => fn ($q) => $q->withCount('productos')->orderBy('nombre')])
            ->withCount('productos')
            ->groups()
            ->orderBy('nombre')
            ->get();
    }

    public ?Categoria $editCategoriaRecord = null;

    public ?Categoria $deleteCategoriaRecord = null;

    public function editCategoria(int $id): void
    {
        $record = Categoria::findOrFail($id);
        $this->editCategoriaRecord = $record;

        $this->mountAction('editCategoriaAction');
    }

    public function deleteCategoria(int $id): void
    {
        $record = Categoria::findOrFail($id);

        if ($record->productos()->exists()) {
            Notification::make()
                ->title('No se puede eliminar')
                ->body('Tiene productos asociados. Desactívala para conservar el catálogo.')
                ->danger()
                ->send();

            return;
        }

        if ($record->children()->exists()) {
            Notification::make()
                ->title('No se puede eliminar')
                ->body('Tiene subcategorías asociadas. Elimínalas primero.')
                ->danger()
                ->send();

            return;
        }

        $this->deleteCategoriaRecord = $record;

        $this->mountAction('deleteCategoriaAction');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nueva categoría'),

            Action::make('editCategoriaAction')
                ->hidden()
                ->label(fn () => 'Editar ' . ($this->editCategoriaRecord?->isGroup() ? 'grupo' : 'categoría'))
                ->record(fn () => $this->editCategoriaRecord)
                ->form(fn (Schema $schema): Schema => CategoriaResource::form($schema))
                ->fillForm(fn (): array => $this->editCategoriaRecord->attributesToArray())
                ->action(function (array $data): void {
                    $this->editCategoriaRecord->update($data);

                    Notification::make()
                        ->title('Guardado correctamente')
                        ->success()
                        ->send();
                })
                ->modalSubmitActionLabel('Guardar'),

            Action::make('deleteCategoriaAction')
                ->hidden()
                ->label('Eliminar')
                ->record(fn () => $this->deleteCategoriaRecord)
                ->requiresConfirmation()
                ->modalHeading(fn () => 'Eliminar ' . ($this->deleteCategoriaRecord?->isGroup() ? 'grupo' : 'categoría'))
                ->modalDescription(fn () => '¿Estás seguro de que deseas eliminar "' . ($this->deleteCategoriaRecord?->nombre) . '"? Esta acción no se puede deshacer.')
                ->action(function (): void {
                    $this->deleteCategoriaRecord->delete();

                    Notification::make()
                        ->title('Eliminado correctamente')
                        ->success()
                        ->send();
                })
                ->color('danger'),
        ];
    }
}
