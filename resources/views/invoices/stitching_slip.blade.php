<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    *{
      margin:0;
      padding:0;
      box-sizing:border-box;
    }

    body{
      font-family:DejaVu Sans,sans-serif;
      padding:30px;
      color:#333;
    }

    .header{
      margin-bottom:25px;
      border-bottom:3px solid #1a1a2e;
      padding-bottom:15px;
    }

    .brand{
      font-size:24px;
      font-weight:bold;
      color:#1a1a2e;
      text-transform:uppercase;
      letter-spacing:1px;
    }

    .slip-title{
      text-align:right;
      font-size:18px;
      font-weight:bold;
      color:#e94560;
      text-transform:uppercase;
      letter-spacing:1.5px;
    }

    .meta-grid {
      width: 100%;
      margin-bottom: 25px;
    }

    .meta-grid td {
      border: none;
      padding: 5px 0;
      font-size: 13px;
      vertical-align: top;
    }

    .meta-label {
      font-weight: bold;
      color: #555;
      width: 130px;
    }

    .section{
      margin-bottom:25px;
    }

    .section-title{
      font-size:13px;
      font-weight:bold;
      color:#1a1a2e;
      margin-bottom:8px;
      text-transform:uppercase;
      letter-spacing:1px;
      border-bottom:1px solid #1a1a2e;
      padding-bottom:4px;
    }

    table.data-table{
      width:100%;
      border-collapse:collapse;
      font-size:12px;
      margin-top: 5px;
    }

    table.data-table th{
      background:#1a1a2e;
      color:white;
      padding:8px 10px;
      text-align:left;
      font-size:11px;
      text-transform:uppercase;
    }

    table.data-table td{
      padding:8px 10px;
      border:1px solid #ddd;
    }

    tr:nth-child(even) td{
      background:#f8f9fa;
    }

    .notes-box {
      background: #fdf6e2;
      border-left: 4px solid #f5b041;
      padding: 12px;
      font-size: 13px;
      color: #5d4037;
      margin-top: 5px;
      line-height: 1.4;
    }

    .footer{
      margin-top:35px;
      text-align:center;
      font-size:11px;
      color:#888;
      border-top:1px solid #eee;
      padding-top:12px;
    }
  </style>
</head>

