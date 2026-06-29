<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Garment;
use App\Models\ClothCategory;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\Order;
use App\Models\Invoice;
use App\Enums\RoleEnum;
use App\Enums\OrderStatusEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceAdvanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_confirmation_saves_and_renders_advance_payment(): void
    {
        User::unguard();

        // 1. Create a Delivery Staff User and Customer
        $staff = User::create([
            'name' => 'Test Delivery Staff',
            'email' => 'staff@test.com',
            'phone' => '8888888888',
            'password' => bcrypt('password'),
            'role' => RoleEnum::DELIVERY_STAFF,
            'status' => 'active',
        ]);

        $customer = User::create([
            'name' => 'Test Customer',
            'email' => 'customer@test.com',
            'phone' => '7777777777',
            'password' => bcrypt('password'),
            'role' => RoleEnum::CUSTOMER,
            'status' => 'active',
        ]);

        // 2. Seed the Catalog
        $this->seed(\Database\Seeders\CatalogSeeder::class);
        $garment = Garment::first();

        // 3. Create Appointment
        $appointment = Appointment::create([
            'customer_id' => $customer->id,
            'assigned_staff_id' => $staff->id,
            'type' => 'catalog_visit',
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '12:00:00',
            'address_line' => '123 Test Street',
            'city' => 'Test City',
            'state' => 'Test State',
            'pincode' => '400001',
            'status' => 'confirmed',
            'visit_charge' => 150.00,
        ]);

        AppointmentItem::create([
            'appointment_id' => $appointment->id,
            'garment_id' => $garment->id,
            'quantity' => 1,
        ]);

        // Generate appointment invoice and mark visit charge transaction as successful
        $apptInvoice = app(\App\Services\InvoiceService::class)->generateForAppointment($appointment);
        $apptInvoice->transactions()->first()->update([
            'status' => \App\Enums\TransactionStatusEnum::SUCCESSFUL,
            'amount' => 150.00,
        ]);

        // 4. Confirm the Order via API
        $response = $this->actingAs($staff, 'sanctum')->postJson("/api/staff/appointments/{$appointment->id}/confirm-order", [
            'items' => [
                [
                    'garment_id' => $garment->id,
                    'quantity' => 1,
                    'price' => 1000.00,
                    'custom_notes' => 'Some note',
                    'provided_own_fabric' => false,
                ]
            ],
            'delivery_address' => '123 Test Street, Test City - 400001',
            'notes' => 'Test order notes',
            'advance_paid' => 500.00,
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(201);

        // 5. Verify the Order model in DB has advance_paid saved
        $order = Order::where('appointment_id', $appointment->id)->first();
        $this->assertNotNull($order);
        
        // Note: advancePaid in controller = advance_paid request parameter (500) + visit_charge (150) = 650.00
        $expectedAdvancePaid = 650.00;
        $this->assertEquals($expectedAdvancePaid, (float) $order->advance_paid);

        // 6. Verify the Invoice model in DB has advance_paid saved
        $invoice = $order->invoices()->first();
        $this->assertNotNull($invoice);
        $this->assertEquals($expectedAdvancePaid, (float) $invoice->advance_paid);

        // 7. Verify the Transaction amount is correctly set to balance due (total_price - advance_paid)
        // total_price = subtotal (1000) + GST (0 by default in test if service throws/gst not seeded) + visit_charge (150) = 1150.00
        // balance due = 1150 - 650 = 500.00
        // 7. Verify the Transaction amounts are correctly set
        // - Successful transaction of ₹650.00 for the advance paid
        $successTxn = $invoice->transactions()->where('status', \App\Enums\TransactionStatusEnum::SUCCESSFUL)->first();
        $this->assertNotNull($successTxn);
        $this->assertEquals($expectedAdvancePaid, (float) $successTxn->amount);

        // - Pending transaction of ₹500.00 for the balance due
        $pendingTxn = $invoice->transactions()->where('status', \App\Enums\TransactionStatusEnum::PENDING)->first();
        $this->assertNotNull($pendingTxn);
        $expectedBalanceDue = $invoice->total_amount - $invoice->advance_paid;
        $this->assertEquals($expectedBalanceDue, (float) $pendingTxn->amount);

        // 8. Test HTML rendering contains the Advance Paid label and correct amount
        $html = view('invoices.invoice', [
            'invoice' => $invoice,
            'order' => $order,
        ])->render();

        $this->assertStringContainsString('Advance / Deposit Paid', $html);
        $this->assertStringContainsString('- &#8377;650.00', $html);
        $this->assertStringContainsString('Balance Due', $html);
    }
}
