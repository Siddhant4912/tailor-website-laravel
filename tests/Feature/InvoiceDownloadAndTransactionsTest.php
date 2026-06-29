<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Garment;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\Order;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Enums\RoleEnum;
use App\Enums\InvoiceStatusEnum;
use App\Enums\TransactionStatusEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceDownloadAndTransactionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $customer;
    private User $staff;
    private Garment $garment;

    protected function setUp(): void
    {
        parent::setUp();
        User::unguard();

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'phone' => '9999999999',
            'password' => bcrypt('password'),
            'role' => RoleEnum::ADMIN,
            'status' => 'active',
        ]);

        $this->customer = User::create([
            'name' => 'Customer User',
            'email' => 'customer@test.com',
            'phone' => '7777777777',
            'password' => bcrypt('password'),
            'role' => RoleEnum::CUSTOMER,
            'status' => 'active',
        ]);

        $this->staff = User::create([
            'name' => 'Staff User',
            'email' => 'staff@test.com',
            'phone' => '8888888888',
            'password' => bcrypt('password'),
            'role' => RoleEnum::DELIVERY_STAFF,
            'status' => 'active',
        ]);

        $this->seed(\Database\Seeders\CatalogSeeder::class);
        $this->garment = Garment::first();
    }

    public function test_generic_invoice_download_for_order_and_appointment(): void
    {
        // 1. Create Appointment and Appointment Invoice
        $appointment = Appointment::create([
            'customer_id' => $this->customer->id,
            'assigned_staff_id' => $this->staff->id,
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

        $apptInvoice = app(\App\Services\InvoiceService::class)->generateForAppointment($appointment);

        // Download appointment invoice as Admin via generic endpoint
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/invoices/{$apptInvoice->id}/download");
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');

        // Download appointment invoice as Customer
        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson("/api/invoices/{$apptInvoice->id}/download");
        $response->assertStatus(200);

        // Download appointment invoice as unauthorized customer
        $otherCustomer = User::create([
            'name' => 'Other Customer',
            'email' => 'other@test.com',
            'phone' => '1111111111',
            'password' => bcrypt('password'),
            'role' => RoleEnum::CUSTOMER,
            'status' => 'active',
        ]);
        $response = $this->actingAs($otherCustomer, 'sanctum')
            ->getJson("/api/invoices/{$apptInvoice->id}/download");
        $response->assertStatus(403);

        // 2. Create Order and Order Invoice
        $order = Order::create([
            'order_number' => 'ORD-12345',
            'customer_id' => $this->customer->id,
            'status' => \App\Enums\OrderStatusEnum::PENDING,
            'subtotal' => 1000.00,
            'gst_rate' => 18.00,
            'gst_amount' => 180.00,
            'visit_charge' => 150.00,
            'advance_paid' => 650.00,
            'total_price' => 1330.00,
            'delivery_address' => '123 Test Street',
        ]);

        $orderInvoice = app(\App\Services\InvoiceService::class)->generateForOrder($order);

        // Download order invoice as Admin via generic endpoint
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/invoices/{$orderInvoice->id}/download");
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');

        // Download order invoice as Customer
        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson("/api/invoices/{$orderInvoice->id}/download");
        $response->assertStatus(200);
    }

    public function test_multi_transaction_invoice_status_updates(): void
    {
        // Create an Invoice with two transactions (one successful advance, one pending balance)
        $order = Order::create([
            'order_number' => 'ORD-98765',
            'customer_id' => $this->customer->id,
            'status' => \App\Enums\OrderStatusEnum::PENDING,
            'subtotal' => 1000.00,
            'gst_rate' => 18.00,
            'gst_amount' => 180.00,
            'visit_charge' => 150.00,
            'advance_paid' => 650.00,
            'total_price' => 1330.00,
            'delivery_address' => '123 Test Street',
        ]);

        $invoice = Invoice::create([
            'customer_id' => $order->customer_id,
            'invoiceable_id' => $order->id,
            'invoiceable_type' => Order::class,
            'invoice_number' => 'INV-98765',
            'subtotal' => 1000.00,
            'visit_charge' => 150.00,
            'advance_paid' => 650.00,
            'gst_rate' => 18.00,
            'gst_amount' => 180.00,
            'total_amount' => 1330.00,
            'status' => InvoiceStatusEnum::GENERATED,
            'generated_at' => now(),
        ]);

        // Transaction 1: Successful Advance
        $txn1 = Transaction::create([
            'invoice_id' => $invoice->id,
            'transaction_number' => 'TXN-ADV',
            'payment_mode' => 'cash',
            'amount' => 650.00,
            'status' => TransactionStatusEnum::SUCCESSFUL,
        ]);

        // Transaction 2: Pending Balance
        $txn2 = Transaction::create([
            'invoice_id' => $invoice->id,
            'transaction_number' => 'TXN-BAL',
            'payment_mode' => 'cash',
            'amount' => 680.00,
            'status' => TransactionStatusEnum::PENDING,
        ]);

        // Admin simulates mockCharge on Transaction 2 (balance)
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/admin/payments/mock-charge', [
            'transaction_id' => $txn2->id,
            'payment_mode' => 'online_upi',
        ]);

        $response->assertStatus(200);

        // Verify Transaction 2 status updated to SUCCESSFUL
        $txn2->refresh();
        $this->assertEquals(TransactionStatusEnum::SUCCESSFUL, $txn2->status);

        // Verify Invoice status updated to PAID because all transactions are successful
        $invoice->refresh();
        $this->assertEquals(InvoiceStatusEnum::PAID, $invoice->status);

        // Verify OrderResource returns balance_due as 0
        $order->load('invoices');
        $resource = new \App\Http\Resources\OrderResource($order);
        $resArray = $resource->toArray(request());
        $this->assertEquals(0, $resArray['balance_due']);
    }
}