<body>

  {{-- HEADER --}}
  <table style="width: 100%;" class="header">
    <tr>
      <td>
        <div style="min-height: 54px; margin-bottom: 6px;">
          @if(file_exists(public_path('images/logo.png')))
            <img src="{{ public_path('images/logo.png') }}" style="float: left; height: 50px; width: 50px; border-radius: 8px; margin-right: 12px;" />
          @endif
          <div style="float: left; margin-top: 4px;">
            <div class="brand">{{ config('company.name', 'SwiDhaagha Tailors') }}</div>
            <div style="font-size:11px; color:#666; margin-top:2px;">
              {{ config('company.tagline', 'Premium Custom Stitching Job Slip') }}
            </div>
          </div>
          <div style="clear: both;"></div>
        </div>
        <div style="font-size:10px; color:#999; margin-top:6px;">
          Phone: {{ config('company.phone', '') }}
        </div>
      </td>
      <td style="text-align: right;">
        <div class="slip-title">Stitching Job Card</div>
        <div style="font-size:12px; color:#555; font-weight: bold; margin-top:4px;">
          Order #{{ $order->order_number }}
        </div>
      </td>
    </tr>
  </table>

  {{-- INFO BLOCKS (Tailor Workshop Reference - Customer personal details and pricing hidden) --}}
  <table class="meta-grid">
    <tr>
      <td class="meta-label">Job Reference:</td>
      <td style="font-weight: bold; color: #1a1a2e;">#{{ $order->order_number }}</td>
      <td class="meta-label">Order Status:</td>
      <td style="text-transform: uppercase; font-weight: bold; color: #e94560;">{{ $order->status }}</td>
    </tr>
    <tr>
      <td class="meta-label">Target Delivery Date:</td>
      <td style="font-weight: bold;">
        {{ $order->delivery_date ? \Carbon\Carbon::parse($order->delivery_date)->format('j F Y') : 'Standard Workshop Queue' }}
      </td>
      <td class="meta-label">Assigned Tailor:</td>
      <td style="font-weight: bold; color: #e94560; font-size: 13px;">
        {{ isset($selectedItem) ? ($selectedItem->tailor?->name ?? 'Unassigned') : 'Workshop' }}
      </td>
    </tr>
  </table>

  {{-- ITEMS SECTION --}}
  <div class="section">
    <div class="section-title">Garments to Stitch</div>
    <table class="data-table">
      <thead>
        <tr>
          <th style="width: 40px;">#</th>
          <th>Garment item / Style</th>
          <th style="width: 60px; text-align: center;">Qty</th>
          <th>Assigned Tailor</th>
          <th style="width: 120px;">Stitching Status</th>
        </tr>
      </thead>
      <tbody>
        @foreach($order->items as $idx => $item)
        <tr>
          <td style="text-align: center;">{{ $idx + 1 }}</td>
          <td style="font-weight: bold;">{{ $item->garment_name ?? $item->garment?->name ?? 'Custom Item' }}</td>
          <td style="text-align: center;">{{ $item->quantity }}</td>
          <td>{{ $item->tailor?->name ?? 'Unassigned' }}</td>
          <td style="text-transform: uppercase; font-weight: bold; font-size: 10px; color: #555;">
            {{ $item->status ?? 'pending' }}
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  {{-- MEASUREMENTS SECTION --}}
  <div class="section">
    <div class="section-title">
      @if(isset($selectedItem))
        Custom Measurement Specifications — {{ $selectedItem->garment_name ?? 'Garment' }}
      @else
        Custom Measurement Specifications
      @endif
    </div>

    @if(count($order->measurements) > 0)
      @if(isset($selectedItem) || $order->items->count() <= 1)
        {{-- Single item slip or single-item order --}}
        <table class="data-table">
          <thead>
            <tr>
              <th style="width: 35px; text-align: center;">#</th>
              <th>Measurement Parameter</th>
              <th style="width: 140px; text-align: center;">Specification Value</th>
              <th>Tailor Notes / Instructions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($order->measurements as $idx => $m)
            <tr>
              <td style="text-align: center; color: #777;">{{ $idx + 1 }}</td>
              <td style="font-weight: bold; color: #1a1a2e;">{{ ucwords(str_replace('_', ' ', $m->field_name)) }}</td>
              <td style="text-align: center; font-size: 13px; font-weight: bold; color: #1a1a2e; background-color: #fcfcfc;">
                {{ $m->value }}
              </td>
              <td style="font-size: 11px; color: #444;">
                {{ $m->note ?: '-' }}
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      @else
        {{-- Multiple items in full order slip: Group clearly by garment item so tailor can easily identify each cloth --}}
        @php
          $matchedMeasurementIds = [];
        @endphp

        @foreach($order->items as $itemIdx => $item)
          @php
            $itemMeasurements = $order->measurements->filter(function($m) use ($item) {
              return $m->order_item_id ? ((int)$m->order_item_id === (int)$item->id) : ((int)$m->garment_id === (int)$item->garment_id);
            });
            foreach ($itemMeasurements as $im) {
              $matchedMeasurementIds[] = $im->id;
            }
          @endphp

          <div style="margin-top: 14px; margin-bottom: 6px; padding: 6px 10px; background: #f0f4f8; border-left: 4px solid #1a1a2e; font-size: 12px; font-weight: bold; color: #1a1a2e;">
            Garment #{{ $itemIdx + 1 }}: {{ $item->garment_name ?? $item->garment?->name ?? 'Custom Garment' }} 
            (Qty: {{ $item->quantity }})
            @if($item->tailor)
              <span style="font-weight: normal; color: #666; margin-left: 10px;">Assigned Tailor: {{ $item->tailor->name }}</span>
            @endif
          </div>

          @if($itemMeasurements->count() > 0)
            <table class="data-table" style="margin-bottom: 14px;">
              <thead>
                <tr>
                  <th style="width: 35px; text-align: center;">#</th>
                  <th>Measurement Parameter</th>
                  <th style="width: 140px; text-align: center;">Specification Value</th>
                  <th>Tailor Notes / Instructions</th>
                </tr>
              </thead>
              <tbody>
                @foreach($itemMeasurements as $mIdx => $m)
                <tr>
                  <td style="text-align: center; color: #777;">{{ $mIdx + 1 }}</td>
                  <td style="font-weight: bold; color: #1a1a2e;">{{ ucwords(str_replace('_', ' ', $m->field_name)) }}</td>
                  <td style="text-align: center; font-size: 13px; font-weight: bold; color: #1a1a2e; background-color: #fcfcfc;">
                    {{ $m->value }}
                  </td>
                  <td style="font-size: 11px; color: #444;">
                    {{ $m->note ?: '-' }}
                  </td>
                </tr>
                @endforeach
              </tbody>
            </table>
          @else
            <div class="notes-box" style="margin-bottom: 12px; background: #fafafa; border-left: 4px solid #ccc; color: #888; font-size: 11px;">
              No custom measurements recorded for this garment item.
            </div>
          @endif
        @endforeach

        {{-- Fallback for any unlinked measurements --}}
        @php
          $remainingMeasurements = $order->measurements->filter(fn($m) => !in_array($m->id, $matchedMeasurementIds));
        @endphp
        @if($remainingMeasurements->count() > 0)
          <div style="margin-top: 14px; margin-bottom: 6px; padding: 6px 10px; background: #fff8e1; border-left: 4px solid #ffa000; font-size: 12px; font-weight: bold; color: #5d4037;">
            General / Additional Measurements
          </div>
          <table class="data-table" style="margin-bottom: 14px;">
            <thead>
              <tr>
                <th style="width: 35px; text-align: center;">#</th>
                <th>Measurement Parameter</th>
                <th style="width: 140px; text-align: center;">Specification Value</th>
                <th>Tailor Notes / Instructions</th>
              </tr>
            </thead>
            <tbody>
              @foreach($remainingMeasurements as $rIdx => $rm)
              <tr>
                <td style="text-align: center; color: #777;">{{ $rIdx + 1 }}</td>
                <td style="font-weight: bold; color: #1a1a2e;">{{ ucwords(str_replace('_', ' ', $rm->field_name)) }}</td>
                <td style="text-align: center; font-size: 13px; font-weight: bold; color: #1a1a2e; background-color: #fcfcfc;">
                  {{ $rm->value }}
                </td>
                <td style="font-size: 11px; color: #444;">
                  {{ $rm->note ?: '-' }}
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        @endif
      @endif
    @else
      <div class="notes-box" style="background: #f4f6f7; border-left: 4px solid #bdc3c7; color: #7f8c8d;">
        No custom sizes recorded for this order. Tailor should follow standard sample garment measurement.
      </div>
    @endif
  </div>

  {{-- SPECIAL INSTRUCTIONS --}}
  @if(isset($selectedItem) && $selectedItem->custom_notes)
  <div class="section">
    <div class="section-title">Garment Custom Instructions</div>
    <div class="notes-box" style="border-left: 4px solid #e94560; background: #fff5f5; color: #721c24;">
      {{ $selectedItem->custom_notes }}
    </div>
  </div>
  @endif

  @if($order->notes)
  <div class="section">
    <div class="section-title">Order Notes & Overall Instructions</div>
    <div class="notes-box">
      {{ $order->notes }}
    </div>
  </div>
  @endif

  {{-- FOOTER --}}
  <div class="footer">
    SwiDhaagha Tailoring ERP System
    |
    Stitching Job Card for Internal Reference Only
    |
    Printed on: {{ \Carbon\Carbon::now()->format('j F Y, h:i A') }}
  </div>

</body>
</html>
