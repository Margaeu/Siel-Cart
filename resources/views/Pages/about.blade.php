<x-layouts.front-end-layout title="About">

    <div class="bg-white text-slate-800">
        <!--
        <section
            class="relative isolate overflow-hidden bg-[var(--color-primary)] px-4 py-20 text-white sm:px-6 sm:py-24 lg:px-8 lg:py-28"
            aria-labelledby="about-page-title"
        >
            <div
                class="absolute inset-0 -z-10 opacity-[0.08]"
                aria-hidden="true"
                style="background-image: radial-gradient(circle at center, white 1px, transparent 1px); background-size: 28px 28px;"
            ></div>
            <div
                class="absolute left-1/2 top-1/2 -z-10 size-[30rem] -translate-x-1/2 -translate-y-1/2 rounded-full border border-white/15 sm:size-[38rem]"
                aria-hidden="true"
            ></div>

            <div class="mx-auto flex max-w-5xl flex-col items-center text-center">
                <img
                    src="{{ asset('images/clsu_logo_white.png') }}"
                    alt="Central Luzon State University seal"
                    class="mb-7 size-20 object-contain drop-shadow-sm sm:size-24"
                >
                <h1 id="about-page-title" class="text-4xl font-bold tracking-[-0.04em] sm:text-5xl lg:text-6xl">
                    About Us
                </h1>
                <span class="mt-7 h-1 w-14 rounded-full bg-[var(--color-secondary)]" aria-hidden="true"></span>
            </div>
        </section>
        -->

        <section class="px-4 py-16 sm:px-6 sm:py-20 lg:px-8 lg:py-24" aria-labelledby="about-story-title">
            <div class="mx-auto max-w-4xl">
                <h2 id="about-story-title" class="text-3xl font-bold leading-tight tracking-[-0.03em] text-[var(--color-primary)] sm:text-4xl">
                    Discover the University Pasalubong Center
                </h2>

                <div class="mt-8 space-y-6 text-base leading-8 text-slate-600 sm:text-lg sm:leading-9">
                    <p>
                        The <strong class="font-semibold text-slate-900">University Pasalubong Center</strong> is a place where the university’s products, identity, and memories come together. From university merchandise to other items worth bringing home, we aim to make it easier for students, alumni, faculty, staff, visitors, and friends of the university to find something that represents the university and the community behind it.
                    </p>
                    <p>
                        More than just a store, the University Pasalubong Center celebrates the creativity, craftsmanship, and products that make our university community special. Every purchase is an opportunity to bring a piece of the university with you—whether as a personal souvenir, a gift for someone special, or a simple reminder of your time at the university.
                    </p>
                </div>

                <blockquote class="mt-10 border-l-4 border-[var(--color-secondary)] bg-[color-mix(in_srgb,var(--color-primary)_6%,white)] px-6 py-7 sm:px-8">
                    <p class="text-2xl font-bold leading-snug tracking-[-0.025em] text-[var(--color-primary)] sm:text-3xl">
                        Take a piece of the university with you. Shop, share, and bring home something worth remembering.
                    </p>
                </blockquote>
            </div>
        </section>
        <!--
        <section class="bg-[color-mix(in_srgb,var(--color-primary)_5%,white)] px-4 py-16 sm:px-6 sm:py-20 lg:px-8" aria-label="What we celebrate">
            <div class="mx-auto grid max-w-[90rem] gap-6 md:grid-cols-3">
                <article class="rounded-2xl border border-black/5 bg-white p-7 shadow-[0_8px_28px_rgba(15,23,42,0.06)] sm:p-8">
                    <div class="flex size-14 items-center justify-center rounded-full bg-[color-mix(in_srgb,var(--color-primary)_9%,white)] text-[var(--color-primary)]">
                        <svg class="size-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M9 18h6m-5 3h4m-6.1-6.8a7 7 0 1 1 8.2 0c-.7.5-1.1 1.3-1.1 2.1H9c0-.8-.4-1.6-1.1-2.1Z" />
                        </svg>
                    </div>
                    <h3 class="mt-6 text-xl font-bold tracking-[-0.02em] text-slate-950 sm:text-2xl">Creativity</h3>
                    <p class="mt-3 text-base leading-7 text-slate-600 sm:text-lg">
                        Original CLSU designs that carry the pride and personality of the community.
                    </p>
                </article>

                <article class="rounded-2xl border border-black/5 bg-white p-7 shadow-[0_8px_28px_rgba(15,23,42,0.06)] sm:p-8">
                    <div class="flex size-14 items-center justify-center rounded-full bg-[color-mix(in_srgb,var(--color-primary)_9%,white)] text-[var(--color-primary)]">
                        <svg class="size-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="m14.7 5.3 4 4M5 19l2.2-5.2L15.5 5.5a2.1 2.1 0 0 1 3 3l-8.3 8.3L5 19Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="m7.2 13.8 3 3" />
                        </svg>
                    </div>
                    <h3 class="mt-6 text-xl font-bold tracking-[-0.02em] text-slate-950 sm:text-2xl">Craftsmanship</h3>
                    <p class="mt-3 text-base leading-7 text-slate-600 sm:text-lg">
                        Locally made merchandise, produced with care and quality.
                    </p>
                </article>

                <article class="rounded-2xl border border-black/5 bg-white p-7 shadow-[0_8px_28px_rgba(15,23,42,0.06)] sm:p-8">
                    <div class="flex size-14 items-center justify-center rounded-full bg-[color-mix(in_srgb,var(--color-primary)_9%,white)] text-[var(--color-primary)]">
                        <svg class="size-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M16 20v-1.5A3.5 3.5 0 0 0 12.5 15h-5A3.5 3.5 0 0 0 4 18.5V20m15.5 0v-1.5a3.5 3.5 0 0 0-2.6-3.4M14.7 4.1a3.5 3.5 0 0 1 0 6.8M9.9 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" />
                        </svg>
                    </div>
                    <h3 class="mt-6 text-xl font-bold tracking-[-0.02em] text-slate-950 sm:text-2xl">Community</h3>
                    <p class="mt-3 text-base leading-7 text-slate-600 sm:text-lg">
                        For students, alumni, faculty, staff, visitors, and friends of the university.
                    </p>
                </article>
            </div>
        </section>
        -->
    </div>
</x-layouts.front-end-layout>
