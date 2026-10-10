@php
    $authenticated = auth()->check();
    $paymentReady = filled(config('services.midtrans.server_key')) && filled(config('services.midtrans.client_key'));
@endphp
<div class="mtn-subscription-host">
    <x-modal name="subscription" :show="request('upgrade') === '1'" labelledby="subscription-dialog-title" focusable>
        <div id="subscription-content" data-authenticated="{{ $authenticated ? '1' : '0' }}" data-configured="{{ $paymentReady ? '1' : '0' }}" data-summary="{{ route('billing.summary') }}" data-pay="{{ route('billing.pay') }}" data-initial-plan="{{ request('plan') === 'annual' ? 'annual' : 'monthly' }}" data-initial-tab="{{ request('billing') === '1' ? 'billing' : 'plans' }}" data-monthly="{{ config('subscription.monthly_price') }}" data-annual="{{ config('subscription.annual_price') }}" data-monthly-login="{{ route('billing.checkout', ['plan' => 'monthly']) }}" data-annual-login="{{ route('billing.checkout', ['plan' => 'annual']) }}">
            <header class="sub-header">
                <h2 id="subscription-dialog-title">Paket Muterin</h2>
                <button type="button" class="sub-close" aria-label="Tutup pilihan paket" @click="$dispatch('close-modal', 'subscription')"><x-icon.x aria-hidden="true"/></button>
            </header>
            <div class="sub-scroll">
                @auth
                    <div class="sub-tabs" role="group" aria-label="Bagian langganan">
                        <button type="button" data-sub-tab="plans" aria-pressed="true">Pilihan paket</button>
                        <button type="button" data-sub-tab="billing" aria-pressed="false">Pembayaran</button>
                    </div>
                @endauth
                <p class="sub-feedback" role="status" aria-live="polite" data-sub-feedback hidden></p>
                <div data-sub-panel="plans">
                    <div class="sub-intro"><h3>Rawat motor. Jaga setiap perjalanan.</h3><p>Pilih catatan dasar atau paket Pro untuk motormu.</p></div>
                    <div class="sub-plan-grid">
                        <article class="sub-plan">
                            <div class="sub-plan-top"><x-icon.motorcycle class="sub-plan-icon" aria-hidden="true"/></div>
                            <h4>Gratis</h4><p class="sub-description">Mulai mencatat perawatan motor.</p>
                            <p class="sub-price">Rp0 <span>/ bulan</span></p>
                            <p class="sub-price-detail">Tanpa biaya.</p>
                            <div class="sub-feature-group"><p>Rancangan fitur Gratis</p><ul>
                                <li><x-icon.check aria-hidden="true"/>Catatan untuk satu motor</li>
                                <li><x-icon.check aria-hidden="true"/>Update odometer manual</li>
                                <li><x-icon.check aria-hidden="true"/>Riwayat servis dasar</li>
                            </ul></div>
                            <div class="sub-plan-bottom">
                                @auth
                                    <button type="button" class="sub-cta sub-cta-secondary" @click="$dispatch('close-modal', 'subscription')">Lanjutkan di aplikasi</button>
                                @else
                                    <a class="sub-cta sub-cta-secondary" href="{{ route('register', ['plan' => 'free']) }}">Buat akun gratis</a>
                                @endauth
                                <p class="sub-billing-note">Tidak perlu pembayaran.</p>
                            </div>
                        </article>
                        <article class="sub-plan sub-plan-pro">
                            <div class="sub-plan-top"><x-icon.route class="sub-plan-icon" aria-hidden="true"/><div class="sub-duration" role="group" aria-label="Durasi paket Pro">
                                <button type="button" data-sub-duration="monthly" aria-pressed="true">Bulanan</button>
                                <button type="button" data-sub-duration="annual" aria-pressed="false">Tahunan <small>Hemat 2 bulan</small></button>
                            </div></div>
                            <h4>Pro</h4><p class="sub-description">Perawatan, biaya, dan rute dalam satu tempat.</p>
                            <p class="sub-price"><strong data-sub-price>Rp{{ number_format(config('subscription.monthly_price'), 0, ',', '.') }}</strong><span data-sub-unit>/ bulan</span></p>
                            <p class="sub-price-detail" data-sub-price-detail>Dibayar untuk 1 bulan.</p>
                            <div class="sub-feature-group"><p>Rancangan fitur Pro</p><ul>
                                <li><x-icon.check aria-hidden="true"/>Kelola beberapa motor</li>
                                <li><x-icon.check aria-hidden="true"/>Pengingat perawatan berkala</li>
                                <li><x-icon.check aria-hidden="true"/>BBM dan tren efisiensi</li>
                                <li><x-icon.check aria-hidden="true"/>Laporan biaya dan ekspor PDF</li>
                                <li><x-icon.check aria-hidden="true"/>Rekam riding dan rencana rute</li>
                                <li><x-icon.check aria-hidden="true"/>Titik pribadi dan peta komunitas</li>
                            </ul></div>
                            <div class="sub-plan-bottom">
                                @auth
                                    <button type="button" class="sub-cta" data-sub-checkout @disabled(! $paymentReady)><span data-sub-pay-label>Pilih Pro Bulanan</span><span class="mtn-loader hidden" data-sub-pay-loader aria-hidden="true"></span></button>
                                @else
                                    <a class="sub-cta" data-sub-guest-checkout href="{{ route('billing.checkout', ['plan' => 'monthly']) }}">Masuk untuk pilih Pro</a>
                                @endauth
                                <p class="sub-billing-note">Bayar sekali per periode. Tidak diperpanjang otomatis.</p>
                            </div>
                        </article>
                    </div>
                    <p class="sub-terms">Trial 7 hari dan pembatasan fitur belum aktif. {{ config('services.midtrans.production') ? 'Checkout membeli paket langsung.' : 'Mode Sandbox: pembayaran hanya untuk pengujian.' }}</p>
                    @if ($authenticated && ! $paymentReady)<p class="sub-terms">Pembayaran belum tersedia saat ini.</p>@endif
                </div>
                @auth
                    <section data-sub-panel="billing" hidden aria-labelledby="sub-billing-title">
                        <h3 id="sub-billing-title" class="sub-billing-title">Paket &amp; pembayaran saya</h3>
                        <p class="sub-active" data-sub-active>Memeriksa paket…</p>
                        <div class="sub-skeleton" data-sub-skeleton hidden aria-hidden="true"><div></div><div></div><div></div></div>
                        <p class="sub-empty" data-sub-empty hidden>Belum ada transaksi. Pilih paket Pro untuk memulai pembayaran.</p>
                        <div data-sub-orders></div>
                        <div class="sub-pagination" data-sub-pagination hidden><button type="button" data-sub-prev>Sebelumnya</button><span data-sub-page></span><button type="button" data-sub-next>Berikutnya</button></div>
                        <button type="button" class="sub-retry" data-sub-retry hidden>Muat ulang pembayaran</button>
                    </section>
                @endauth
            </div>
        </div>
    </x-modal>
</div>
@if ($authenticated && $paymentReady)
    <script src="https://{{ config('services.midtrans.production') ? 'app.midtrans.com' : 'app.sandbox.midtrans.com' }}/snap/snap.js" data-client-key="{{ config('services.midtrans.client_key') }}" defer></script>
@endif
<script src="{{ asset('js/subscription.js') }}?v={{ filemtime(public_path('js/subscription.js')) }}" defer></script>
