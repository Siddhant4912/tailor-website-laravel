<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\AppointmentUpload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Services\AppointmentInvoiceService;

class AppointmentService
{
    public function __construct(
        private AppointmentInvoiceService $invoiceService
    ) {
    }

    private function withRelations($query)
    {
        return $query->with([
            'customer',
            'assignedStaff',
            'items.garment.design',
            'items.garment.category',
            'uploads',
            'invoices.transactions',
        ]);
    }

    public function list()
    {
        return $this->withRelations(Appointment::where('status', '!=', \App\Enums\AppointmentStatusEnum::DRAFT))->latest()->get();
    }

    public function find($id)
    {
        return $this->withRelations(Appointment::where('id', $id))->firstOrFail();
    }

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            $data['visit_charge'] = (isset($data['measurement_type']) && $data['measurement_type'] === 'onsite_visit') ? 200 : 0;
            $data['deposit_amount'] = $data['visit_charge'];
            
            // Removed legacy logic that forced 'draft' status for online payments.
            $appointment = Appointment::create($data);

            if (!empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    $garment = \App\Models\Garment::find($item['garment_id']);
                    $price = 0;
                    $priceType = $item['price_type'] ?? 'stitching';

                    if ($garment) {
                        $type = is_object($appointment->type) ? $appointment->type->value : (string) $appointment->type;
                        if ($type === 'custom_cloth' && $garment->design) {
                            if ($priceType === 'secondary') {
                                $price = $garment->design->secondary_price ?? 0;
                            } else {
                                $price = $garment->design->additional_price ?? 0;
                                $priceType = 'additional';
                            }
                        } else {
                            $price = $garment->price ?? 0;
                            $priceType = 'stitching';
                        }
                    }

                    AppointmentItem::create([
                        'appointment_id' => $appointment->id,
                        'garment_id' => $item['garment_id'],
                        'quantity' => $item['quantity'] ?? 1,
                        'price' => $price,
                        'price_type' => $priceType,
                    ]);
                }
            }

            // Generate invoice on creation
            $this->invoiceService->generate($appointment->id);

            $created = $this->find($appointment->id);

            // Dispatch notification only if not draft
            $statusStr = is_object($created->status) ? $created->status->value : (string) $created->status;
            if ($statusStr !== 'draft') {
                try {
                    $created->customer->notify(new \App\Notifications\AppointmentStatusNotification($created, 'pending'));
                } catch (\Throwable $e) {
                    Log::error('Appointment creation notification failed: ' . $e->getMessage());
                }
            }

            return $created;
        });
    }

    public function update(Appointment $appointment, array $data)
    {
        return DB::transaction(function () use ($appointment, $data) {
            $oldStatus = $appointment->status;
            $appointment->update($data);

            if (isset($data['items'])) {
                $appointment->items()->delete();
                foreach ($data['items'] as $item) {
                    $garment = \App\Models\Garment::find($item['garment_id']);
                    $price = 0;
                    $priceType = $item['price_type'] ?? 'stitching';

                    if ($garment) {
                        $type = is_object($appointment->type) ? $appointment->type->value : (string) $appointment->type;
                        if ($type === 'custom_cloth' && $garment->design) {
                            if ($priceType === 'secondary') {
                                $price = $garment->design->secondary_price ?? 0;
                            } else {
                                $price = $garment->design->additional_price ?? 0;
                                $priceType = 'additional';
                            }
                        } else {
                            $price = $garment->price ?? 0;
                            $priceType = 'stitching';
                        }
                    }

                    AppointmentItem::create([
                        'appointment_id' => $appointment->id,
                        'garment_id' => $item['garment_id'],
                        'quantity' => $item['quantity'] ?? 1,
                        'price' => $price,
                        'price_type' => $priceType,
                    ]);
                }
            }

            $updated = $this->find($appointment->id);

            // Dispatch notification if status changed
            $oldStatusValue = $oldStatus instanceof \BackedEnum ? $oldStatus->value : (string) $oldStatus;
            $newStatusValue = $updated->status instanceof \BackedEnum ? $updated->status->value : (string) $updated->status;
            if ($oldStatusValue !== $newStatusValue) {
                try {
                    $updated->customer->notify(new \App\Notifications\AppointmentStatusNotification($updated, $newStatusValue));
                } catch (\Throwable $e) {
                    Log::error('Appointment status update notification failed: ' . $e->getMessage());
                }
            }

            return $updated;
        });
    }

    public function startVisit(Appointment $appointment)
    {
        $appointment->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $updated = $this->find($appointment->id);

        try {
            $updated->customer->notify(new \App\Notifications\AppointmentStatusNotification($updated, 'in_progress'));
        } catch (\Throwable $e) {
            Log::error('Appointment start visit notification failed: ' . $e->getMessage());
        }

        return $updated;
    }

    public function endVisit(Appointment $appointment, array $data)
    {
        $appointment->update([
            'status' => 'completed',
            'ended_at' => now(),
            'is_visited' => true,
            'measurement_taken' => $data['measurement_taken'] ?? true,
        ]);

        // FIX #5: invoice already generated on create() — only generate again
        // if one doesn't exist yet (e.g. visit_charge was updated before endVisit)
        if (!$appointment->invoices()->exists()) {
            try {
                $this->invoiceService->generate($appointment->id);
            } catch (\Throwable $e) {
                Log::error('endVisit invoice generation failed: ' . $e->getMessage());
            }
        }

        $updated = $this->find($appointment->id);

        try {
            $updated->customer->notify(new \App\Notifications\AppointmentStatusNotification($updated, 'completed'));
        } catch (\Throwable $e) {
            Log::error('Appointment end visit notification failed: ' . $e->getMessage());
        }

        return $updated;
    }

    public function uploadImage(Appointment $appointment, $file)
    {
        $path = $file->store('appointments/' . $appointment->id, 'public');

        return AppointmentUpload::create([
            'appointment_id' => $appointment->id,
            'file_path' => $path,
        ]);
    }

    public function deleteUpload(AppointmentUpload $upload)
    {
        Storage::disk('public')->delete($upload->file_path);
        $upload->delete();
    }

    public function delete(Appointment $appointment)
    {
        foreach ($appointment->uploads as $upload) {
            Storage::disk('public')->delete($upload->file_path);
        }

        $appointment->invoices->each(function($invoice) {
            $invoice->transactions()->delete();
            $invoice->delete();
        });

        return $appointment->delete();
    }
}
