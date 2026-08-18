@foreach (($theme_custom_stylesheets ?? []) as $stylesheet)
<link rel="stylesheet" href="{{$stylesheet}}">
@endforeach
