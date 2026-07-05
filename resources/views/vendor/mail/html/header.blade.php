@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none; text-align: center;">
<img src="{{ rtrim(config('app.frontend_url'), '/') }}/logo.png" style="height: 52px; width: 52px; border-radius: 8px; vertical-align: middle; margin-bottom: 8px;" alt="Logo"><br>
<span style="font-size: 20px; font-weight: 800; color: #1e1b4b; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">{{ config('app.name', 'Stitch & Style') }}</span>
</a>
</td>
</tr>
