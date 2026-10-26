@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
{{-- PNG porque o Gmail não mostra SVG; gerado por docs/brand/gerar-assets.mjs. --}}
<img src="{{ asset('brand/email-simbolo.png') }}" class="logo" width="44" height="35" alt="">
<span class="wordmark">{!! $slot !!}</span>
</a>
</td>
</tr>
