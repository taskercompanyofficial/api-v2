<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Job Sheet - {{ $workOrder->work_order_number ?? 'Tasker Company' }}</title>
    <style>
        @page {
            size: A4;
            margin: 8mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            font-size: 11px;
            line-height: 1.3;
            color: #333;
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: #f0f0f0;
        }

        .page {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .container {
            padding: 10mm;
        }

        /* Print Button */
        .print-controls {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            display: flex;
            gap: 10px;
        }

        .print-btn {
            padding: 12px 24px;
            font-size: 14px;
            font-weight: bold;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .print-btn-primary {
            background: #1a56db;
            color: white;
        }

        .print-btn-secondary {
            background: #e5e7eb;
            color: #333;
        }

        @media print {
            body { background: white; }
            .print-controls { display: none !important; }
            .page { box-shadow: none; }
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #b8976b;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }

        .logo-img {
            height: 45px;
            width: auto;
        }

        .header-title {
            font-size: 24px;
            font-weight: bold;
            color: #b8976b;
            text-transform: uppercase;
            letter-spacing: 3px;
        }

        .header-info {
            text-align: right;
            font-size: 11px;
        }

        .job-number {
            font-size: 13px;
            font-weight: bold;
            color: #b8976b;
            background: #f5f0e8;
            padding: 4px 12px;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 4px;
        }

        /* Grid Layout */
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 10px;
        }

        .grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px;
            margin-bottom: 6px;
        }

        /* Section */
        .section {
            border: 1px solid #999;
            padding: 8px;
            background: #fafafa;
            border-radius: 4px;
        }

        .section-title {
            font-weight: bold;
            font-size: 11px;
            color: #b8976b;
            border-bottom: 1px solid #999;
            padding-bottom: 4px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        /* Fields */
        .field {
            display: flex;
            margin-bottom: 6px;
            font-size: 11px;
        }

        .field-label {
            font-weight: bold;
            width: 35%;
            color: #444;
        }

        .field-value {
            flex: 1;
            border-bottom: 1px dotted #888;
            min-height: 16px;
            padding-left: 4px;
        }

        .inline-fields {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .inline-field {
            display: flex;
            font-size: 11px;
            margin-bottom: 6px;
        }

        .inline-field .field-label {
            width: 45%;
        }

        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            margin-bottom: 8px;
        }

        table th {
            background: #b8976b;
            color: white;
            padding: 6px;
            text-align: left;
            font-weight: bold;
        }

        table td {
            border: 1px solid #999;
            padding: 5px 6px;
            height: 24px;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* Remarks */
        .remarks-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 8px;
        }

        .remarks-box {
            border: 1px solid #999;
            padding: 6px;
            min-height: 55px;
            background: white;
            border-radius: 4px;
        }

        .remarks-title {
            font-weight: bold;
            font-size: 10px;
            color: #b8976b;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .remarks-content {
            font-size: 9px;
            color: #333;
        }

        /* Time Tracking */
        .time-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-top: 6px;
        }

        .time-box {
            text-align: center;
            padding: 6px;
            border: 1px solid #999;
            background: white;
            border-radius: 4px;
        }

        .time-label {
            font-size: 9px;
            color: #666;
            margin-bottom: 3px;
        }

        .time-value {
            font-size: 12px;
            font-weight: bold;
            min-height: 16px;
            border-bottom: 1px dotted #666;
        }

        /* Payment Section */
        .payment-section {
            border: 1px solid #999;
            padding: 8px;
            background: #fafafa;
            border-radius: 4px;
            margin-bottom: 8px;
        }

        .payment-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 10px;
        }

        /* Signatures */
        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            margin-top: 8px;
        }

        .signature-box {
            border: 1px solid #999;
            padding: 8px;
            text-align: center;
            min-height: 70px;
            border-radius: 4px;
            background: white;
        }

        .signature-box strong {
            font-size: 10px;
            color: #b8976b;
            text-transform: uppercase;
        }

        .signature-line {
            border-top: 1px solid #333;
            margin-top: 35px;
            padding-top: 4px;
            font-size: 9px;
        }

        /* Footer */
        .footer {
            margin-top: 8px;
            padding-top: 6px;
            border-top: 2px solid #b8976b;
            font-size: 10px;
            text-align: center;
            color: #666;
        }

        .footer strong { color: #b8976b; }

        /* Checkbox */
        .checkbox-row {
            display: flex;
            gap: 14px;
            font-size: 10px;
            margin-top: 6px;
            flex-wrap: wrap;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 3px;
        }

        .checkbox {
            width: 12px;
            height: 12px;
            border: 1px solid #333;
            display: inline-block;
        }

        .checkbox.checked {
            background: #b8976b;
            position: relative;
        }

        .checkbox.checked::after {
            content: '✓';
            color: white;
            font-size: 10px;
            position: absolute;
            top: -1px;
            left: 1px;
        }

        /* Terms */
        .terms {
            font-size: 8px;
            color: #666;
            margin-top: 6px;
            padding: 5px;
            background: #f9fafb;
            border-radius: 3px;
            border: 1px solid #999;
        }

        .terms-title {
            font-weight: bold;
            color: #b8976b;
        }
    </style>
</head>

<body>
    <div class="print-controls">
        <button class="print-btn print-btn-primary" onclick="window.print()">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg>
            Print
        </button>
        <button class="print-btn print-btn-secondary" onclick="window.close()">Close</button>
    </div>

    <div class="page">
        <div class="container">
            <!-- Header -->
            <div class="header">
                <img src="/images/tasker-logo.png" alt="Tasker Company" class="logo-img">
                <div class="header-title">Job Sheet</div>
                <div class="header-info">
                    <div class="job-number">JOB # {{ $workOrder->work_order_number ?? '______________' }}</div>
                    <div>Date: {{ $generatedDate ?? now()->format('d/m/Y') }}</div>
                </div>
            </div>

            <!-- Customer & Job Details -->
            <div class="grid">
                <div class="section">
                    <div class="section-title">Customer Details</div>
                    <div class="field">
                        <span class="field-label">Name:</span>
                        <span class="field-value">{{ $workOrder->customer->name ?? '' }}</span>
                    </div>
                    <div class="inline-fields">
                        <div class="inline-field">
                            <span class="field-label">Phone:</span>
                            <span class="field-value">{{ $workOrder->customer->phone ?? '' }}</span>
                        </div>
                        <div class="inline-field">
                            <span class="field-label">WhatsApp:</span>
                            <span class="field-value">{{ $workOrder->customer->whatsapp ?? '' }}</span>
                        </div>
                    </div>
                    <div class="field">
                        <span class="field-label">Address:</span>
                        <span class="field-value">
                            @if($workOrder->address)
                                {{ $workOrder->address->address_line_1 }}{{ $workOrder->address->address_line_2 ? ', '.$workOrder->address->address_line_2 : '' }}
                            @endif
                        </span>
                    </div>
                    <div class="inline-fields">
                        <div class="inline-field">
                            <span class="field-label">City:</span>
                            <span class="field-value">{{ $workOrder->city->name ?? $workOrder->address->city ?? '' }}</span>
                        </div>
                        <div class="inline-field">
                            <span class="field-label">Area:</span>
                            <span class="field-value">{{ $workOrder->address->area ?? '' }}</span>
                        </div>
                    </div>
                </div>

                <div class="section">
                    <div class="section-title">Job Details</div>
                    <div class="inline-fields">
                        <div class="inline-field">
                            <span class="field-label">Work Order #:</span>
                            <span class="field-value">{{ $workOrder->work_order_number ?? '' }}</span>
                        </div>
                        <div class="inline-field">
                            <span class="field-label">Priority:</span>
                            <span class="field-value">{{ strtoupper($workOrder->priority ?? '') }}</span>
                        </div>
                    </div>
                    <div class="inline-fields">
                        <div class="inline-field">
                            <span class="field-label">Status:</span>
                            <span class="field-value">{{ $workOrder->status->name ?? '' }}</span>
                        </div>
                        <div class="inline-field">
                            <span class="field-label">Branch:</span>
                            <span class="field-value">{{ $workOrder->branch->name ?? '' }}</span>
                        </div>
                    </div>
                    <div class="field">
                        <span class="field-label">Assigned To:</span>
                        <span class="field-value">
                            @if($workOrder->assignedTo)
                                {{ $workOrder->assignedTo->first_name }} {{ $workOrder->assignedTo->last_name }}
                            @endif
                        </span>
                    </div>
                    <div class="inline-fields">
                        <div class="inline-field">
                            <span class="field-label">Appt. Date:</span>
                            <span class="field-value">{{ $workOrder->appointment_date ? $workOrder->appointment_date->format('d/m/Y') : '' }}</span>
                        </div>
                        <div class="inline-field">
                            <span class="field-label">Appt. Time:</span>
                            <span class="field-value">{{ $workOrder->appointment_time ?? '' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Product Information -->
            <div class="section" style="margin-bottom: 10px;">
                <div class="section-title">Product Information</div>
                <div class="grid-3">
                    <div class="field"><span class="field-label">Brand:</span><span class="field-value">{{ $workOrder->brand->name ?? '' }}</span></div>
                    <div class="field"><span class="field-label">Category:</span><span class="field-value">{{ $workOrder->category->name ?? '' }}</span></div>
                    <div class="field"><span class="field-label">Service:</span><span class="field-value">{{ $workOrder->service->name ?? '' }}</span></div>
                </div>
                <div class="grid-3">
                    <div class="field"><span class="field-label">Product:</span><span class="field-value">{{ $workOrder->product->name ?? '' }}</span></div>
                    <div class="field"><span class="field-label">Indoor Model:</span><span class="field-value">{{ $workOrder->product_indoor_model ?? '' }}</span></div>
                    <div class="field"><span class="field-label">Outdoor Model:</span><span class="field-value">{{ $workOrder->product_outdoor_model ?? '' }}</span></div>
                </div>
                <div class="grid-3">
                    <div class="field"><span class="field-label">Indoor Serial:</span><span class="field-value">{{ $workOrder->indoor_serial_number ?? '' }}</span></div>
                    <div class="field"><span class="field-label">Outdoor Serial:</span><span class="field-value">{{ $workOrder->outdoor_serial_number ?? '' }}</span></div>
                    <div class="field"><span class="field-label">Purchase Date:</span><span class="field-value">{{ $workOrder->purchase_date ? $workOrder->purchase_date->format('d/m/Y') : '' }}</span></div>
                </div>
                <div class="checkbox-row">
                    <span class="checkbox-item"><span class="checkbox {{ $workOrder->is_warranty_case ? 'checked' : '' }}"></span> Warranty</span>
                    <span class="checkbox-item"><span class="checkbox {{ !$workOrder->is_warranty_case ? 'checked' : '' }}"></span> Non-Warranty</span>
                    <span class="checkbox-item"><span class="checkbox"></span> Extended Warranty</span>
                    <span class="checkbox-item"><span class="checkbox"></span> AMC</span>
                </div>
            </div>

            <!-- Time Tracking -->
            <div class="section" style="margin-bottom: 10px;">
                <div class="section-title">Service Time</div>
                <div class="time-grid">
                    <div class="time-box"><div class="time-label">Arrival</div><div class="time-value"></div></div>
                    <div class="time-box"><div class="time-label">Start</div><div class="time-value">{{ $workOrder->service_start_time ?? '' }}</div></div>
                    <div class="time-box"><div class="time-label">End</div><div class="time-value">{{ $workOrder->service_end_time ?? '' }}</div></div>
                    <div class="time-box"><div class="time-label">Duration</div><div class="time-value"></div></div>
                </div>
            </div>

            <!-- Services & Parts -->
            <div class="grid">
                <div>
                    <div class="section-title" style="margin-bottom: 4px; border-bottom: none; padding-bottom: 0;">Services</div>
                    <table>
                        <thead><tr><th style="width: 8%;">#</th><th>Service</th><th style="width: 25%;" class="text-right">Rs.</th></tr></thead>
                        <tbody>
                            @if($workOrder->services && $workOrder->services->count() > 0)
                                @foreach($workOrder->services as $index => $service)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $service->service_name ?? '' }}</td>
                                        <td class="text-right">{{ number_format($service->final_price ?? 0, 0) }}</td>
                                    </tr>
                                @endforeach
                                @for($i = $workOrder->services->count(); $i < 4; $i++)
                                    <tr><td>{{ $i + 1 }}</td><td></td><td class="text-right"></td></tr>
                                @endfor
                            @else
                                <tr><td>1</td><td></td><td class="text-right"></td></tr>
                                <tr><td>2</td><td></td><td class="text-right"></td></tr>
                                <tr><td>3</td><td></td><td class="text-right"></td></tr>
                                <tr><td>4</td><td></td><td class="text-right"></td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
                <div>
                    <div class="section-title" style="margin-bottom: 4px; border-bottom: none; padding-bottom: 0;">Parts Used</div>
                    <table>
                        <thead><tr><th style="width: 8%;">#</th><th>Part</th><th style="width: 12%;" class="text-center">Qty</th><th style="width: 25%;" class="text-right">Rs.</th></tr></thead>
                        <tbody>
                            @if($workOrder->parts && $workOrder->parts->count() > 0)
                                @foreach($workOrder->parts as $index => $part)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $part->part->name ?? $part->part_name ?? '' }}</td>
                                        <td class="text-center">{{ $part->quantity ?? 1 }}</td>
                                        <td class="text-right">{{ number_format($part->total_price ?? 0, 0) }}</td>
                                    </tr>
                                @endforeach
                                @for($i = $workOrder->parts->count(); $i < 4; $i++)
                                    <tr><td>{{ $i + 1 }}</td><td></td><td class="text-center"></td><td class="text-right"></td></tr>
                                @endfor
                            @else
                                <tr><td>1</td><td></td><td class="text-center"></td><td class="text-right"></td></tr>
                                <tr><td>2</td><td></td><td class="text-center"></td><td class="text-right"></td></tr>
                                <tr><td>3</td><td></td><td class="text-center"></td><td class="text-right"></td></tr>
                                <tr><td>4</td><td></td><td class="text-center"></td><td class="text-right"></td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Payment -->
            <div class="payment-section">
                <div class="section-title" style="border-bottom: none; padding-bottom: 0; margin-bottom: 6px;">Payment</div>
                <div class="payment-grid">
                    <div class="field"><span class="field-label">Services:</span><span class="field-value">{{ number_format($workOrder->total_amount ?? 0, 0) }}</span></div>
                    <div class="field"><span class="field-label">Parts:</span><span class="field-value">{{ number_format($workOrder->parts_total ?? 0, 0) }}</span></div>
                    <div class="field"><span class="field-label">Discount:</span><span class="field-value">{{ number_format($workOrder->discount ?? 0, 0) }}</span></div>
                    <div class="field"><span class="field-label" style="color: #b8976b;"><strong>Total:</strong></span><span class="field-value" style="border-bottom: 2px solid #b8976b;">{{ number_format($workOrder->final_amount ?? 0, 0) }}</span></div>
                </div>
                <div class="checkbox-row">
                    <strong>Method:</strong>
                    <span class="checkbox-item"><span class="checkbox"></span> Cash</span>
                    <span class="checkbox-item"><span class="checkbox"></span> Card</span>
                    <span class="checkbox-item"><span class="checkbox"></span> Bank Transfer</span>
                    <span class="checkbox-item"><span class="checkbox"></span> Online</span>
                    <span class="checkbox-item"><span class="checkbox"></span> Pending</span>
                </div>
            </div>

            <!-- Remarks -->
            <div class="remarks-grid">
                <div class="remarks-box">
                    <div class="remarks-title">Customer Complaint</div>
                    <div class="remarks-content">{{ $workOrder->customer_description ?? '' }}</div>
                </div>
                <div class="remarks-box">
                    <div class="remarks-title">Defect Found</div>
                    <div class="remarks-content">{{ $workOrder->defect_description ?? '' }}</div>
                </div>
            </div>
            <div class="remarks-grid">
                <div class="remarks-box">
                    <div class="remarks-title">Technician Remarks</div>
                    <div class="remarks-content">{{ $workOrder->technician_remarks ?? '' }}</div>
                </div>
                <div class="remarks-box">
                    <div class="remarks-title">Work Done</div>
                    <div class="remarks-content">{{ $workOrder->service_description ?? '' }}</div>
                </div>
            </div>

            <!-- Signatures -->
            <div class="signatures">
                <div class="signature-box">
                    <strong>Technician</strong>
                    <div class="signature-line">Sign: __________________ | Name: {{ $workOrder->assignedTo ? $workOrder->assignedTo->first_name : '__________________' }}</div>
                </div>
                <div class="signature-box">
                    <strong>Supervisor</strong>
                    <div class="signature-line">Sign: __________________ | Name: __________________</div>
                </div>
                <div class="signature-box">
                    <strong>Customer</strong>
                    <div class="signature-line">Sign: __________________ | Name: {{ $workOrder->customer->name ?? '__________________' }}</div>
                </div>
            </div>

            <!-- Terms -->
            <div class="terms">
                <span class="terms-title">Terms & Conditions:</span>
                1. All services subject to Tasker Company policies.
                2. <strong>Service Warranty: 30 Days</strong> from service completion.
                3. <strong>Parts: No Warranty</strong> - All parts sold as-is.
                4. Customer must verify all charges and sign before technician leaves.
            </div>

            <!-- Footer -->
            <div class="footer">
                <strong>Tasker Company</strong> - HVACR Solutions | <strong>UAN: 0304-111-2717</strong> | www.taskercompany.com
            </div>
        </div>
    </div>
</body>

</html>
