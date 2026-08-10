<div class="bw-login-page">
    <header class="bw-login-header">
        <div class="bw-login-brand" aria-label="Los Boomwalos">
            <img src="{{ asset('images/favicon.png') }}" alt="" class="bw-login-logo">
            <span class="bw-login-brand-name">LOS BOOMWALOS</span>
        </div>
    </header>

    <main class="bw-login-main">
        @if ($mode === 'cashier')
            <section class="bw-login-intro" aria-labelledby="cashier-login-title">
                <p class="bw-login-kicker">Acceso operativo</p>
                <h1 id="cashier-login-title">Inicia sesión</h1>
                <p>Ingresa tu código y PIN para comenzar a trabajar.</p>
            </section>

            <section
                class="bw-login-grid"
                aria-label="Acceso de cajero"
                x-data="{
                    step: 'codigo',
                    codigo: '',
                    pin: '',
                    localError: '',
                    loading: false,
                    append(digit) {
                        if (this.loading || !/^[0-9]$/.test(digit)) return;

                        const field = this.step === 'codigo' ? 'codigo' : 'pin';
                        const maxLength = field === 'codigo' ? 6 : 4;

                        if (this[field].length >= maxLength) return;

                        this[field] += digit;
                        this.localError = '';
                    },
                    backspace() {
                        if (this.loading) return;

                        const field = this.step === 'codigo' ? 'codigo' : 'pin';
                        this[field] = this[field].slice(0, -1);
                        this.localError = '';
                    },
                    clearCurrent() {
                        if (this.loading) return;

                        const field = this.step === 'codigo' ? 'codigo' : 'pin';
                        this[field] = '';
                        this.localError = '';
                    },
                    focusCode() {
                        if (this.loading) return;

                        this.step = 'codigo';
                        this.localError = '';
                    },
                    focusPin() {
                        if (this.loading) return;

                        this.step = 'pin';
                        this.localError = '';
                    },
                    continueStep() {
                        if (this.loading) return;

                        if (this.step === 'codigo') {
                            if (!/^[0-9]{2,6}$/.test(this.codigo)) {
                                this.localError = 'El código debe tener entre 2 y 6 dígitos.';
                                return;
                            }

                            this.step = 'pin';
                            this.localError = '';
                            return;
                        }

                        if (!/^[0-9]{4}$/.test(this.pin)) {
                            this.localError = 'El PIN debe tener exactamente 4 dígitos.';
                            return;
                        }

                        this.loading = true;
                        this.localError = '';

                        const request = this.$wire.authenticateCashier(this.codigo, this.pin);

                        if (request && typeof request.finally === 'function') {
                            request.finally(() => { this.loading = false; });
                        }
                    }
                }"
            >
                <div class="bw-login-card bw-login-identity-card">
                    <div>
                        <span class="bw-login-label">CÓDIGO DE CAJERO · PASO 1</span>
                        <label class="sr-only" for="cashier-code">Código de cajero</label>
                        <input
                            id="cashier-code"
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            maxlength="6"
                            readonly
                            x-model="codigo"
                            @click="focusCode"
                            :class="{ 'is-active': step === 'codigo' }"
                            class="bw-login-code-input"
                            placeholder="Ej. 01"
                        >
                        @error('codigo')
                            <p class="bw-login-error" role="alert">{{ $message }}</p>
                        @enderror
                        <p class="bw-login-help">Código asignado por el administrador. Usa el teclado numérico.</p>
                    </div>

                    <div class="bw-login-pin-section">
                        <span class="bw-login-label">PIN · PASO 2</span>
                        <button
                            type="button"
                            @click="focusPin"
                            :class="{ 'is-active': step === 'pin' }"
                            class="bw-login-pin-display"
                            :aria-label="'PIN de ' + pin.length + ' de 4 dígitos'"
                            aria-live="polite"
                        >
                            <template x-for="index in 4" :key="index">
                                <span class="bw-login-pin-dot" :class="{ 'is-filled': index <= pin.length }"></span>
                            </template>
                        </button>
                        @error('pin')
                            <p class="bw-login-error" role="alert">{{ $message }}</p>
                        @enderror
                        <p x-show="localError" x-cloak x-text="localError" class="bw-login-error" role="alert" aria-live="assertive"></p>
                        <p class="bw-login-help">Primero completa el código; después ingresa tus 4 dígitos.</p>
                    </div>

                    <button type="button" wire:click="showAdminLogin" class="bw-login-secondary-action">
                        Acceso administrador
                    </button>
                </div>

                <div class="bw-login-card bw-login-keypad-card">
                    <div class="bw-login-card-heading">
                        <span class="bw-login-label" x-text="step === 'codigo' ? 'INGRESA TU CÓDIGO' : 'INGRESA TU PIN'"></span>
                        <span class="bw-login-card-status" x-text="step === 'codigo' ? 'Paso 1 de 2' : 'Paso 2 de 2'"></span>
                    </div>

                    <div class="bw-login-keypad" aria-label="Teclado numérico">
                        @foreach (['1', '2', '3', '4', '5', '6', '7', '8', '9'] as $digit)
                            <button
                                type="button"
                                @click="append('{{ $digit }}')"
                                :disabled="loading"
                                class="bw-login-key"
                                aria-label="{{ $digit }}"
                            >
                                {{ $digit }}
                            </button>
                        @endforeach

                        <button type="button" @click="backspace" :disabled="loading" class="bw-login-key bw-login-key-muted" aria-label="Borrar último dígito">⌫</button>
                        <button type="button" @click="append('0')" :disabled="loading" class="bw-login-key" aria-label="0">0</button>
                        <button
                            type="button"
                            @click="continueStep"
                            :disabled="loading"
                            class="bw-login-key bw-login-key-primary"
                            :aria-label="step === 'codigo' ? 'Continuar al PIN' : 'Iniciar sesión'"
                        >
                            <span x-show="!loading" x-cloak x-text="step === 'codigo' ? '→' : 'Entrar'"></span>
                            <span x-show="loading" x-cloak aria-hidden="true">…</span>
                        </button>
                    </div>

                    <button type="button" @click="clearCurrent" :disabled="loading" class="bw-login-clear-action">
                        Borrar <span x-text="step === 'codigo' ? 'código' : 'PIN'"></span>
                    </button>
                </div>
            </section>
        @else
            <section class="bw-login-admin-panel" aria-labelledby="admin-login-title">
                <p class="bw-login-kicker">Acceso protegido</p>
                <h1 id="admin-login-title">Inicia sesión como administrador</h1>
                <p>Usa el correo y la contraseña del administrador para gestionar el sistema.</p>

                <form wire:submit="authenticate" class="bw-login-admin-form">
                    <div>
                        <label for="admin-email" class="bw-login-label">CORREO ELECTRÓNICO</label>
                        <input
                            id="admin-email"
                            type="email"
                            wire:model="email"
                            autocomplete="username"
                            class="bw-login-text-input"
                            autofocus
                        >
                        @error('email')
                            <p class="bw-login-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="admin-password" class="bw-login-label">CONTRASEÑA</label>
                        <input
                            id="admin-password"
                            type="password"
                            wire:model="password"
                            autocomplete="current-password"
                            class="bw-login-text-input"
                        >
                        @error('password')
                            <p class="bw-login-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" wire:loading.attr="disabled" class="bw-login-primary-action">
                        <span wire:loading.remove>Entrar al panel</span>
                        <span wire:loading>Verificando…</span>
                    </button>
                </form>

                <button type="button" wire:click="showCashierLogin" class="bw-login-back-action">
                    Volver al acceso de caja
                </button>
            </section>
        @endif
    </main>
</div>
