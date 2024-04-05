<?php

namespace App\Filament\Pages;

use App\Models\Customer;
use App\Models\PipelineStage;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;

class ManageCustomerStages extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string $view = 'filament.pages.manage-customer-stages';

    // Our Custom heading to be displayed on the page
    protected ?string $heading = 'Customer Board';
    // Custom Navigation Link name
    protected static ?string $navigationLabel = 'Customer Board';
    // Adding a Heroicon to the Navigation Link
    #[On('statusChangeEvent')]
    public function changeRecordStatus($id, $pipeline_stage_id): void
    {
        $customer = Customer::find($id);

        if(!in_array($customer->pipeline_stage_id, [4,5])) {
            $customer->pipeline_stage_id = $pipeline_stage_id;
            $customer->save();

            $customer->pipelineStageLogs()->create([
                'pipeline_stage_id' => $pipeline_stage_id,
                'notes' => null,
                'user_id' => auth()->id()
            ]);

            // Inform the user that the status has been updated
            $customerName = $customer->first_name . ' ' . $customer->last_name;

            Notification::make()
                ->title($customerName . ' Pipeline Stage Updated')
                ->success()
                ->send();
        }else {
            Notification::make()
                ->title('Error: You cannot change the status of this customer')
                ->danger()
                ->send();
        }

    }

    // Data that we will pass to our View
    protected function getViewData(): array
    {
        $statuses = $this->statuses();

        $records = $this->records();

        // We are mapping through the statuses and adding the records to each status
        // This will form multiple lists dynamically based on the records
        $statuses = $statuses
            ->map(function ($status) use ($records) {
                $status['group'] = $this->getId();
                $status['kanbanRecordsId'] = "{$this->getId()}-{$status['id']}";
                $status['records'] = $records
                    ->filter(function ($record) use ($status) {
                        return $this->isRecordInStatus($record, $status);
                    });

                return $status;
            });

        return [
            'records' => $records,
            'statuses' => $statuses,
        ];
    }

    // Loading the statuses from the database and mapping them
    // to have id and title. ID will be checked against Customers
    protected function statuses(): Collection
    {
        return PipelineStage::query()
            ->orderBy('position')
            ->get()
            ->map(function (PipelineStage $stage) {
                return [
                    'id' => $stage->id,
                    'title' => $stage->name,
                ];
            });
    }

    // We are loading all the customers and mapping them to have ID, title, and status
    protected function records(): Collection
    {
        return Customer::with('pipelineStage') // Ensure you're eager loading the pipeline stage
        ->get()
            ->map(function (Customer $item) {
                return [
                    'id' => $item->id,
                    'title' => $item->first_name . ' ' . $item->last_name,
                    'status' => $item->pipeline_stage_id,
                    'position' => $item->pipelineStage->position ?? null, // Add position
                ];
            });
    }


    // We are checking if the record is in the status
    protected function isRecordInStatus($record, $status): bool
    {
        return $record['status'] === $status['id'];
    }
}
