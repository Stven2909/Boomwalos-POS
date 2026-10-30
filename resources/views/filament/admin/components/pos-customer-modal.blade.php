@php
    $open = $open ?? 'customerModalOpen';
    $model = $model ?? 'customerName';
    $title = $title ?? 'Nombre del cliente';
    $subtitle = $subtitle ?? 'Opcional. Puedes dejarlo vacío y continuar.';
    $confirmLabel = $confirmLabel ?? 'Guardar';
    $confirm = $confirm ?? '';
@endphp

<div
    x-show="{{ $open }}"
    x-cloak
    x-trap.noscroll="{{ $open }}"
    class="bw-pos-combo-modal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="customer-modal-title"
>
    <button type="button" class="bw-pos-combo-backdrop" @click="{{ $open }} = false" aria-label="Cerrar"></button>

    <section class="bw-pos-numpad-dialog bw-pos-customer-dialog">
        <header class="bw-pos-numpad-dialog-header">
            <div>
                <span class="bw-pos-step-label">CLIENTE</span>
                <h2 id="customer-modal-title">{{ $title }}</h2>
                <p>{{ $subtitle }}</p>
            </div>
            <button type="button" @click="{{ $open }} = false" class="bw-pos-dialog-close" aria-label="Cerrar">
                <x-heroicon-o-x-mark class="h-6 w-6" />
            </button>
        </header>

        <label class="bw-pos-customer-input-wrap">
            <x-heroicon-o-user class="h-5 w-5" aria-hidden="true" />
            <input
                type="text"
                x-model="{{ $model }}"
                maxlength="60"
                placeholder="Nombre del cliente (opcional)"
                class="bw-pos-customer-input"
                autocomplete="off"
                x-ref="customerInput"
                @keydown.enter.prevent="{{ $confirm }}; {{ $open }} = false"
                x-effect="if ({{ $open }}) { $refs.customerInput.focus() }"
            >
        </label>

        <div class="bw-pos-keyboard" aria-label="Teclado táctil para el nombre del cliente">
            @foreach ([
                ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0'],
                ['Q', 'W', 'E', 'R', 'T', 'Y', 'U', 'I', 'O', 'P'],
                ['A', 'S', 'D', 'F', 'G', 'H', 'J', 'K', 'L', 'Ñ'],
                ['Z', 'X', 'C', 'V', 'B', 'N', 'M'],
            ] as $row)
                <div class="bw-pos-keyboard-row">
                    @foreach ($row as $key)
                        <button
                            type="button"
                            class="bw-pos-keyboard-key"
                            @click="if ({{ $model }}.length < 60) {{ $model }} += '{{ $key }}'"
                        >{{ $key }}</button>
                    @endforeach
                </div>
            @endforeach

            <div class="bw-pos-keyboard-row">
                @foreach (['Á', 'É', 'Í', 'Ó', 'Ú'] as $key)
                    <button
                        type="button"
                        class="bw-pos-keyboard-key is-accent"
                        @click="if ({{ $model }}.length < 60) {{ $model }} += '{{ $key }}'"
                    >{{ $key }}</button>
                @endforeach
                <button
                    type="button"
                    class="bw-pos-keyboard-key is-space"
                    @click="if ({{ $model }}.length < 60) {{ $model }} += ' '"
                >Espacio</button>
                <button
                    type="button"
                    class="bw-pos-keyboard-key is-backspace"
                    @click="{{ $model }} = {{ $model }}.slice(0, -1)"
                    aria-label="Borrar último carácter"
                >
                    <x-heroicon-o-backspace class="h-5 w-5" aria-hidden="true" />
                </button>
                <button
                    type="button"
                    class="bw-pos-keyboard-key is-clear"
                    @click="{{ $model }} = ''"
                >Limpiar</button>
            </div>
        </div>

        <footer class="bw-pos-numpad-dialog-footer bw-pos-customer-footer">
            <button type="button" @click="{{ $open }} = false" class="bw-pos-secondary-button">Cancelar</button>
            <button type="button" @click="{{ $confirm }}; {{ $open }} = false" class="bw-pos-primary-button">
                <x-heroicon-o-check class="h-5 w-5" />
                <span>{{ $confirmLabel }}</span>
            </button>
        </footer>
    </section>
</div>
