@props(['title' => null, 'subtitle' => null, 'actions' => null])

@include('layouts.app', [
    'title' => $title,
    'subtitle' => $subtitle,
    'actions' => $actions,
    'slot' => $slot,
])

