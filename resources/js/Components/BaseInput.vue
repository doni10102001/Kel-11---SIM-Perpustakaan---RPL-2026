<script setup>
import { computed, ref, useId, useSlots } from 'vue';

defineOptions({ inheritAttrs: false });

const model = defineModel({ type: [String, Number], default: '' });

const props = defineProps({
    label: { type: String, default: null },
    id: { type: String, default: null },
    type: { type: String, default: 'text' },
    placeholder: { type: String, default: null },
    required: { type: Boolean, default: false },
    /** Pesan kesalahan validasi; bila diisi field ditandai tidak valid. */
    error: { type: String, default: null },
    hint: { type: String, default: null },
    /** Ikon Bootstrap Icons di sisi kiri, mis. "bi-search". */
    icon: { type: String, default: null },
    disabled: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
    /** md | lg */
    size: { type: String, default: 'md' },
    /** Render sebagai <textarea>. */
    multiline: { type: Boolean, default: false },
    rows: { type: Number, default: 3 },
});

const slots = useSlots();
const idOtomatis = useId();
const idField = computed(() => props.id ?? `field-${idOtomatis}`);
const idError = computed(() => `${idField.value}-error`);
const idHint = computed(() => `${idField.value}-hint`);

const describedBy = computed(
    () => [props.error ? idError.value : null, props.hint ? idHint.value : null].filter(Boolean).join(' ') || undefined,
);

const kelasField = computed(() => ['form-control', { 'form-control-lg': props.size === 'lg', 'is-invalid': !!props.error }]);

const elemen = ref(null);

defineExpose({ focus: () => elemen.value?.focus() });
</script>

<template>
    <div class="sim-field">
        <label v-if="label" :for="idField" class="form-label">
            {{ label }}<span v-if="required" class="sim-required" aria-hidden="true">*</span>
        </label>

        <div :class="['sim-input-group', { 'has-icon': icon, 'has-action': slots.action }]">
            <i v-if="icon && !multiline" :class="['bi', icon, 'sim-input-icon']" aria-hidden="true"></i>

            <textarea
                v-if="multiline"
                :id="idField"
                ref="elemen"
                v-model="model"
                v-bind="$attrs"
                :class="kelasField"
                :rows="rows"
                :placeholder="placeholder"
                :disabled="disabled"
                :readonly="readonly"
                :aria-required="required || undefined"
                :aria-invalid="!!error || undefined"
                :aria-describedby="describedBy"
            ></textarea>
            <input
                v-else
                :id="idField"
                ref="elemen"
                v-model="model"
                v-bind="$attrs"
                :type="type"
                :class="kelasField"
                :placeholder="placeholder"
                :disabled="disabled"
                :readonly="readonly"
                :aria-required="required || undefined"
                :aria-invalid="!!error || undefined"
                :aria-describedby="describedBy"
            />

            <div v-if="slots.action" class="sim-input-action">
                <slot name="action" />
            </div>
        </div>

        <p v-if="error" :id="idError" class="sim-field-error mb-0">
            <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
            <span>{{ error }}</span>
        </p>
        <p v-if="hint" :id="idHint" class="sim-field-hint mb-0">{{ hint }}</p>
    </div>
</template>
