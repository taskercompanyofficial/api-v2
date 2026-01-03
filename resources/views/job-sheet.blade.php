<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Job Sheet - {{ $workOrder->work_order_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            font-size: 10px;
            line-height: 1.3;
            color: #000;
        }

        .container {
            padding: 15mm;
        }

        /* Header */
        .header {
            display: table;
            width: 100%;
            margin-bottom: 10px;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 8px;
        }

        .header-left,
        .header-center,
        .header-right {
            display: table-cell;
            vertical-align: middle;
        }

        .header-left {
            width: 30%;
            font-weight: bold;
            font-size: 14px;
            color: #2563eb;
        }

        .header-center {
            width: 40%;
            text-align: center;
        }

        .header-center h1 {
            font-size: 18px;
            font-weight: bold;
            color: #2563eb;
        }

        .header-right {
            width: 30%;
            text-align: right;
            font-size: 9px;
        }

        /* Two Column Layout */
        .row {
            display: table;
            width: 100%;
            margin-bottom: 8px;
        }

        .col-50 {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 5px;
        }

        .col-50:last-child {
            padding-right: 0;
            padding-left: 5px;
        }

        /* Boxes */
        .box {
            border: 1px solid #ddd;
            padding: 6px;
            margin-bottom: 8px;
            background: #f9fafb;
        }

        .box-title {
            font-weight: bold;
            font-size: 11px;
            color: #2563eb;
            margin-bottom: 4px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 2px;
        }

        .field {
            margin-bottom: 3px;
        }

        .field-label {
            font-weight: bold;
            display: inline-block;
            width: 45%;
        }

        .field-value {
            display: inline-block;
            width: 54%;
        }

        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 9px;
        }

        table th {
            background: #2563eb;
            color: white;
            padding: 4px;
            text-align: left;
            font-weight: bold;
        }

        table td {
            border: 1px solid #ddd;
            padding: 3px 4px;
        }

        table tr:nth-child(even) {
            background: #f9fafb;
        }

        .text-right {
            text-align: right;
        }

        .total-row {
            font-weight: bold;
            background: #e5e7eb !important;
        }

        /* Remarks */
        .remarks {
            border: 1px solid #ddd;
            padding: 6px;
            min-height: 40px;
            margin-bottom: 8px;
            background: #fff;
        }

        .remarks-title {
            font-weight: bold;
            font-size: 10px;
            margin-bottom: 3px;
        }

        /* Signatures */
        .signature-box {
            border: 1px solid #ddd;
            padding: 6px;
            min-height: 50px;
            text-align: center;
        }

        .signature-line {
            border-top: 1px solid #000;
            margin-top: 30px;
            padding-top: 3px;
            font-size: 9px;
        }

        /* Footer */
        .footer {
            margin-top: 10px;
            padding-top: 6px;
            border-top: 1px solid #ddd;
            font-size: 8px;
            text-align: center;
            color: #666;
        }

        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
        }

        .badge-high {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-urgent {
            background: #fecaca;
            color: #7f1d1d;
        }

        .badge-medium {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-low {
            background: #d1fae5;
            color: #065f46;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                TASKER COMPANY
            </div>
            <div class="header-center">
                <h1>JOB SHEET</h1>
            </div>
            <div class="header-right">
                <strong>Job #:</strong> {{ $workOrder->work_order_number }}<br>
                <strong>Date:</strong> {{ $generatedDate }}
            </div>
        </div>

        <!-- Two Column Layout -->
        <div class="row">
            <!-- Left Column -->
            <div class="col-50">
                <!-- Customer Details -->
                <div class="box">
                    <div class="box-title">CUSTOMER DETAILS</div>
                    <div class="field">
                        <span class="field-label">Name:</span>
                        <span class="field-value">{{ $workOrder->customer->name ?? 'N/A' }}</span>
                    </div>
                    <div class="field">
                        <span class="field-label">Phone:</span>
                        <span class="field-value">{{ $workOrder->customer->phone ?? 'N/A' }}</span>
                    </div>
                    <div class="field">
                        <span class="field-label">Email:</span>
                        <span class="field-value">{{ $workOrder->customer->email ?? 'N/A' }}</span>
                    </div>
                    <div class="field">
                        <span class="field-label">WhatsApp:</span>
                        <span class="field-value">{{ $workOrder->customer->whatsapp ?? 'N/A' }}</span>
                    </div>
                    <div class="field">
                        <span class="field-label">Address:</span>
                        <span class="field-value">
                            @if ($workOrder->address)
                                {{ $workOrder->address->address_line_1 }}
                                @if ($workOrder->address->address_line_2)
                                    , {{ $workOrder->address->address_line_2 }}
                                @endif
                                <br>{{ $workOrder->address->city }}, {{ $workOrder->address->state }}
                                {{ $workOrder->address->zip_code }}
                            @else
                                N/A
                            @endif
                        </span>
                    </div>
                </div>

                <!-- Product Information -->
                <div class="box">
                    <div class="box-title">PRODUCT INFORMATION</div>
                    <div class="field">
                        <span class="field-label">Brand:</span>
                        <span class="field-value">{{ $workOrder->brand->name ?? 'N/A' }}</span>
                    </div>
                    <div class="field">
                        <span class="field-label">Category:</span>
                        <span class="field-value">{{ $workOrder->category->name ?? 'N/A' }}</span>
                    </div>
                    <div class="field">
                        <span class="field-label">Service:</span>
                        <span class="field-value">{{ $workOrder->service->name ?? 'N/A' }}</span>
                    </div>
                    <div class="field">
                        <span class="field-label">Parent Service:</span>
                        <span class="field-value">{{ $workOrder->parentService->name ?? 'N/A' }}</span>
                    </div>
                    <div class="field">
                        <span class="field-label">Product:</span>
                        <span class="field-value">{{ $workOrder->product->name ?? 'N/A' }}</span>
                    </div>
                    <div class="field">
                        <span class="field-label">Indoor Model:</span>
                        <span class="field-value">{{ $workOrder->product_indoor_model ?? 'N/A' }}</span>
                    </div>
                    <div class="field">
                        <span class="field-label">Indoor Serial:</span>
                        <span class="field-value">{{ $workOrder->indoor_serial_number ?? 'N/A' }}</span>
                    </div>
                    <div class="field">
                        <span class="field-label">Outdoor Model:</span>
                        <span class="field-value">{{ $workOrder->product_outdoor_model ?? 'N/A' }}</span>
                    </div>
                    <div class="field">
                        <span class="field-label">Outdoor Serial:</span>
                        <span class="field-value">{{ $workOrder->outdoor_serial_number ?? 'N/A' }}</span>
                    </div>
                    <div class="field">
                        <span class="field-label">Purchase Date:</span>
                        <span
                            class="field-value">{{ $workOrder->purchase_date ? $workOrder->purchase_date->format('d/m/Y') : 'N/A' }}</span>
                    </div>
                    <div class="field">
                        <span class="field-label">Warranty:</span>
                        <span class="field-value">{{ $workOrder->is_warranty_case ? 'Yes' : 'No' }}</span>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-50">
                <!-- Job Details -->
                <div class="box">
                    <div class="box-title">JOB DETAILS</div>
                    <div class="field">
                        <span class="field-label">Work Order #:</span>
                        <span class="field-value">{{ $workOrder->work_order_number }}</span>
                    </div>
                    <div class="field">
                        <span class="field-label">Priority:</span>
                        <span class="field-value">
                            <span class="badge badge-{{ $workOrder->priority }}">
                                {{ strtoupper($workOrder->priority ?? 'N/A') }}
                            </span>
                        </span>
                    </div>
                    <div class="field">
                        <span class="field-label">Status:</span>
                        <span class="field-value">{{ $workOrder->status->name ?? 'N/A' }}</span>
                    </div>
                    <div class="field">
                        <span class="field-label">Sub-Status:</span>
                        <span class="field-value">{{ $workOrder->subStatus->name ?? 'N/A' }}</span>
                    </div>
                    <div class="field">
                        <span class="field-label">Branch:</span>
                        <span class="field-value">{{ $workOrder->branch->name ?? 'N/A' }}</span>
                    </div>
                    <div class="field">
                        <span class="field-label">Assigned To:</span>
                        <span class="field-value">
                            @if ($workOrder->assignedTo)
                                {{ $workOrder->assignedTo->first_name }} {{ $workOrder->assignedTo->last_name }}
                            @else
                                Not Assigned
                            @endif
                        </span>
                    </div>
                    <div class="field">
                        <span class="field-label">Appointment Date:</span>
                        <span
                            class="field-value">{{ $workOrder->appointment_date ? $workOrder->appointment_date->format('d/m/Y') : 'N/A' }}</span>
                    </div>
                    <div class="field">
                        <span class="field-label">Appointment Time:</span>
                        <span class="field-value">{{ $workOrder->appointment_time ?? 'N/A' }}</span>
                    </div>
                    <div class="field">
                        <span class="field-label">Service Start:</span>
                        <span class="field-value">
                            {{ $workOrder->service_start_date ? $workOrder->service_start_date->format('d/m/Y') : 'N/A' }}
                            {{ $workOrder->service_start_time ?? '' }}
                        </span>
                    </div>
                    <div class="field">
                        <span class="field-label">Service End:</span>
                        <span class="field-value">
                            {{ $workOrder->service_end_date ? $workOrder->service_end_date->format('d/m/Y') : 'N/A' }}
                            {{ $workOrder->service_end_time ?? '' }}
                        </span>
                    </div>
                    <div class="field">
                        <span class="field-label">Brand Complaint #:</span>
                        <span class="field-value">{{ $workOrder->brand_complaint_no ?? 'N/A' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Services Table -->
        @if ($workOrder->services && $workOrder->services->count() > 0)
            <div class="box-title" style="margin-bottom: 4px;">SERVICES PROVIDED</div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 50%;">Service Name</th>
                        <th style="width: 30%;">Description</th>
                        <th style="width: 20%;" class="text-right">Price</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($workOrder->services as $service)
                        <tr>
                            <td>{{ $service->service_name ?? 'N/A' }}</td>
                            <td>{{ $service->description ?? '-' }}</td>
                            <td class="text-right">₹{{ number_format($service->final_price ?? 0, 2) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total-row">
                        <td colspan="2" class="text-right">Subtotal:</td>
                        <td class="text-right">₹{{ number_format($workOrder->total_amount ?? 0, 2) }}</td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="2" class="text-right">Discount:</td>
                        <td class="text-right">₹{{ number_format($workOrder->discount ?? 0, 2) }}</td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="2" class="text-right"><strong>Total:</strong></td>
                        <td class="text-right"><strong>₹{{ number_format($workOrder->final_amount ?? 0, 2) }}</strong>
                        </td>
                    </tr>
                </tbody>
            </table>
        @endif

        <!-- Remarks Section -->
        <div class="row">
            <div class="col-50">
                <div class="remarks">
                    <div class="remarks-title">CUSTOMER COMPLAINT:</div>
                    {{ $workOrder->customer_description ?? 'No description provided' }}
                </div>
                <div class="remarks">
                    <div class="remarks-title">DEFECT DESCRIPTION:</div>
                    {{ $workOrder->defect_description ?? 'No defect description' }}
                </div>
            </div>
            <div class="col-50">
                <div class="remarks">
                    <div class="remarks-title">TECHNICIAN REMARKS:</div>
                    {{ $workOrder->technician_remarks ?? 'No remarks' }}
                </div>
                <div class="remarks">
                    <div class="remarks-title">SERVICE DESCRIPTION:</div>
                    {{ $workOrder->service_description ?? 'No service description' }}
                </div>
            </div>
        </div>

        <!-- Signatures -->
        <div class="row">
            <div class="col-50">
                <div class="signature-box">
                    <strong>TECHNICIAN SIGNATURE</strong>
                    <div class="signature-line">
                        Name:
                        {{ $workOrder->assignedTo ? $workOrder->assignedTo->first_name . ' ' . $workOrder->assignedTo->last_name : '___________________' }}
                        <br>Date: _______________
                    </div>
                </div>
            </div>
            <div class="col-50">
                <div class="signature-box">
                    <strong>CUSTOMER SIGNATURE</strong>
                    <div class="signature-line">
                        Name: {{ $workOrder->customer->name ?? '___________________' }}
                        <br>Date: _______________
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <strong>Tasker Company</strong> | Phone: +92 XXX XXXXXXX | Email: info@taskercompany.com |
            www.taskercompany.com
            <br>This is a computer-generated document. Terms and conditions apply.
        </div>
    </div>
</body>

</html>
