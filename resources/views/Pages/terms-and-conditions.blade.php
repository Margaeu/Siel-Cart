<x-layouts.front-end-layout title="Terms and Conditions">

    <div class="bg-white text-slate-800">
        <section class="px-4 py-16 sm:px-6 sm:py-20 lg:px-8 lg:py-24">
            <div class="mx-auto max-w-4xl">
                <h1 class="text-3xl font-bold leading-tight tracking-[-0.03em] text-[var(--color-primary)] sm:text-4xl">
                    Terms and Conditions
                </h1>
                <p class="mt-3 text-sm text-slate-500">Last updated: {{ now()->format('F d, Y') }}</p>

                <div class="mt-10 space-y-10">
                    <section>
                        <h2 class="text-xl font-bold text-slate-900">1. Agreement to Terms</h2>
                        <p class="mt-3 text-base leading-8 text-slate-600">
                            By accessing or placing an order through {{ config('app.name') }}, you agree to be bound by these
                            Terms and Conditions. If you do not agree with any part of these terms, please do not use this
                            website or make a purchase.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-xl font-bold text-slate-900">2. Account Registration</h2>
                        <p class="mt-3 text-base leading-8 text-slate-600">
                            To place an order, you may be required to create an account. You agree to provide accurate,
                            current, and complete information, and to keep this information up to date. You are
                            responsible for maintaining the confidentiality of your account credentials and for all
                            activity that occurs under your account.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-xl font-bold text-slate-900">3. Products and Pricing</h2>
                        <p class="mt-3 text-base leading-8 text-slate-600">
                            We make every effort to display product details, images, and pricing accurately. However, we
                            do not warrant that product descriptions or other content on this site are error-free.
                            Prices and availability are subject to change without prior notice, and we reserve the right
                            to limit quantities or refuse any order at our discretion.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-xl font-bold text-slate-900">4. Orders and Payment</h2>
                        <p class="mt-3 text-base leading-8 text-slate-600">
                            By placing an order, you confirm that the information provided is accurate and that you are
                            authorized to use the payment method selected. Orders are subject to acceptance and
                            availability; we reserve the right to cancel or refuse any order, including in cases of
                            suspected fraud, pricing errors, or stock unavailability.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-xl font-bold text-slate-900">5. Shipping and Delivery</h2>
                        <p class="mt-3 text-base leading-8 text-slate-600">
                            Delivery timeframes provided at checkout are estimates and not guaranteed. We are not
                            responsible for delays caused by circumstances beyond our reasonable control, including
                            courier delays, incorrect shipping details provided by the customer, or events of force
                            majeure.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-xl font-bold text-slate-900">6. Returns and Refunds</h2>
                        <p class="mt-3 text-base leading-8 text-slate-600">
                            Requests for returns, exchanges, or refunds are handled in accordance with our
                            <a href="{{ route('return-refund-policy') }}" class="font-semibold text-[var(--color-primary)] hover:underline">Return and Refund Policy</a>,
                            which forms part of these Terms and Conditions.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-xl font-bold text-slate-900">7. User Conduct</h2>
                        <p class="mt-3 text-base leading-8 text-slate-600">
                            When using this site, including when submitting product reviews, you agree not to post
                            content that is false, defamatory, abusive, or that infringes on the rights of others. We
                            reserve the right to remove any content and to suspend or terminate accounts that violate
                            these terms.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-xl font-bold text-slate-900">8. Intellectual Property</h2>
                        <p class="mt-3 text-base leading-8 text-slate-600">
                            All content on this site, including logos, product designs, images, and text, is the
                            property of {{ config('app.name') }} or its licensors and is protected by applicable
                            intellectual property laws. You may not reproduce, distribute, or use this content without
                            prior written permission.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-xl font-bold text-slate-900">9. Limitation of Liability</h2>
                        <p class="mt-3 text-base leading-8 text-slate-600">
                            To the fullest extent permitted by law, {{ config('app.name') }} shall not be liable for any
                            indirect, incidental, or consequential damages arising from your use of this site or the
                            products purchased through it.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-xl font-bold text-slate-900">10. Changes to These Terms</h2>
                        <p class="mt-3 text-base leading-8 text-slate-600">
                            We may update these Terms and Conditions from time to time. Continued use of the site after
                            changes are posted constitutes your acceptance of the revised terms.
                        </p>
                    </section>

                    <section>
                        <h2 class="text-xl font-bold text-slate-900">11. Contact Us</h2>
                        <p class="mt-3 text-base leading-8 text-slate-600">
                            If you have any questions about these Terms and Conditions, please reach out to us through
                            the contact details provided on this site.
                        </p>
                    </section>
                </div>
            </div>
        </section>
    </div>

</x-layouts.front-end-layout>