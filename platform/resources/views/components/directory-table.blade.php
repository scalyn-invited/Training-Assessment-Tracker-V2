@props(['label', 'columns'])
<div class="directory-table-wrap"><table class="directory-table"><caption class="sr-only">{{ $label }}</caption><thead><tr>@foreach($columns as $column)<th scope="col">{{ $column }}</th>@endforeach</tr></thead><tbody>{{ $slot }}</tbody></table></div>
