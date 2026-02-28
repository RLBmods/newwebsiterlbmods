@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (trim($slot) === 'Laravel')
<span style="color: #FFFFFF; font-size: 24px; font-weight: 900; letter-spacing: -0.02em;">RLBmods</span>
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
