@extends('errors.minimal')

@section('title', __('Server Error'))
@section('code', '500')
@section('message', __('System Failure'))
@section('description', __('Our core engines encountered an unexpected anomaly. We\'ve logged the incident and our technicians are already investigating the breach.'))

@section('actions')
    <a href="javascript:location.reload()" class="group relative px-8 py-4 bg-brand-primary text-white font-black uppercase tracking-widest text-[11px] rounded-xl transition-all hover:scale-105 active:scale-95 glow-on-hover">
        <span class="relative z-10 flex items-center gap-2">
            Try again
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/></svg>
        </span>
    </a>
    
    <a href="/licenses" class="px-8 py-4 bg-white/5 border border-white/5 text-zinc-400 font-black uppercase tracking-widest text-[11px] rounded-xl transition-all hover:bg-white/10 hover:text-white">
        Back to Dashboard
    </a>
@endsection
