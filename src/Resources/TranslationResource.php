<?php

namespace TomatoPHP\FilamentTranslations\Resources;

use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;
use Spatie\TranslationLoader\LanguageLine;
use TomatoPHP\FilamentTranslations\Models\Translation;
use TomatoPHP\FilamentTranslations\Resources\TranslationResource\Pages;
use TomatoPHP\FilamentTranslations\Services\ExcelImportExportService;

class TranslationResource extends Resource
{

    protected static ?string $model = Translation::class;

    protected static ?string $slug = 'translations';

    protected static ?string $recordTitleAttribute = 'key';

    protected static bool $isScopedToTenant = false;

    public static function getNavigationLabel(): string
    {
        return trans('filament-translations::translation.label');
    }

    public static function getLabel(): ?string
    {
        return trans('filament-translations::translation.single');
    }

    public static function getNavigationGroup(): ?string
    {
        return config('filament-translations.languages-switcher-menu.group', trans('filament-translations::translation.group'));
    }

    public static function getNavigationIcon(): string
    {
        return config('filament-translations.languages-switcher-menu.icon', 'heroicon-m-language');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return config('filament-translations.register_navigation', true);
    }

    public function getTitle(): string
    {
        return trans('filament-translations::translation.title.home');
    }

    public static function form(Form $form): Form
    {

        return $form->schema([
            Forms\Components\TextInput::make('group')
                ->label(trans('filament-translations::translation.group'))
                ->required()
                ->disabled(fn(Forms\Get $get) => $get('id') !== null)
                ->maxLength(255),
            Forms\Components\TextInput::make('key')
                ->label(trans('filament-translations::translation.key'))
                ->disabled(fn(Forms\Get $get) => $get('id') !== null)
                ->required()
                ->maxLength(255),
            \TomatoPHP\FilamentTranslationComponent\Components\Translation::make('text')
                ->label(trans('filament-translations::translation.text'))
                ->columnSpanFull(),

        ]);
    }

    public static function table(Table $table): Table
    {
        $actions = [];
        if (config('filament-translations.import_enabled')) {
            $actions[] = Tables\Actions\Action::make('import')
                ->label(trans('filament-translations::translation.import'))
                ->form([
                    FileUpload::make('file')
                        ->label(trans('filament-translations::translation.import-file'))
                        ->acceptedFileTypes([
                            "application/csv",
                            "application/vnd.ms-excel",
                            "application/vnd.msexcel",
                            "text/csv",
                            "text/anytext",
                            "text/plain",
                            "text/x-c",
                            "text/comma-separated-values",
                            "inode/x-empty",
                            "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
                        ])
                        ->storeFiles(false),
                ])
                ->icon('heroicon-o-document-arrow-up')
                ->color('success')
                ->action(fn(array $data) => ExcelImportExportService::import($data['file']));
        }

        if (config('filament-translations.export_enabled')) {
            $actions[] = Tables\Actions\Action::make('export')
                ->label(trans('filament-translations::translation.export'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->action(fn() => ExcelImportExportService::export());
        }
        $table
            ->headerActions($actions)
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->label(trans('filament-translations::translation.key'))
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('text')
                    ->label(trans('filament-translations::translation.text'))
                    ->view('filament-translations::text-column')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_reviewed')
                    ->label(trans('filament-translations::translation.is_reviewed'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('group')
                    ->label(trans('filament-translations::global.filter_by_group'))
                    ->options(fn(): array => LanguageLine::query()->groupBy('group')->pluck('group', 'group')->all()),
                Tables\Filters\Filter::make('text')
                    ->label(trans('filament-translations::global.filter_by_null_text'))
                    ->query(fn(Builder $query): Builder => $query->whereJsonContains('text', [])),
                Tables\Filters\TernaryFilter::make('reviewed')
                    ->label(trans('filament-translations::global.filter_by_reviewed'))
                    ->queries(
                        true: fn(Builder $query): Builder => $query->whereJsonContains('metadata', ['is_reviewed' => true]),
                        false: fn(Builder $query): Builder => $query->whereJsonContains('metadata', ['is_reviewed' => false])->orWhereNull('metadata->is_reviewed')
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);

        $validateAction = Tables\Actions\Action::make('validate')
            ->icon(fn(Translation $record) => $record->is_reviewed ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
            ->color(fn(Translation $record) => $record->is_reviewed ? 'warning' : 'success')
            ->label(fn(Translation $record) => $record->is_reviewed ? 'Mark as needs review' : 'Mark as reviewed')
            ->action(fn(Translation $record) => $record->update(['metadata' => ['is_reviewed' => !$record->is_reviewed]]));

        if (!config('filament-translations.modal')) {
            $table->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    $validateAction,
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ]);
        } else {
            $table->actions([
                $validateAction,
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
        }


        return $table;
    }

    public static function getPages(): array
    {
        if (config('filament-translations.modal')) {
            return [
                'index' => Pages\ManageTranslations::route('/'),
            ];
        } else {
            return [
                'index' => Pages\ListTranslations::route('/'),
                'create' => Pages\CreateTranslation::route('/create'),
                'edit' => Pages\EditTranslation::route('/{record}/edit'),
            ];
        }
    }
}
