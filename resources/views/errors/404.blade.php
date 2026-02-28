@extends('errors.minimal')

@section('title', __('Page Not Found'))
@section('code', '404')
@section('message', __('Lost in the void?'))
@section('description', __('The path you followed seems to have vanished. Our high-performance systems couldn\'t locate the page you were looking for.'))

@section('actions')
    <a href="/" class="group relative px-8 py-4 bg-brand-primary text-white font-black uppercase tracking-widest text-[11px] rounded-xl transition-all hover:scale-105 active:scale-95 glow-on-hover">
        <span class="relative z-10 flex items-center gap-2">
            Return home
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        </span>
    </a>
    
    <a href="/licenses" class="px-8 py-4 bg-white/5 border border-white/5 text-zinc-400 font-black uppercase tracking-widest text-[11px] rounded-xl transition-all hover:bg-white/10 hover:text-white">
        Back to Dashboard
    </a>
@endsection
