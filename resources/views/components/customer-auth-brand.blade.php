<a
    href="{{ route('home') }}"
    class="group inline-flex items-center justify-center gap-3 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-[#E0A70D] focus-visible:ring-offset-4 focus-visible:ring-offset-[#f4f7ef]"
    aria-label="{{ config('app.name') }} home"
>
    <img
        src="{{ asset('images/clsu_logo_green.png') }}"
        alt="Central Luzon State University seal"
        class="size-12 shrink-0 object-contain drop-shadow-sm transition-transform duration-200 group-hover:scale-[1.03] sm:size-14"
        width="56"
        height="56"
    >

    <span class="text-2xl font-bold tracking-[-0.035em] text-[#557F13] transition-colors group-hover:text-[#3E5D0E] sm:text-3xl">
        {{ config('app.name') }}
    </span>
</a>
