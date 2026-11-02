<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SwiDhaagha - 15-Minute Activity Alert</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f4f5f8;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #1e293b;
            -webkit-font-smoothing: antialiased;
        }
        .container {
            max-width: 650px;
            margin: 25px auto;
            background: #ffffff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.06);
            border: 1px solid #e2e8f0;
        }
        .header {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4338ca 100%);
            padding: 32px 30px;
            text-align: center;
            color: #ffffff;
        }
        .header h1 {
            margin: 0 0 6px 0;
            font-size: 26px;
            font-weight: 900;
            letter-spacing: -0.5px;
        }
        .header p {
            margin: 0;
            font-size: 13px;
            color: #c7d2fe;
            font-weight: 500;
        }
        .badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(8px);
            padding: 6px 14px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 14px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #ffffff;
        }
        .content {
            padding: 28px 28px 32px;
        }
        .stats-grid {
            display: table;
            width: 100%;
            margin-bottom: 24px;
        }
        .stat-card {
            display: table-cell;
            width: 50%;
            padding: 16px;
            background: #f8fafc;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            vertical-align: top;
        }
        .stat-card:first-child {
            padding-right: 12px;
        }
        .stat-card:last-child {
            margin-left: 10px;
        }
        .stat-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 800;
            color: #64748b;
            margin-bottom: 4px;
        }
        .stat-value {
            font-size: 26px;
            font-weight: 900;
            color: #0f172a;
            margin: 0;
            line-height: 1.2;
        }
        .stat-sub {
            font-size: 12px;
            color: #059669;
            font-weight: 700;
            margin-top: 3px;
        }
        .section-title {
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
            margin: 28px 0 12px 0;
            display: flex;
            align-items: center;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 8px;
        }
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 12px;
        }
        th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 700;
            text-align: left;
            padding: 10px 12px;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
        }
        th:first-child { border-top-left-radius: 10px; border-bottom-left-radius: 10px; }
        th:last-child { border-top-right-radius: 10px; border-bottom-right-radius: 10px; }
        td {
            padding: 12px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            vertical-align: middle;
        }
        tr:last-child td {
            border-bottom: none;
        }
        .tag {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .tag-green { background: #dcfce7; color: #15803d; }
        .tag-amber { background: #fef3c7; color: #b45309; }
        .tag-indigo { background: #e0e7ff; color: #4338ca; }
        .cta-container {
            text-align: center;
            margin: 30px 0 10px;
            padding-top: 20px;
            border-top: 1px dashed #cbd5e1;
        }
        .cta-btn {
            display: inline-block;
            background: #4338ca;
            color: #ffffff !important;
            padding: 12px 28px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(67, 56, 202, 0.25);
            transition: all 0.2s;
        }
        .footer {
            background: #f8fafc;
            padding: 20px;
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>🧵 SwiDhaagha</h1>
            <p>Aap ke ghar ka tailor • Admin Notification System</p>
            <div class="badge">
                ⏱️ Activity in Last {{ $windowMinutes == 60 ? '1 Hour' : ($windowMinutes . ' Minutes') }}
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Key Stats -->
            <table class="stats-grid" style="border: none;">
                <tr>
                    <td class="stat-card" style="width: 50%; padding-right: 12px;">
                        <div class="stat-label">📦 New Orders</div>
                        <div class="stat-value">{{ $orderCount }}</div>
                        <div class="stat-sub">
                            @if($totalOrderAmount > 0)
                                Total: ₹{{ number_format($totalOrderAmount, 2) }}
                            @else
                                No direct payment
                            @endif
                        </div>
                    </td>
                    <td class="stat-card" style="width: 50%; padding-left: 12px;">
                        <div class="stat-label">📅 New Appointments</div>
                        <div class="stat-value">{{ $appointmentCount }}</div>
                        <div class="stat-sub" style="color: #6366f1;">Doorstep Visits</div>
                    </td>
                </tr>
            </table>

            <!-- Orders Table (If any) -->
            @if($orderCount > 0)
                <div class="section-title">
                    <span>📦 New Orders Received ({{ $orderCount }})</span>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($orders as $order)
                                <tr>
                                    <td style="font-weight: 800; color: #4338ca;">
                                        {{ $order->order_number ?? ('#' . $order->id) }}
                                    </td>
                                    <td>
                                        <div style="font-weight: 700;">{{ $order->customer?->name ?? 'Customer' }}</div>
                                        <div style="font-size: 11px; color: #64748b;">{{ $order->customer?->phone ?? 'No phone' }}</div>
                                    </td>
                                    <td>{{ $order->items?->count() ?? 1 }} item(s)</td>
                                    <td style="font-weight: 800; color: #059669;">
                                        ₹{{ number_format($order->total_amount, 2) }}
                                    </td>
                                    <td>
                                        <span class="tag tag-amber">{{ strtoupper($order->status ?? 'NEW') }}</span>
                                    </td>
                                    <td style="color: #64748b; font-size: 11px;">
                                        {{ $order->created_at ? $order->created_at->format('h:i A') : 'N/A' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <!-- Appointments Table (If any) -->
            @if($appointmentCount > 0)
                <div class="section-title">
                    <span>📅 New Appointments Booked ({{ $appointmentCount }})</span>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Appt #</th>
                                <th>Customer</th>
                                <th>Date & Time Slot</th>
                                <th>Type</th>
                                <th>City / Area</th>
                                <th>Booked At</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($appointments as $appt)
                                <tr>
                                    <td style="font-weight: 800; color: #4338ca;">
                                        #{{ $appt->id }}
                                    </td>
                                    <td>
                                        <div style="font-weight: 700;">{{ $appt->customer?->name ?? 'Customer' }}</div>
                                        <div style="font-size: 11px; color: #64748b;">{{ $appt->customer?->phone ?? 'No phone' }}</div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 700;">{{ $appt->appointment_date ?? 'N/A' }}</div>
                                        <div style="font-size: 11px; color: #64748b;">{{ $appt->appointment_time ?? 'Flexible' }}</div>
                                    </td>
                                    <td>
                                        <span class="tag tag-indigo">
                                            {{ $appt->measurement_type === 'existing_garment' ? 'Old Garment' : 'Onsite Visit' }}
                                        </span>
                                    </td>
                                    <td style="color: #475569;">
                                        {{ $appt->city ?? ($appt->pincode ? ('Pin: ' . $appt->pincode) : 'Default') }}
                                    </td>
                                    <td style="color: #64748b; font-size: 11px;">
                                        {{ $appt->created_at ? $appt->created_at->format('h:i A') : 'N/A' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <!-- Call to Action -->
            <div class="cta-container">
                <a href="{{ env('FRONTEND_URL', 'http://localhost:5173') }}" class="cta-btn">
                    🚀 Open Admin Dashboard
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p style="margin: 0 0 4px 0;">This is an automated {{ $windowMinutes == 60 ? '1-hour' : ($windowMinutes . '-minute') }} scheduled digest sent to SwiDhaagha Administration.</p>
            <p style="margin: 0;">Generated on <strong>{{ $generatedAt }}</strong> • SwiDhaagha Portal</p>
        </div>
    </div>
</body>
</html>
