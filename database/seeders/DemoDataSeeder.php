<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\Garment;
use App\Models\User;
use App\Services\OrderService;
use App\Enums\RoleEnum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $customer = User::where('role', RoleEnum::CUSTOMER)->first();
        $deliveryStaff = User::where('role', RoleEnum::DELIVERY_STAFF)->first();
        $tailor = User::where('role', RoleEnum::TAILOR)->first();
        $suit = Garment::where('name', 'Premium Wool Suit')->first();
        
        if (!$customer || !$suit || !$deliveryStaff) return;

        DB::transaction(function () use ($customer, $deliveryStaff, $tailor, $suit) {
            // 1. Create an Appointment
            $appointment = Appointment::create([
                'customer_id' => $customer->id,
                'assigned_staff_id' => $deliveryStaff->id,
                'type' => 'catalog_visit',
                'appointment_date' => now()->addDays(1),
                'appointment_time' => '10:00:00',
                'address_line' => '456 Customer Ave',
                'city' => 'New York',
                'status' => 'completed',
                'visit_charge' => 150.00,
                'is_visited' => true,
                'measurement_taken' => true,
                'started_at' => now()->subHours(2),
                'ended_at' => now()->subHours(1),
                'notes' => 'Customer wants a dark blue suit.',
            ]);

            AppointmentItem::create([
                'appointment_id' => $appointment->id,
                'garment_id' => $suit->id,
                'quantity' => 1,
            ]);

            // 2. Create Order from Appointment using Service
            $orderService = app(OrderService::class);
            
            $measurements = [
                ['field_name' => 'Chest', 'value' => '40'],
                ['field_name' => 'Waist', 'value' => '34'],
                ['field_name' => 'Sleeve Length', 'value' => '25'],
                ['field_name' => 'Shoulder', 'value' => '18'],
                ['field_name' => 'Pant Length', 'value' => '41'],
            ];

            $order = $orderService->createFromAppointment($appointment->id, [
                'delivery_address' => '456 Customer Ave, NY',
                'notes' => 'Priority stitching',
                'measurements' => $measurements,
            ]);

            // 3. Assign Tailor to Order Items
            foreach ($order->items as $item) {
                $item->update(['assigned_tailor_id' => $tailor->id, 'status' => 'stitching']);
            }

            // 4. Update Order Status
            $order->update(['status' => 'stitching']);

            // 5. Pay the Invoice
            $invoice = $order->invoices()->first();
            if ($invoice) {
                $invoice->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);
                
                $invoice->transactions()->create([
                    'transaction_number' => 'TXN-' . rand(100000, 999999),
                    'payment_mode' => 'online',
                    'amount' => $invoice->total_amount,
                    'status' => 'successful',
                ]);
            }
        });
    }
}
