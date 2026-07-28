{{-- Nom de fichier suggéré à l'enregistrement PDF (lu via document.title par le navigateur). --}}
@php($printTitle = $printTitle ?? 'Document')
<title>{{ $printTitle }}</title>
