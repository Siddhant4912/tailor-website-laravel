{{-- siddhant pawawr 05-07-2026 --}}
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'DejaVu Sans', sans-serif;
      color: #334155;
      padding: 30px;
      font-size: 10px;
      line-height: 1.5;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 20px;
    }

    /* Layout helper tables */
    .layout-table td {
      border: none;
      padding: 0;
      vertical-align: top;
    }

    /* Brand & Title */
    .brand-title {
      font-size: 20px;
      font-weight: bold;
      color: #0f172a;
      letter-spacing: -0.5px;
    }

    .brand-accent {
      color: #c8945e;
    }

    .tagline {
      font-size: 9px;
      color: #64748b;
      margin-top: 2px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .company-details {
      font-size: 9px;
      color: #64748b;
      margin-top: 8px;
      line-height: 1.4;
    }

    .invoice-title {
      font-size: 20px;
      font-weight: bold;
      color: #0f172a;
      text-align: right;
      letter-spacing: 0.5px;
    }

    .invoice-meta {
      text-align: right;
      font-size: 10px;
      color: #475569;
      margin-top: 5px;
      line-height: 1.4;
    }

    .meta-value {
      font-weight: bold;
      color: #0f172a;
    }

    /* Badges */
    .badge {
      display: inline-block;
      padding: 3px 8px;
      font-size: 8px;
      font-weight: bold;
      border-radius: 4px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-top: 4px;
    }

    .badge-paid {
      color: #166534;
      background-color: #dcfce7;
      border: 1px solid #bbf7d0;
    }

    .badge-unpaid {
      color: #991b1b;
      background-color: #fee2e2;
      border: 1px solid #fecaca;
    }

    /* Customer & Order details cards */
    .details-box {
      background-color: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 6px;
      padding: 10px 12px;
    }

    .section-title {
      font-size: 8px;
      font-weight: bold;
      color: #c8945e;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      margin-bottom: 6px;
    }

    .details-name {
      font-size: 11px;
      font-weight: bold;
      color: #0f172a;
      margin-bottom: 2px;
    }

    .details-text {
      font-size: 9px;
      color: #475569;
      line-height: 1.4;
    }

    /* Items Table */
    .items-table {
      margin-top: 10px;
    }

    .items-table th {
      background-color: #0f172a;
      color: #ffffff;
      font-weight: bold;
      font-size: 9px;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      padding: 8px 10px;
      border: none;
    }

    .items-table td {
      padding: 8px 10px;
      border-bottom: 1px solid #e2e8f0;
      color: #334155;
      vertical-align: middle;
    }

    .items-table tr:nth-child(even) td {
      background-color: #f8fafc;
    }

    .item-name {
      font-size: 10px;
      font-weight: bold;
      color: #0f172a;
    }

    .item-meta {
      font-size: 8px;
      color: #64748b;
      margin-top: 2px;
    }

    .item-note {
      font-size: 8px;
      color: #c8945e;
      margin-top: 2px;
      font-style: italic;
    }

    /* Totals block */
    .totals-table {
      width: 100%;
    }

    .totals-table td {
      padding: 4px 8px;
      font-size: 10px;
      color: #475569;
      border: none;
    }

    .totals-table td.amount {
      text-align: right;
      font-weight: bold;
      color: #0f172a;
    }

    .totals-table .discount-row td {
      color: #b45309;
    }

    .totals-table .paid-row td {
      color: #166534;
    }

    .totals-table .grand-total-row td {
      font-size: 12px;
      font-weight: bold;
      color: #0f172a;
      border-top: 1.5px solid #0f172a;
      padding-top: 6px;
    }

    .totals-table .grand-total-row td.amount {
      font-size: 13px;
      color: #c8945e;
    }

    /* Footer */
    .footer {
      clear: both;
      margin-top: 40px;
      border-top: 1px solid #e2e8f0;
      padding-top: 12px;
      text-align: center;
      font-size: 9px;
      color: #94a3b8;
    }
  </style>
</head>

<body>

  @php
    $subtotal = 0;
    $statusVal = is_object($invoice->status) ? $invoice->status->value : $invoice->status;
    $txn = $invoice->transactions->first(function($t) {
        $tStatus = is_object($t->status) ? $t->status->value : $t->status;
        return $tStatus === 'successful';
    });
    if (!$txn) {
        $txn = $invoice->transactions->first();
    }
    $paymentMode = $txn ? $txn->payment_mode : null;
  @endphp

  {{-- HEADER --}}
  <table class="layout-table" style="border-bottom: 2px solid #c8945e; padding-bottom: 12px; margin-bottom: 20px;">
    <tr>
      <td style="width: 60%;">
        <div style="min-height: 54px; margin-bottom: 6px;">
          @if(file_exists(public_path('images/logo.png')))
            <img src="{{ public_path('images/logo.png') }}" style="float: left; height: 50px; width: 50px; border-radius: 8px; margin-right: 12px;" />
          @endif
          <div style="float: left; margin-top: 4px;">
            <div class="brand-title"><span class="brand-accent">{{ config('company.name', 'SwiDhaagha') }}</span></div>
            <div class="tagline">{{ config('company.tagline', 'Professional Ladies Tailoring Services') }}</div>
          </div>
          <div style="clear: both;"></div>
        </div>
        <div class="company-details">
          {{ config('company.address', '') }}<br>
          Phone: {{ config('company.phone', '') }}
          {{--
          @if(config('company.gstin'))
            &nbsp;|&nbsp; GSTIN: {{ config('company.gstin') }}
          @endif
          --}}
        </div>
      </td>
      <td style="text-align: right; width: 40%;">
        <div class="invoice-title">INVOICE</div>
        <div class="invoice-meta">
          Invoice No: <span class="meta-value">{{ $invoice->invoice_number }}</span><br>
          Date: <span class="meta-value">{{ \Carbon\Carbon::parse($invoice->generated_at)->format('j F Y') }}</span><br>
          @if($paymentMode)
            Mode: <span class="meta-value" style="text-transform: uppercase;">{{ str_replace('_', ' ', $paymentMode) }}</span><br>
          @endif
          <div style="margin-top: 4px;">
            @if($statusVal === 'paid')
              <span class="badge badge-paid">Paid</span>
            @else
              <span class="badge badge-unpaid">Pending</span>
            @endif
          </div>
        </div>
      </td>
    </tr>
  </table>

  {{-- CUSTOMER INFO & ORDER INFO --}}
  <table class="layout-table" style="margin-bottom: 20px;">
    <tr>
      <td style="width: 48%;">
        <div class="details-box">
          <div class="section-title">Bill To</div>
          <div class="details-name">{{ $order->customer->name ?? 'Customer' }}</div>
          <div class="details-text">
            {{ $order->delivery_address }}
            @if($order->customer->email)
              <div style="margin-top: 4px;">Email: {{ $order->customer->email }}</div>
            @endif
            @if($order->customer->phone)
              <div style="margin-top: 4px; font-weight: bold;">Phone: {{ $order->customer->phone }}</div>
            @endif
          </div>
        </div>
      </td>
      <td style="width: 4%;"></td>
      <td style="width: 48%;">
        <div class="details-box">
          <div class="section-title">Order Details</div>
          <div class="details-name">Order #{{ $order->order_number }}</div>
          <div class="details-text">
            Order Date: {{ \Carbon\Carbon::parse($order->created_at)->format('j F Y') }}
            @if($order->delivery_date)
              <div style="margin-top: 4px; font-weight: bold;">Expected Delivery: {{ \Carbon\Carbon::parse($order->delivery_date)->format('j F Y') }}</div>
            @endif
          </div>
        </div>
      </td>
    </tr>
  </table>

  {{-- ITEMS TABLE --}}
  <div class="section" style="margin-bottom: 15px;">
    <div class="section-title" style="margin-bottom: 6px;">Items & Stitching Details</div>
    <table class="items-table">
      <thead>
        <tr>
          <th style="width: 40px; text-align: center;">#</th>
          <th style="text-align: left;">Garment</th>
          <th style="text-align: right; width: 120px;">Unit Price</th>
          <th style="text-align: center; width: 60px;">Qty</th>
          <th style="text-align: right; width: 120px;">Total</th>
        </tr>
      </thead>
      <tbody>
        @foreach($order->items as $i => $item)
        @php
          $unitPrice = $item->price ?? $item->garment?->price ?? 0;
          $qty = $item->quantity ?? 1;
          $totalItemPrice = $unitPrice * $qty;
          $subtotal += $totalItemPrice;
        @endphp
        <tr>
          <td style="text-align: center; font-weight: bold; color: #64748b;">{{ $i + 1 }}</td>
          <td>
            <div class="item-name">{{ $item->garment_name ?? $item->garment?->name ?? 'Garment' }} <span style="font-size: 7.5px; font-weight: normal; color: #64748b;">(SAC: 9988)</span></div>
            @if($item->provided_own_fabric)
              <div class="item-meta" style="color: #b45309; font-weight: bold;">🧵 Customer Fabric Provided</div>
            @endif
            @if($item->custom_notes)
              <div class="item-note">Note: {{ $item->custom_notes }}</div>
            @endif
          </td>
          <td style="text-align: right; font-weight: bold;">
            &#8377;{{ number_format($unitPrice, 2) }}
          </td>
          <td style="text-align: center; font-weight: bold;">
            {{ $qty }}
          </td>
          <td style="text-align: right; font-weight: bold; color: #0f172a;">
            &#8377;{{ number_format($totalItemPrice, 2) }}
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  @php
    $invoiceSubtotal = $invoice->subtotal ?? 0;
    $gstAmount = $invoice->gst_amount ?? 0;
    $advancePaid = $invoice->advance_paid ?? 0;
    $visitCharge = $invoice->visit_charge ?? 0;
    $grossTotal = $invoice->total_amount ?? 0;
    
    $isPaid = $statusVal === 'paid';
    $balancePaid = $isPaid ? max(0, $grossTotal - $advancePaid) : 0;
    $grandTotal = $isPaid ? 0 : max(0, $grossTotal - $advancePaid);
  @endphp

  {{-- TOTALS & TERMS --}}
  <table class="layout-table" style="margin-top: 15px;">
    <tr>
      <td style="width: 55%; padding-right: 25px;">
        <div style="font-size: 9px; color: #64748b; line-height: 1.5;">
          <div style="font-weight: bold; color: #0f172a; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px;">Terms & Conditions</div>
          Thank you for choosing us! Please verify the garments upon receipt. All customizations are crafted as per instructions. This is a system-generated document requiring no physical signature.
        </div>
      </td>
      <td style="width: 45%;">
        <table class="totals-table">
          <tr>
            <td>Subtotal</td>
            <td class="amount">&#8377;{{ number_format($invoiceSubtotal, 2) }}</td>
          </tr>
          @if($gstAmount > 0)
          <tr>
            <td>CGST ({{ number_format($invoice->gst_rate / 2, 1) }}%)</td>
            <td class="amount">&#8377;{{ number_format($gstAmount / 2, 2) }}</td>
          </tr>
          <tr>
            <td>SGST ({{ number_format($invoice->gst_rate / 2, 1) }}%)</td>
            <td class="amount">&#8377;{{ number_format($gstAmount / 2, 2) }}</td>
          </tr>
          @endif
          @if($visitCharge > 0)
          <tr>
            <td>Visit Charge / Fees</td>
            <td class="amount">&#8377;{{ number_format($visitCharge, 2) }}</td>
          </tr>
          @endif
          <tr style="border-top: 1px solid #e2e8f0; font-weight: bold;">
            <td>Gross Total</td>
            <td class="amount">&#8377;{{ number_format($grossTotal, 2) }}</td>
          </tr>
          @if($advancePaid > 0)
          <tr class="discount-row">
            <td>{{ $advancePaid >= $grossTotal ? 'Full Amount Paid' : 'Advance / Deposit Paid' }}</td>
            <td class="amount" style="color: #b45309;">- &#8377;{{ number_format($advancePaid, 2) }}</td>
          </tr>
          @endif
          @if($isPaid && $balancePaid > 0)
          <tr class="paid-row">
            <td>Balance Paid</td>
            <td class="amount" style="color: #166534;">- &#8377;{{ number_format($balancePaid, 2) }}</td>
          </tr>
          @endif
          <tr class="grand-total-row">
            <td>Balance Due</td>
            <td class="amount" style="{{ $isPaid ? 'color: #166534;' : 'color: #c8945e;' }}">
              &#8377;{{ number_format($grandTotal, 2) }}
              @if($isPaid)
                <div style="font-size: 8px; font-weight: bold; color: #166534; margin-top: 2px; text-transform: uppercase;">Paid In Full</div>
              @endif
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>

  {{-- TRANSACTIONS HISTORY --}}
  @if($invoice->transactions->count() > 0)
  <div class="section" style="margin-top: 20px; margin-bottom: 20px;">
    <div class="section-title" style="margin-bottom: 6px; color: #c8945e; font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.8px;">Payment Ledger History</div>
    <table class="items-table" style="font-size: 8.5px;">
      <thead>
        <tr>
          <th style="text-align: left; padding: 6px 8px;">Txn Reference</th>
          <th style="text-align: left; width: 120px; padding: 6px 8px;">Payment Mode</th>
          <th style="text-align: right; width: 100px; padding: 6px 8px;">Amount</th>
          <th style="text-align: center; width: 100px; padding: 6px 8px;">Status</th>
          <th style="text-align: right; width: 120px; padding: 6px 8px;">Date</th>
        </tr>
      </thead>
      <tbody>
        @foreach($invoice->transactions as $txn)
        <tr>
          <td style="font-weight: bold; padding: 6px 8px;">{{ $txn->transaction_number ?? 'Pending Reference' }}</td>
          <td style="text-transform: uppercase; padding: 6px 8px;">{{ str_replace('_', ' ', $txn->payment_mode) }}</td>
          <td style="text-align: right; font-weight: bold; padding: 6px 8px;">&#8377;{{ number_format($txn->amount, 2) }}</td>
          <td style="text-align: center; text-transform: uppercase; font-weight: bold; padding: 6px 8px; color: {{ $txn->status === 'successful' ? '#166534' : ($txn->status === 'pending' ? '#b45309' : '#991b1b') }};">
            {{ $txn->status }}
          </td>
          <td style="text-align: right; padding: 6px 8px;">{{ \Carbon\Carbon::parse($txn->created_at)->format('j F Y h:i A') }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @endif

  {{-- FOOTER --}}
  <div class="footer">
    Thank you for choosing SwiDhaagha!
  </div>

</body>
</html>