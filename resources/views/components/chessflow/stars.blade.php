@props(['n' => 0])
@foreach ([0, 1, 2] as $i)<i class="star-ico{{ $i < $n ? '' : ' off' }}"></i>@endforeach
