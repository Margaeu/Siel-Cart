<x-layouts.front-end-layout title="Privacy Policy">

    <div class="bg-white text-slate-800">
        <section class="px-4 py-16 sm:px-6 sm:py-20 lg:px-8 lg:py-24">
            <div class="mx-auto max-w-4xl">
                <h1 class="text-3xl font-bold leading-tight tracking-[-0.03em] text-[var(--color-primary)] sm:text-4xl">
                    Privacy Policy
                </h1>
                <p class="mt-3 text-sm text-slate-500">Last updated: {{ now()->format('F d, Y') }}</p>

                <div class="mt-10 space-y-10">
                    <section>
                        <h2 class="text-xl font-bold text-slate-900">1. Introduction</h2>
                        <p class="mt-3 text-base leading-8 text-slate-600">
                            {{ config('app.name') }} respects your privacy and is committed to protecting the personal
                            information you share with us. This Privacy Policy explains what information we collect,
                            how we use it, and the choices you have.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-xl font-bold text-slate-900">2. Information We Collect</h2>
                        <ul class="mt-3 list-disc space-y-2 pl-5 text-base leading-8 text-slate-600">
                            <li><strong class="text-slate-800">Account information</strong> — your name, email address, and phone number when you register.</li>
                            <li><strong class="text-slate-800">Order information</strong> — items purchased, shipping address, and order history.</li>
                            <li><strong class="text-slate-800">Payment information</strong> — processed securely by our payment provider; we do not store full payment card details on our servers.</li>
                            <li><strong class="text-slate-800">Reviews and content</strong> — any reviews, ratings, photos, or videos you choose to submit.</li>
                            <li><strong class="text-slate-800">Usage data</strong> — pages visited and general browsing activity on our site, collected to improve the shopping experience.</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-xl font-bold text-slate-900">3. How We Use Your Information</h2>
                        <ul class="mt-3 list-disc space-y-2 pl-5 text-base leading-8 text-slate-600">
                            <li>To process and fulfill your orders, including payment and delivery.</li>
                            <li>To communicate with you about your orders, account, or customer support requests.</li>
                            <li>To display your reviews and ratings on relevant product pages.</li>
                            <li>To improve our products, website, and overall customer experience.</li>
                            <li>To detect and prevent fraud, abuse, or violations of our Terms and Conditions.</li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-xl font-bold text-slate-900">4. How We Share Your Information</h2>
                        <p class="mt-3 text-base leading-8 text-slate-600">
                            We do not sell your personal information. We may share information with trusted third
                            parties only as needed to operate our store — such as payment processors to complete
                            transactions, courier or delivery partners to fulfill orders, and cloud storage providers to
                            host uploaded content like review photos and videos.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-xl font-bold text-slate-900">5. Cookies</h2>
                        <p class="mt-3 text-base leading-8 text-slate-600">
                            We use cookies and similar technologies to keep you signed in, remember items in your cart,
                            and understand how our site is used. You can control cookies through your browser settings,
                            though disabling them may affect certain features of the site, such as staying logged in.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-xl font-bold text-slate-900">6. Data Retention</h2>
                        <p class="mt-3 text-base leading-8 text-slate-600">
                            We retain your personal information for as long as your account is active or as needed to
                            fulfill orders, comply with legal obligations, resolve disputes, and enforce our agreements.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-xl font-bold text-slate-900">7. Data Security</h2>
                        <p class="mt-3 text-base leading-8 text-slate-600">
                            We take reasonable technical and organizational measures to protect your personal
                            information from unauthorized access, alteration, disclosure, or destruction. However, no
                            method of transmission or storage over the internet is completely secure.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-xl font-bold text-slate-900">8. Your Rights</h2>
                        <p class="mt-3 text-base leading-8 text-slate-600">
                            You may access, update, or request deletion of your personal information at any time by
                            managing your account settings or contacting us directly.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-xl font-bold text-slate-900">9. Children's Privacy</h2>
                        <p class="mt-3 text-base leading-8 text-slate-600">
                            Our site is not intended for children, and we do not knowingly collect personal information
                            from anyone under the applicable age of consent without parental permission.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-xl font-bold text-slate-900">10. Changes to This Policy</h2>
                        <p class="mt-3 text-base leading-8 text-slate-600">
                            We may update this Privacy Policy from time to time. Any changes will be posted on this page
                            with a revised "Last updated" date.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-xl font-bold text-slate-900">11. Contact Us</h2>
                        <p class="mt-3 text-base leading-8 text-slate-600">
                            If you have any questions about this Privacy Policy or how we handle your data, please
                            contact us through the contact details provided on this site.
                        </p>
                    </section>
                </div>
            </div>
        </section>
    </div>

</x-layouts.front-end-layout>