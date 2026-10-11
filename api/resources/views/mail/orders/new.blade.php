@component('mail::message')
# New order {{ $order->reference }}

**{{ $order->customer_name }}** · {{ $order->customer_phone }}{{ $order->customer_email ? ' · '.$order->customer_email : '' }}


Pickup: **{{ $pickup }}** · Pay at pickup

@component('mail::table')
| Item | Qty | Price |
| :--- | :-: | ----: |
@foreach($order->items as $item)
@php
    $label = $item->name;
    if ($item->options) {
        $label .= ' ('.collect($item->options)->pluck('name')->join(', ').')';
    }
    if ($item->notes) {
        $label .= ': '.$item->notes;
    }
@endphp
| {{ $label }} | {{ $item->quantity }} | ${{ number_format($item->line_total_cents / 100, 2) }} |
@endforeach
| **Total** | | **${{ number_format($order->total_cents / 100, 2) }}** |
@endcomponent

@if($order->notes)
Note from the customer: {{ $order->notes }}
@endif

@component('mail::button', ['url' => $url])
Open the orders board
@endcomponent
@endcomponent
