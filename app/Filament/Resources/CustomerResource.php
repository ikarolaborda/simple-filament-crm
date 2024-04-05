<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
use App\Models\CustomField;
use App\Models\PipelineStage;
use App\Models\Role;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Colors\Color;
use http\Header;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Facades\Storage;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';


    public static function infoList(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Personal Information')
                    ->schema([
                        TextEntry::make('first_name'),
                        TextEntry::make('last_name'),
                    ])
                    ->columns(),
                Section::make('Contact Information')
                    ->schema([
                        TextEntry::make('email'),
                        TextEntry::make('phone_number'),
                    ])
                    ->columns(),
                Section::make('Additional Details')
                    ->schema([
                        TextEntry::make('description'),
                    ]),
                Section::make('Lead and Stage Information')
                    ->schema([
                        TextEntry::make('leadSource.name'),
                        TextEntry::make('pipelineStage.name'),
                    ])
                    ->columns(),
                Section::make('Additional fields')
                    ->hidden(fn($record) => $record->customFields->isEmpty())
                    ->schema(
                    // We are looping within our relationship, then creating a TextEntry for each Custom Field
                        fn($record) => $record->customFields->map(function ($customField) {
                            return TextEntry::make($customField->customField->name)
                                ->label($customField->customField->name)
                                ->default($customField->value);
                        })->toArray()
                    )
                    ->columns(),
                Section::make('Documents')
                    // This will hide the section if there are no documents
                    ->hidden(fn($record) => $record->documents->isEmpty())
                    ->schema([
                        RepeatableEntry::make('documents')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('file_path')
                                    ->label('Document')
                                    ->formatStateUsing(fn() => "Download Document")
                                    ->url(fn($record) => Storage::url($record->file_path), true)
                                    ->badge()
                                    ->color(Color::Blue),
                                TextEntry::make('comments'),
                            ])
                            ->columns()
                    ]),
                Section::make('Pipeline Stage History and Notes')
                    ->schema([
                        ViewEntry::make('pipelineStageLogs')
                            ->label('')
                            ->view('infolists.components.pipeline-stage-history-list')
                    ])
                    ->collapsible()
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Employee Information')
                    ->schema([
                        Forms\Components\Select::make('employee_id')
                            ->options(User::where('role_id', Role::where('name', 'Employee')->first()->id)->pluck('name', 'id'))
                    ])
                    ->hidden(!auth()->user()->isAdmin()),
                Forms\Components\Section::make('Customer Information')
                    ->schema(
                        [
                            Forms\Components\TextInput::make('first_name')
                                ->maxLength(255),
                            Forms\Components\TextInput::make('last_name')
                                ->maxLength(255),
                            Forms\Components\TextInput::make('email')
                                ->email()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('phone_number')
                                ->maxLength(255),
                            Forms\Components\Textarea::make('description')
                                ->maxLength(65535)
                                ->columnSpanFull(),
                        ]
                    )->columns(),
                Forms\Components\Section::make('Lead Details')
                    ->schema([
                        Forms\Components\Select::make('lead_source_id')
                            ->relationship('leadSource', 'name'),
                        Forms\Components\Select::make('tags')
                            ->relationship('tags', 'name')
                            ->multiple(),
                    ])->columns(),
                Forms\Components\Section::make('Pipeline Information')
                    ->schema(
                        [
                            Forms\Components\Select::make('pipeline_stage_id')
                                ->relationship('pipelineStage', 'name', function ($query) {
                                    // It is important to order by position to display the correct order
                                    $query->orderBy('position', 'asc');
                                })
                                // We are setting the default value to the default Pipeline Stage
                                ->default(PipelineStage::where('is_default', true)->first()?->id)
                                ->disabled(fn($record) => $record?->pipelineStage->position == 4)
                                ->label(fn($record) => $record?->pipelineStage->position == 4 ?
                                    'Pipeline Stage (Cannot be edited when the proposal has already been rejected)' : 'Pipeline Stage'),
                        ]
                    )->columns(),
                Forms\Components\Section::make('Documents')
                    ->visibleOn('edit')
                    ->schema([
                        Forms\Components\Repeater::make('documents')
                            ->relationship('documents')
                            ->hiddenLabel()
                            ->reorderable(false)
                            ->addActionLabel('Add Document')
                            ->schema([
                                Forms\Components\FileUpload::make('file_path')
                                    ->required(),
                                Forms\Components\Textarea::make('comments'),
                            ])
                            ->columns()
                    ]),
                Forms\Components\Section::make('Additional fields')
                    ->schema([
                        Forms\Components\Repeater::make('fields')
                            ->hiddenLabel()
                            ->relationship('customFields')
                            ->schema([
                                Forms\Components\Select::make('custom_field_id')
                                    ->label('Field Type')
                                    ->options(CustomField::pluck('name', 'id')->toArray())
                                    // We will disable already selected fields
                                    ->disableOptionWhen(function ($value, $state, Get $get) {
                                        return collect($get('../*.custom_field_id'))
                                            ->reject(fn($id) => $id === $state)
                                            ->filter()
                                            ->contains($value);
                                    })
                                    ->required()
                                    ->searchable()
                                    ->live(),
                                Forms\Components\TextInput::make('value')
                                    ->required()
                            ])
                            ->addActionLabel('Add another Field')
                            ->columns(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                return $query->with('tags');
            })
            ->columns([
                Tables\Columns\TextColumn::make('employee.name')
                    ->hidden(!auth()->user()->isAdmin()),
                Tables\Columns\TextColumn::make('first_name')
                    ->searchable()
                    ->label('Name')
                    ->formatStateUsing(function ($record) {
                        $tagsList = view('customer.tagsList', ['tags' => $record->tags])->render();

                        return $record->first_name . ' ' . $record->last_name . ' ' . $tagsList;
                    })
                    ->html()
                    ->searchable(['first_name', 'last_name']),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('leadSource.name')
                    ->label('Lead Source (Origin)')
                    ->searchable(),
                Tables\Columns\TextColumn::make('pipelineStage.name')
                    ->label('Pipeline Stage')
                    ->formatStateUsing(function ($state, $record) {
                        $style = match($record->pipelineStage->position) {
                            3 => 'color: #DAA520;',
                            4 => 'color: #FF0000;',
                            5 => 'color: #008000;',
                            default => 'color: #000000;',
                        };
                        return "<span style='{$style}'>{$state}</span>";
                    })
                    ->html(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                ->toggleable(isToggledHiddenByDefault: true)
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('')
                    ->tooltip('Edit Customer')
                    ->hidden(fn($record) => $record->trashed())
                    ->disabled(function ($record) {
                        return $record->pipelineStage->position == 4;
                    }),
                Tables\Actions\Action::make('Move to Stage')
                    ->label('')
                    ->hidden(fn($record) => $record->trashed())
                    ->disabled(fn($record) => $record->pipelineStage->position == 4 || $record->pipelineStage->position == 5)
                    ->tooltip('Move to a different stage (Cannot perform action if the stage is rejected of is already a customer)')
                    ->icon('heroicon-m-chevron-double-right')
                    ->form([
                        Forms\Components\Select::make('pipeline_stage_id')
                            ->label('Status')
                            ->options(PipelineStage::pluck('name', 'id')->toArray())
                            ->default(function (Customer $record) {
                                $currentPosition = $record->pipelineStage->position;
                                return PipelineStage::where('position', '>', $currentPosition)->first()?->id;
                            }),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                    ])
                    ->action(function (Customer $customer, array $data): void {
                        $customer->pipeline_stage_id = $data['pipeline_stage_id'];
                        $customer->save();

                        $customer->pipelineStageLogs()->create([
                            'pipeline_stage_id' => $data['pipeline_stage_id'],
                            'notes' => $data['notes'],
                            'user_id' => auth()->id()
                        ]);

                        Notification::make()
                            ->title('Customer Pipeline Updated')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make()
                ->label('')
                ->tooltip('Delete Customer'),
                Tables\Actions\RestoreAction::make(),
            ])
            ->recordUrl(function ($record) {
                // If the record is trashed, return null
                if ($record->trashed()) {
                    // Null will disable the row click
                    return null;
                }

                return Pages\ViewCustomer::getUrl([$record->id]);
            })
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->hidden(function (Pages\ListCustomers $livewire) {
                        return $livewire->activeTab == 'archived';
                    }),
                Tables\Actions\RestoreBulkAction::make()
                    ->hidden(function (Pages\ListCustomers $livewire) {
                        return $livewire->activeTab != 'archived';
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
            'view' => Pages\ViewCustomer::route('/{record}'),
        ];
    }
}
