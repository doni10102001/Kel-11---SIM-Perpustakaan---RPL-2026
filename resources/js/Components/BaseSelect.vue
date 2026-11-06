<script setup>
import { computed, useId } from 'vue';

defineOptions({ inheritAttrs: false });

const model = defineModel({ type: [String, Number], default: '' });

const props = defineProps({
    label: { type: String, default: null },
    id: { type: String, default: null },
    /** Array string atau objek { value, label, disabled }. */
    options: { type: Array, default: () => [] },
    /** Opsi kosong di awal, mis. "Pilih status". */
    placeholder: { type: String, default: null },
    /** Placeholder boleh dipilih kembali (mis. filter "Semua status"). */
    placeholderSelectable: { type: Boolean, default: false },
    required: { type: Boolean, default: false },
    error: { type: String, default: null },
    hint: { type: String, default: null },
    icon: { type: String, default: null },
    disabled: { type: Boolean, default: false },
});

const idOtomatis = useId();
const idField = computed(() => props.id ?? `select-${idOtomatis}`);
const idError = computed(() => `${idField.value}-error`);
const idHint = computed(() => `${idField.value}-hint`);

const describedBy = computed(
    () => [props.error ? idError.value : null, props.hint ? idHint.value : null].filter(Boolean).join(' ') || undefined,
);

const opsi = computed(() =>
    props.options.map((o) => (typeof o === 'object' ? o : { value: o, label: String(o) })),
);
</script>

<template>
    <div class="sim-field">
        <label v-if="label" :for="idField" class="form-label">
            {{ label }}<span v-if="required" class="sim-required" aria-hidden="true">*</span>
        </label>

        <div :class="['sim-input-group', { 'has-icon': icon }]">
            <i v-if="icon" :class="['bi', icon, 'sim-input-icon']" aria-hidden="true"></i>
            <select
                :id="idField"
                v-model="model"
                v-bind="$attrs"
                :class="['form-select', { 'is-invalid': !!error }]"
                :disabled="disabled"
                :aria-required="required || undefined"
                :aria-invalid="!!error || undefined"
                :aria-describedby="describedBy"
            >
                <option v-if="placeholder" value="" :disabled="!placeholderSelectable">{{ placeholder }}</option>
                <option v-for="o in opsi" :key="o.value" :value="o.value" :disabled="o.disabled">{{ o.label }}</option>
            </select>
        </div>

        <p v-if="error" :id="idError" class="sim-field-error mb-0">
            <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
            <span>{{ error }}</span>
        </p>
        <p v-if="hint" :id="idHint" class="sim-field-hint mb-0">{{ hint }}</p>
    </div>
</template>
